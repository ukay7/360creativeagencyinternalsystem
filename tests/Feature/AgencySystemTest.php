<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AgencySystemTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_sign_in_and_open_every_module(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@agencyos.local',
            'password' => 'Admin@360!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        foreach (['dashboard','quote_studio','saved_quotes','quote_settings','leads','pipeline','discovery','clients','packages','services','proposals','contracts','projects','tasks','visits','content','media','calendar','invoices','time','team','reports','settings','audit','change_password','search?q=client'] as $module) {
            $this->get('/'.$module)->assertOk()->assertSee('360 Creative Agency');
        }
    }

    public function test_lead_workflow_persists_to_mysql(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $serviceIds = DB::table('services')->where('active', true)->orderBy('id')->limit(2)->pluck('id')->all();
        $serviceNames = DB::table('services')->whereIn('id', $serviceIds)->orderBy('name')->pluck('name')->implode(', ');

        $response = $this->post('/leads', [
            'action' => 'create_lead',
            'first_name' => 'System',
            'last_name' => 'Test',
            'company_name' => 'Laravel MySQL Verification',
            'email' => 'verification@example.test',
            'company_size_range' => '11_50',
            'years_in_business_range' => '3_5',
            'business_stage' => 'existing_business',
            'budget_range' => '10k_25k',
            'service_ids' => $serviceIds,
        ]);

        $response->assertRedirect('/leads');
        $this->assertDatabaseHas('leads', [
            'company_name' => 'Laravel MySQL Verification',
            'email' => 'verification@example.test',
            'company_size_range' => '11_50',
            'employee_count' => 30,
            'years_in_business_range' => '3_5',
            'business_stage' => 'existing_business',
            'budget_range' => '10k_25k',
            'estimated_budget' => 17500,
            'lead_score' => 64,
            'services_interested' => $serviceNames,
        ]);
        $leadId = DB::table('leads')->where('email', 'verification@example.test')->value('id');
        $leadCount = DB::table('leads')->count();
        $this->post('/leads', [
            'action'=>'create_lead', 'first_name'=>'Duplicate', 'last_name'=>'Attempt',
            'company_name'=>'Laravel MySQL Verification', 'email'=>'verification@example.test',
        ])->assertRedirect('/leads')->assertSessionHas('danger');
        $this->assertSame($leadCount, DB::table('leads')->count());
        foreach ($serviceIds as $serviceId) {
            $this->assertDatabaseHas('lead_service_interests', ['lead_id'=>$leadId, 'service_id'=>$serviceId]);
        }
        $this->assertDatabaseHas('opportunities', [
            'title' => 'Laravel MySQL Verification opportunity',
            'estimated_value' => 17500,
            'services' => $serviceNames,
        ]);

        $this->get('/leads')
            ->assertOk()
            ->assertSee('Lead fit score is calculated automatically')
            ->assertSee('Company size')
            ->assertSee('Services interested')
            ->assertSee('Conversion')
            ->assertSee('Minimum fit')
            ->assertDontSee('name="lead_score"', false)
            ->assertDontSee('name="services_interested"', false);

        $this->get('/lead?id='.$leadId)
            ->assertOk()
            ->assertSee('LEAD RELATIONSHIP')
            ->assertSee('Edit lead')
            ->assertSee('Add follow-up')
            ->assertSee('Conversion probability')
            ->assertSee('Start discovery');

        $updatedServiceIds = [(int)$serviceIds[0]];
        $updatedServiceName = DB::table('services')->where('id', $updatedServiceIds[0])->value('name');
        $updatedPayload = [
            'action'=>'update_lead', 'lead_id'=>$leadId, 'first_name'=>'System', 'last_name'=>'Updated',
            'company_name'=>'Laravel Qualified Lead', 'email'=>'verification@example.test',
            'phone'=>'+1 416 555 0111', 'website'=>'https://example.test', 'industry'=>'Technology',
            'status'=>'new', 'company_size_range'=>'51_100', 'years_in_business_range'=>'6_10',
            'business_stage'=>'existing_business', 'budget_range'=>'25k_50k',
            'service_ids'=>$updatedServiceIds, 'notes'=>'Qualification context updated.',
        ];
        $this->post('/lead?id='.$leadId, $updatedPayload)->assertRedirect('/lead?id='.$leadId)->assertSessionHas('success');

        $this->assertDatabaseHas('leads', [
            'id'=>$leadId, 'company_name'=>'Laravel Qualified Lead', 'last_name'=>'Updated',
            'employee_count'=>75, 'estimated_budget'=>37500, 'lead_score'=>74,
            'services_interested'=>$updatedServiceName, 'notes'=>'Qualification context updated.',
        ]);
        $this->assertDatabaseHas('lead_service_interests', ['lead_id'=>$leadId, 'service_id'=>$updatedServiceIds[0]]);
        $this->assertDatabaseMissing('lead_service_interests', ['lead_id'=>$leadId, 'service_id'=>$serviceIds[1]]);
        $this->assertDatabaseHas('opportunities', ['lead_id'=>$leadId, 'title'=>'Laravel Qualified Lead opportunity', 'estimated_value'=>37500, 'services'=>$updatedServiceName]);
        $this->assertDatabaseHas('audit_logs', ['action'=>'updated', 'entity_type'=>'lead', 'entity_id'=>$leadId]);

        $this->post('/lead?id='.$leadId, [
            'action'=>'add_lead_followup', 'lead_id'=>$leadId, 'type'=>'email', 'outcome'=>'interested',
            'subject'=>'Campaign requirements received', 'notes'=>'The lead confirmed interest and requested a proposal.',
            'followed_up_at'=>'2026-08-15T10:30', 'next_follow_up_at'=>'2026-08-20T09:00',
        ])->assertRedirect('/lead?id='.$leadId)->assertSessionHas('success');

        $this->assertDatabaseHas('lead_followups', [
            'lead_id'=>$leadId, 'type'=>'email', 'outcome'=>'interested',
            'subject'=>'Campaign requirements received', 'followed_up_at'=>'2026-08-15 10:30:00',
            'next_follow_up_at'=>'2026-08-20 09:00:00',
        ]);
        $this->assertDatabaseHas('leads', ['id'=>$leadId, 'status'=>'contacted', 'last_contact_at'=>'2026-08-15 10:30:00', 'next_follow_up_at'=>'2026-08-20 09:00:00']);
        $contactedStageId = DB::table('pipeline_stages')->where('slug', 'contacted')->value('id');
        $this->assertDatabaseHas('opportunities', ['lead_id'=>$leadId, 'stage_id'=>$contactedStageId, 'next_action'=>'Complete scheduled follow-up']);
        $this->assertDatabaseHas('audit_logs', ['action'=>'follow_up_added', 'entity_type'=>'lead', 'entity_id'=>$leadId]);
        $this->get('/lead?id='.$leadId)
            ->assertOk()
            ->assertSee('Campaign requirements received')
            ->assertSee('The lead confirmed interest and requested a proposal.')
            ->assertSee('Interested');

        $followupId = DB::table('lead_followups')->where('lead_id', $leadId)->value('id');
        $overdueInput = now()->subDay()->format('Y-m-d\T09:00');
        $overdueDatabase = now()->subDay()->format('Y-m-d 09:00:00');
        $this->post('/lead?id='.$leadId, [
            'action'=>'update_lead_followup', 'lead_id'=>$leadId, 'followup_id'=>$followupId,
            'type'=>'email', 'outcome'=>'needs_follow_up', 'subject'=>'Corrected campaign requirements',
            'notes'=>'Corrected notes with the next action confirmed.', 'followed_up_at'=>'2026-08-15T10:30',
            'next_follow_up_at'=>$overdueInput,
        ])->assertRedirect('/lead?id='.$leadId)->assertSessionHas('success');

        $this->assertDatabaseHas('lead_followups', [
            'id'=>$followupId, 'subject'=>'Corrected campaign requirements', 'outcome'=>'needs_follow_up',
            'notes'=>'Corrected notes with the next action confirmed.', 'next_follow_up_at'=>$overdueDatabase,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action'=>'follow_up_corrected', 'entity_type'=>'lead', 'entity_id'=>$leadId]);
        $this->get('/lead?id='.$leadId)->assertOk()->assertSee('Corrected campaign requirements')->assertSee('Correct');
        $this->get('/leads?status=contacted&min_fit_score=70&follow_up_state=overdue')
            ->assertOk()->assertSee('Laravel Qualified Lead')->assertSee('Overdue');
        $this->get('/dashboard')->assertOk()->assertSee('QUOTE TO DELIVERY')->assertSee('Project delivery')->assertSee('Task flow');

        $this->get('/discovery?lead_id='.$leadId)
            ->assertOk()
            ->assertSee('value="'.$leadId.'" selected', false);
        $this->post('/discovery', [
            'action'=>'create_consultation', 'lead_id'=>$leadId,
            'main_goal'=>'Increase qualified inbound opportunities',
            'revenue_goal'=>'Reach $1.2M annual revenue', 'acquisition_goal'=>'Add 20 qualified leads monthly',
            'expansion_plans'=>'Open a second service location.', 'challenges'=>'Inconsistent lead quality',
            'competitors'=>'Regional specialist agencies', 'current_website'=>'https://example.test',
            'current_social_media'=>'Instagram and LinkedIn', 'google_business'=>'Needs optimization',
            'current_advertising'=>'Google Search campaigns', 'seo_status'=>'Limited local rankings',
            'marketing_budget'=>'$8,000 monthly', 'agency_experience'=>'Previously used a freelance team.',
            'target_customer'=>'Established local service businesses', 'geographic_market'=>'Greater Toronto Area',
            'customer_demographics'=>'Owners and operations managers', 'customer_problems'=>'Need predictable growth',
            'why_customers_choose'=>'Specialist expertise and responsiveness',
            'current_problems'=>['Low leads'], 'desired_outcomes'=>['More leads'],
            'notes'=>'Structured discovery completed from the lead profile.',
        ])->assertRedirect('/discovery')->assertSessionHas('success');
        $this->assertDatabaseHas('consultations', ['lead_id'=>$leadId, 'notes'=>'Structured discovery completed from the lead profile.']);
        $discoveryStageId = DB::table('pipeline_stages')->where('slug', 'discovery')->value('id');
        $this->assertDatabaseHas('leads', ['id'=>$leadId, 'status'=>'discovery']);
        $this->assertDatabaseHas('opportunities', ['lead_id'=>$leadId, 'stage_id'=>$discoveryStageId]);
        $this->get('/lead?id='.$leadId)->assertOk()->assertSee('Increase qualified inbound opportunities')->assertSee('Discovery sessions')->assertSee('View full details');
        $this->get('/discovery')
            ->assertOk()->assertSee('View details')->assertDontSee('Full discovery records')
            ->assertSee('Reach $1.2M annual revenue')->assertSee('Instagram and LinkedIn')
            ->assertSee('Established local service businesses')->assertSee('Structured discovery completed from the lead profile.');

        $lostPayload = array_merge($updatedPayload, ['status'=>'lost', 'lost_reason'=>'']);
        $this->post('/lead?id='.$leadId, $lostPayload)->assertRedirect('/lead?id='.$leadId)->assertSessionHas('danger');
        $this->assertDatabaseHas('leads', ['id'=>$leadId, 'status'=>'discovery', 'lost_reason'=>null]);

        $lostPayload['lost_reason'] = 'Budget timing did not align with the proposed engagement.';
        $this->post('/lead?id='.$leadId, $lostPayload)->assertRedirect('/lead?id='.$leadId)->assertSessionHas('success');
        $this->assertDatabaseHas('leads', ['id'=>$leadId, 'status'=>'lost', 'lost_reason'=>'Budget timing did not align with the proposed engagement.']);
        $lostStageId = DB::table('pipeline_stages')->where('slug', 'lost')->value('id');
        $this->assertDatabaseHas('opportunities', ['lead_id'=>$leadId, 'stage_id'=>$lostStageId, 'probability'=>0]);
        $this->get('/lead?id='.$leadId)->assertOk()->assertSee('Lost lead:')->assertSee('Reopen or edit');

        $reopenPayload = array_merge($updatedPayload, ['status'=>'qualified', 'lost_reason'=>'']);
        $this->post('/lead?id='.$leadId, $reopenPayload)->assertRedirect('/lead?id='.$leadId)->assertSessionHas('success');
        $this->assertDatabaseHas('leads', ['id'=>$leadId, 'status'=>'qualified', 'lost_reason'=>null, 'lost_at'=>null]);
        $qualifiedStageId = DB::table('pipeline_stages')->where('slug', 'qualified')->value('id');
        $this->assertDatabaseHas('opportunities', ['lead_id'=>$leadId, 'stage_id'=>$qualifiedStageId, 'probability'=>55]);

        $this->post('/leads', ['action'=>'convert_lead', 'lead_id'=>$leadId])
            ->assertRedirect()
            ->assertSessionHas('success');
        $clientId = DB::table('leads')->where('id', $leadId)->value('converted_client_id');
        $businessId = DB::table('clients')->where('id', $clientId)->value('business_id');
        $this->assertDatabaseHas('businesses', ['id'=>$businessId, 'employee_count'=>75, 'years_in_business'=>8]);
        $this->assertSame('Medium Business', DB::table('businesses')->join('business_sizes','business_sizes.id','=','businesses.business_size_id')->where('businesses.id',$businessId)->value('business_sizes.name'));
    }

    public function test_role_permissions_and_user_overrides_control_routes_and_sidebar(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name'=>'Limited QA', 'slug'=>'limited_qa', 'description'=>'Access test', 'created_at'=>now()->toDateTimeString(),
        ]);
        $leadsPermission = DB::table('permissions')->where('slug', 'leads.access')->value('id');
        $pipelinePermission = DB::table('permissions')->where('slug', 'pipeline.access')->value('id');
        DB::table('role_permissions')->insert(['role_id'=>$roleId, 'permission_id'=>$leadsPermission]);
        $userId = DB::table('users')->insertGetId([
            'role_id'=>$roleId, 'name'=>'Limited User', 'email'=>'limited@example.test',
            'password_hash'=>password_hash('Limited@360!', PASSWORD_DEFAULT), 'status'=>'active',
            'created_at'=>now()->toDateTimeString(), 'updated_at'=>now()->toDateTimeString(),
        ]);
        $user = User::query()->findOrFail($userId);
        $this->actingAs($user);

        $this->get('/leads')->assertOk()->assertSee('Lead Management')->assertDontSee('Sales Pipeline');
        $this->get('/pipeline')->assertForbidden();

        DB::table('user_permissions')->insert([
            ['user_id'=>$userId,'permission_id'=>$leadsPermission,'allowed'=>false,'created_at'=>now(),'updated_at'=>now()],
            ['user_id'=>$userId,'permission_id'=>$pipelinePermission,'allowed'=>true,'created_at'=>now(),'updated_at'=>now()],
        ]);

        $this->get('/leads')->assertForbidden();
        $this->get('/pipeline')->assertOk()->assertSee('Sales Pipeline')->assertDontSee('Lead Management');
    }

    public function test_super_admin_can_create_a_role_from_settings(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $dashboardPermission = DB::table('permissions')->where('slug', 'dashboard.access')->value('id');

        $this->post('/settings', [
            'action'=>'create_role', 'name'=>'Client Viewer', 'slug'=>'client_viewer',
            'description'=>'Restricted client viewer', 'permission_ids'=>[$dashboardPermission],
        ])->assertRedirect('/settings');

        $this->assertDatabaseHas('roles', ['slug'=>'client_viewer']);
        $roleId = DB::table('roles')->where('slug', 'client_viewer')->value('id');
        $this->assertDatabaseHas('role_permissions', ['role_id'=>$roleId,'permission_id'=>$dashboardPermission]);
        $this->assertDatabaseCount('navigation_items', 24);
        $this->assertDatabaseCount('navigation_groups', 7);
    }

    public function test_roles_can_be_deleted_only_when_unassigned_and_unprotected(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $dashboardPermission = (int)DB::table('permissions')->where('slug', 'dashboard.access')->value('id');
        $roleId = DB::table('roles')->insertGetId([
            'name'=>'Temporary Delete QA',
            'slug'=>'temporary_delete_qa',
            'description'=>'Safe deletion test role',
            'created_at'=>now()->toDateTimeString(),
        ]);
        DB::table('role_permissions')->insert(['role_id'=>$roleId,'permission_id'=>$dashboardPermission]);

        $this->get('/settings')->assertOk()
            ->assertSee('name="action" value="delete_role"', false)
            ->assertSee('Delete role');
        $this->post('/settings', [
            'action'=>'delete_role',
            'role_id'=>$roleId,
        ])->assertRedirect('/settings')->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id'=>$roleId]);
        $this->assertDatabaseMissing('role_permissions', ['role_id'=>$roleId]);
        $this->assertDatabaseHas('audit_logs', ['action'=>'deleted','entity_type'=>'role','entity_id'=>$roleId]);

        $adminRoleId = (int)DB::table('roles')->where('slug', 'admin')->value('id');
        $this->post('/settings', [
            'action'=>'delete_role',
            'role_id'=>$adminRoleId,
        ])->assertRedirect('/settings')->assertSessionHas('danger', 'Protected system roles cannot be deleted.');
        $this->assertDatabaseHas('roles', ['id'=>$adminRoleId,'slug'=>'admin']);

        $assignedRoleId = DB::table('roles')->insertGetId([
            'name'=>'Assigned Delete QA',
            'slug'=>'assigned_delete_qa',
            'description'=>'Must be reassigned first',
            'created_at'=>now()->toDateTimeString(),
        ]);
        DB::table('users')->insert([
            'role_id'=>$assignedRoleId,
            'name'=>'Assigned Role User QA',
            'email'=>'assigned-role-user-qa@example.test',
            'password_hash'=>password_hash('AssignedRole!2026', PASSWORD_DEFAULT),
            'status'=>'active',
            'created_at'=>now()->toDateTimeString(),
            'updated_at'=>now()->toDateTimeString(),
        ]);
        $this->get('/settings')->assertOk()->assertSee('Reassign the assigned user before deletion.');
        $this->post('/settings', [
            'action'=>'delete_role',
            'role_id'=>$assignedRoleId,
        ])->assertRedirect('/settings')->assertSessionHas('danger', 'Reassign the user using this role before deleting it.');
        $this->assertDatabaseHas('roles', ['id'=>$assignedRoleId]);
    }

    public function test_sidebar_uses_database_driven_sections_in_business_order(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());

        $navigation = app(\App\Services\Auth::class)->navigationItems();

        $this->assertSame(
            ['Command Center', 'Quote & Onboarding', 'Project Delivery', 'Operations & Finance', 'Settings', 'Change Password'],
            array_column($navigation, 'label')
        );

        $groups = [];
        foreach ($navigation as $node) {
            if ($node['type'] === 'group') {
                $groups[$node['label']] = array_column($node['children'], 'label');
            }
        }

        $this->assertSame(['New Quote', 'Saved Quotes', 'Clients', 'Quote Settings'], $groups['Quote & Onboarding']);
        $this->assertSame(['Projects', 'Tasks'], $groups['Project Delivery']);
        $this->assertArrayNotHasKey('Content & Marketing', $groups);
        $this->assertSame(['Agency Calendar', 'Invoices', 'Reports'], $groups['Operations & Finance']);
        $this->assertSame(['Team', 'System Settings', 'Audit Log'], $groups['Settings']);

        $this->get('/settings')
            ->assertOk()
            ->assertSee('Quote &amp; Onboarding', false)
            ->assertSee('System Settings');
    }

    public function test_quote_studio_saves_multilingual_item_pricing_and_support(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $package=DB::table('packages')->where('package_type','quote_setup')->where('tier','basic')->first();
        $supportPackage=DB::table('packages')->where('package_type','support_contract')->where('tier','starter')->first();
        $expectedSupportMonthly=(float)DB::table('package_items')->where('package_id',$supportPackage->id)->where('included',1)->sum(DB::raw('quantity * unit_price'));
        $items=DB::table('package_items')->where('package_id',$package->id)->orderBy('sort_order')->limit(2)->get();
        $payload=$items->map(fn($item)=>[
            'service_id'=>$item->service_id,'list_price'=>(float)$item->unit_price,'discount_percent'=>10,'custom_price'=>null,
            'description'=>$item->description,'description_ar'=>$item->description_ar,'description_he'=>$item->description_he,
        ])->all();
        $answers=[];
        foreach(config('quote_studio.assessment') as $question){$answers[$question['key']]=$question['options'][1]['value'];}

        $response=$this->post('/quote_studio',[
            'action'=>'save_quote','business_name'=>'Quote Journey Test','contact_name'=>'Rami Test','contact_email'=>'rami@example.test',
            'business_stage'=>'existing_business','years_operating'=>4,'locale'=>'ar','assessment_json'=>json_encode($answers),
            'items_json'=>json_encode($payload),'selected_tier'=>'basic','support_plan'=>'3_months','membership_plan'=>'starter',
            'membership_term'=>3,'tax_enabled'=>1,'tax_percent'=>13,'validity_days'=>14,
        ]);

        $quote=DB::table('proposals')->where('business_name','Quote Journey Test')->first();
        $response->assertRedirect('/quote_view?id='.$quote->id)->assertSessionHas('success');
        $this->assertSame('ar',$quote->locale);
        $this->assertSame(10,(int)$quote->assessment_score);
        $this->assertSame('medium',$quote->recommended_tier);
        $this->assertSame('basic',$quote->selected_tier);
        $this->assertGreaterThan(0,(float)$quote->support_amount);
        $this->assertSame($expectedSupportMonthly,(float)$quote->membership_monthly_price);
        $this->assertSame(3,(int)$quote->membership_duration_months);
        $this->assertSame($expectedSupportMonthly * 3,(float)$quote->membership_amount);
        $this->assertDatabaseHas('leads',['email'=>'rami@example.test','status'=>'proposal']);
        $this->assertDatabaseHas('proposal_items',['proposal_id'=>$quote->id,'item_type'=>'service']);
        $this->assertDatabaseHas('proposal_items',['proposal_id'=>$quote->id,'item_type'=>'support']);
        $this->assertDatabaseHas('proposal_items',['proposal_id'=>$quote->id,'item_type'=>'support_contract']);
        $this->get('/quote_view?id='.$quote->id.'&lang=ar')->assertOk()->assertSee('ملخص العرض')->assertSee($items[0]->description_ar);
        $this->get('/quote/share/'.$quote->share_token.'?lang=he')->assertOk()->assertSee('סיכום הצעה')->assertSee($items[0]->description_he);
        $this->get('/quote_studio')->assertOk()->assertSee('Quote Journey Test')->assertSee('Prospect');
    }

    public function test_global_language_direction_and_refined_quote_plans_are_available(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());

        $this->from('/quote_studio')->post('/ui-language', ['locale'=>'he'])
            ->assertRedirect('/quote_studio')
            ->assertSessionHas('ui_locale', 'he');

        $this->get('/quote_studio')
            ->assertOk()
            ->assertSee('<html lang="he" dir="rtl">', false)
            ->assertSee('8 חודשים')
            ->assertSee('סושיאל Growth')
            ->assertSee('How scoring works')
            ->assertSee('Edit package scopes')
            ->assertSee('quote-scope-modal')
            ->assertSee('existing-relationship');

        $this->assertDatabaseHas('quote_support_plans', ['code'=>'8_months','duration_months'=>8]);
        $this->assertDatabaseHas('quote_memberships', ['code'=>'growth','name'=>'Social Media Growth']);
        $this->assertDatabaseHas('quote_memberships', ['code'=>'custom','name'=>'Custom social media plan']);
    }

    public function test_quote_client_picker_is_searchable_and_new_prospect_resets_client_fields(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());

        $this->get('/quote_studio')
            ->assertOk()
            ->assertSee('id="relationship-search"', false)
            ->assertSee('role="combobox"', false)
            ->assertSee('data-membership-term="3"', false)
            ->assertSee('data-membership-term="6"', false)
            ->assertSee('data-membership-term="12"', false)
            ->assertSee('data-membership-term="custom"', false)
            ->assertDontSee('data-membership-term="18"', false)
            ->assertSee('Search by business, contact, email, or phone')
            ->assertSee('class="required" data-i18n="contact_name"', false)
            ->assertSee('class="required" data-i18n="business_name"', false)
            ->assertSee('class="required" data-i18n="email"', false)
            ->assertSee('data-i18n="select_business_stage"', false);

        $script = file_get_contents(public_path('assets/js/quote-studio.js'));
        $this->assertStringContainsString('function clearClientDetails()', $script);
        $this->assertStringContainsString("form.elements.business_stage.value = '';", $script);
        $this->assertStringContainsString("form.elements.years_operating.value = '';", $script);
        $this->assertStringContainsString("['contact_name','business_name','contact_email','contact_phone','website','internal_notes']", $script);
    }

    public function test_quote_studio_saves_custom_support_and_custom_membership_term(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $package=DB::table('packages')->where('package_type','quote_setup')->where('tier','pro')->first();
        $item=DB::table('package_items')->where('package_id',$package->id)->orderBy('sort_order')->first();
        $answers=[];
        foreach(config('quote_studio.assessment') as $question){$answers[$question['key']]=$question['options'][2]['value'];}

        $response=$this->post('/quote_studio',[
            'action'=>'save_quote','business_name'=>'Custom Commercial Terms','contact_name'=>'Osama Test','contact_email'=>'osama@example.test',
            'locale'=>'en','assessment_json'=>json_encode($answers),'selected_tier'=>'pro',
            'items_json'=>json_encode([['service_id'=>$item->service_id,'list_price'=>1000,'discount_percent'=>0,'custom_price'=>null,'description'=>$item->description,'description_ar'=>$item->description_ar,'description_he'=>$item->description_he]]),
            'support_plan'=>'custom','custom_support_name'=>'Priority Care Plus','custom_support_rate_percent'=>17.5,'custom_support_duration'=>6,
            'membership_plan'=>'custom','custom_membership_name'=>'Osama Social Accelerator',
            'custom_membership_monthly_price'=>1250,'membership_term'=>18,'tax_percent'=>13,'validity_days'=>7,
        ]);

        $quote=DB::table('proposals')->where('business_name','Custom Commercial Terms')->first();
        $response->assertRedirect('/quote_view?id='.$quote->id);
        $this->assertSame(20,(int)$quote->assessment_score);
        $this->assertSame('Priority Care Plus',$quote->support_name);
        $this->assertSame(6,(int)$quote->support_duration_months);
        $this->assertSame(17.5,(float)$quote->support_rate_percent);
        $this->assertSame(350.0,(float)$quote->support_amount);
        $this->assertSame('Osama Social Accelerator',$quote->membership_name);
        $this->assertSame(18,(int)$quote->membership_duration_months);
        $this->assertSame(1250.0,(float)$quote->membership_monthly_price);
        $this->assertSame(22500.0,(float)$quote->membership_amount);
        $this->assertDatabaseHas('proposal_items',['proposal_id'=>$quote->id,'item_type'=>'support','title'=>'Priority Care Plus','total'=>350]);
        $this->assertDatabaseHas('proposal_items',['proposal_id'=>$quote->id,'item_type'=>'membership','quantity'=>18,'unit_price'=>1250,'total'=>22500]);
        $this->get('/quote_view?id='.$quote->id)->assertOk()->assertSee('Priority Care Plus')->assertSee('17.5% of the setup subtotal per 3 months')->assertSee('Osama Social Accelerator')->assertSee('$1,250.00/month for 18 months.');
    }

    public function test_admin_can_create_setup_package_add_service_and_use_it_in_quote_studio(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());

        $this->post('/quote_settings',[
            'action'=>'save_quote_package','name'=>'Enterprise Launch','name_ar'=>'إطلاق المؤسسات','name_he'=>'השקת Enterprise',
            'tier'=>'enterprise','description'=>'A custom enterprise launch package.','description_ar'=>'باقة إطلاق مخصصة للمؤسسات.','description_he'=>'חבילת השקה מותאמת לארגונים.','display_order'=>4,'active'=>1,
        ])->assertRedirect('/quote_settings')->assertSessionHas('success');

        $package=DB::table('packages')->where('package_type','quote_setup')->where('tier','enterprise')->first();
        $service=DB::table('services')->where('name','SEO')->first();
        $this->post('/quote_settings',[
            'action'=>'add_quote_package_item','package_id'=>$package->id,'service_id'=>$service->id,'unit_price'=>2400,'sort_order'=>1,
            'description'=>'Enterprise SEO strategy, technical audit, and implementation.','description_ar'=>'استراتيجية SEO وتدقيق تقني وتنفيذ للمؤسسات.','description_he'=>'אסטרטגיית SEO, ביקורת טכנית ויישום לארגונים.','included'=>1,
        ])->assertRedirect('/quote_settings')->assertSessionHas('success');

        $item=DB::table('package_items')->where('package_id',$package->id)->where('service_id',$service->id)->first();
        $this->assertNotNull($item);
        $this->get('/quote_settings')->assertOk()->assertSee('Add setup package')->assertSee('Add service to package')->assertSee('Enterprise Launch')
            ->assertSee('Package total')->assertSee('$2,400.00', false);
        $this->get('/quote_studio')->assertOk()->assertSee('Enterprise Launch')->assertSee('data-tier="enterprise"',false);

        $answers=[];foreach(config('quote_studio.assessment') as $question){$answers[$question['key']]=$question['options'][1]['value'];}
        $this->post('/quote_studio',[
            'action'=>'save_quote','business_name'=>'Enterprise Package Client','contact_name'=>'Package Test','contact_email'=>'package@example.test','locale'=>'en',
            'assessment_json'=>json_encode($answers),'selected_tier'=>'enterprise','items_json'=>json_encode([['service_id'=>$service->id,'list_price'=>2400,'discount_percent'=>0,'custom_price'=>null,'description'=>$item->description,'description_ar'=>$item->description_ar,'description_he'=>$item->description_he]]),
            'support_plan'=>'none','membership_plan'=>'none','membership_term'=>0,'tax_percent'=>13,'validity_days'=>7,
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('proposals',['business_name'=>'Enterprise Package Client','package_id'=>$package->id,'selected_tier'=>'enterprise']);

        $this->post('/quote_settings',['action'=>'remove_quote_package_item','package_item_id'=>$item->id])
            ->assertRedirect('/quote_settings')->assertSessionHas('success');
        $this->assertDatabaseMissing('package_items',['id'=>$item->id]);
    }

    public function test_service_items_flow_through_package_quote_and_onboarding_as_ordinary_tasks(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $package = DB::table('packages')->where('package_type', 'quote_setup')->where('tier', 'basic')->first();
        $packageItem = DB::table('package_items')->where('package_id', $package->id)->where('included', 1)->orderBy('sort_order')->first();
        $service = DB::table('services')->where('id', $packageItem->service_id)->first();

        $this->post('/quote_settings', [
            'action'=>'save_quote_service', 'service_id'=>$service->id, 'category_id'=>$service->category_id,
            'name'=>$service->name, 'name_ar'=>$service->name_ar, 'name_he'=>$service->name_he,
            'description'=>$service->description, 'description_ar'=>$service->description_ar, 'description_he'=>$service->description_he,
            'pricing_type'=>$service->pricing_type, 'default_price'=>$service->default_price,
            'cost_estimate'=>$service->cost_estimate, 'estimated_hours'=>$service->estimated_hours,
            'quote_sort'=>$service->quote_sort, 'taxable'=>1, 'active'=>1, 'quote_enabled'=>1,
            'work_items_present'=>1,
            'work_items'=>[[
                'name'=>'Campaign Optimization', 'name_ar'=>'تحسين الحملة', 'name_he'=>'אופטימיזציית קמפיין',
                'task_description'=>'Master service instructions.', 'schedule_type'=>'recurring', 'frequency'=>'weekly',
            ]],
        ])->assertRedirect('/quote_settings')->assertSessionHas('success');

        $workItem = DB::table('service_work_items')->where('service_id', $service->id)->where('name', 'Campaign Optimization')->first();
        $this->assertNotNull($workItem);
        $this->assertSame(1, (int)$workItem->interval_count);
        $this->assertNull($workItem->occurrence_count);
        $this->assertDatabaseMissing('package_service_item_rules', [
            'package_id'=>$package->id, 'service_work_item_id'=>$workItem->id,
        ]);
        $unselectedPackage = collect(app(\App\Services\QuoteStudioService::class)->builderData()['packages'])->firstWhere('id', $package->id);
        $unselectedService = collect($unselectedPackage['items'])->firstWhere('service_id', $service->id);
        $unselectedWorkItem = collect($unselectedService['work_items'])->firstWhere('id', $workItem->id);
        $this->assertSame(0, (int)$unselectedWorkItem['included']);
        $this->get('/quote_settings')->assertOk()
            ->assertSee('Service item templates')->assertSee('Package service items')
            ->assertSee('Add to this package')
            ->assertDontSee('Every N periods')->assertDontSee('Starts after (days)')
            ->assertDontSee('Occurrences')->assertDontSee('End after months');

        $this->post('/quote_settings', [
            'action'=>'save_quote_package_item', 'package_item_id'=>$packageItem->id,
            'unit_price'=>$packageItem->unit_price, 'sort_order'=>$packageItem->sort_order,
            'description'=>$packageItem->description, 'description_ar'=>$packageItem->description_ar,
            'description_he'=>$packageItem->description_he, 'included'=>1, 'work_items_present'=>1,
            'work_items'=>[[
                'service_work_item_id'=>$workItem->id, 'included'=>1,
                'name'=>'Basic Campaign Check', 'task_description'=>'Instructions customised for the Basic package.',
                'value_label'=>'1 optimization each week', 'schedule_type'=>'recurring', 'frequency'=>'weekly',
            ]],
        ])->assertRedirect('/quote_settings')->assertSessionHas('success');

        $this->assertDatabaseHas('package_service_item_rules', [
            'package_id'=>$package->id, 'service_work_item_id'=>$workItem->id, 'included'=>1,
            'name_override'=>'Basic Campaign Check', 'description_override'=>'Instructions customised for the Basic package.',
            'value_label'=>'1 optimization each week',
        ]);
        $builderPackage = collect(app(\App\Services\QuoteStudioService::class)->builderData()['packages'])->firstWhere('id', $package->id);
        $builderService = collect($builderPackage['items'])->firstWhere('service_id', $service->id);
        $selectedWorkItem = collect($builderService['work_items'])->firstWhere('id', $workItem->id);
        $this->assertSame(1, (int)$selectedWorkItem['included']);
        $this->assertSame('Basic Campaign Check', $selectedWorkItem['name']);

        $answers = [];
        foreach (config('quote_studio.assessment') as $question) { $answers[$question['key']] = $question['options'][0]['value']; }
        $this->post('/quote_studio', [
            'action'=>'save_quote', 'business_name'=>'Final Scope Client', 'contact_name'=>'Workflow Tester',
            'contact_email'=>'workflow@example.test', 'business_stage'=>'existing_business', 'years_operating'=>2,
            'locale'=>'en', 'assessment_json'=>json_encode($answers), 'selected_tier'=>'basic',
            'items_json'=>json_encode([[
                'service_id'=>$service->id, 'list_price'=>(float)$packageItem->unit_price,
                'discount_percent'=>0, 'custom_price'=>null, 'description'=>$packageItem->description,
                'description_ar'=>$packageItem->description_ar, 'description_he'=>$packageItem->description_he,
                'work_items'=>[[
                    'service_work_item_id'=>$workItem->id, 'included'=>1,
                    'title'=>'Client Final Campaign Task', 'description'=>'Final instructions agreed in the quote.',
                    'scope_value'=>'2 manual campaign checks', 'schedule_type'=>'recurring', 'frequency'=>'weekly',
                ]],
            ]]),
            'support_plan'=>'3_months', 'membership_plan'=>'growth', 'membership_term'=>3,
            'tax_percent'=>13, 'validity_days'=>7,
        ])->assertSessionHas('success');

        $quote = DB::table('proposals')->where('business_name', 'Final Scope Client')->first();
        $proposalItem = DB::table('proposal_items')->where('proposal_id', $quote->id)->where('service_id', $service->id)->first();
        $supportItem = DB::table('proposal_items')->where('proposal_id', $quote->id)->where('item_type', 'support')->first();
        $membershipItem = DB::table('proposal_items')->where('proposal_id', $quote->id)->where('item_type', 'support_contract')->first();
        $this->assertSame(1, DB::table('proposal_items')->where('proposal_id', $quote->id)->where('item_type', 'service')->count());
        $this->assertNotNull($supportItem);
        $this->assertNotNull($membershipItem);
        $proposalWorkItem = DB::table('proposal_work_items')->where('proposal_item_id', $proposalItem->id)->first();
        $this->assertSame('Client Final Campaign Task', $proposalWorkItem->title);
        $this->assertSame('Final instructions agreed in the quote.', $proposalWorkItem->description);
        $this->assertSame('2 manual campaign checks', $proposalWorkItem->scope_value);

        $this->post('/saved_quotes', ['action'=>'onboard_quote', 'proposal_id'=>$quote->id])
            ->assertRedirectContains('/client?id=')->assertSessionHas('success');
        $project = DB::table('projects')->where('source_proposal_item_id', $proposalItem->id)->first();
        $task = DB::table('project_tasks')->where('project_id', $project->id)->first();
        $supportProject = DB::table('projects')->where('source_proposal_item_id', $supportItem->id)->first();
        $membershipProject = DB::table('projects')->where('source_proposal_item_id', $membershipItem->id)->first();
        $supportTask = DB::table('project_tasks')->where('project_id', $supportProject->id)->first();
        $membershipTask = DB::table('project_tasks')->where('project_id', $membershipProject->id)->first();
        $this->assertDatabaseHas('proposals', ['id'=>$quote->id, 'status'=>'accepted']);
        $this->assertSame('Activate '.$supportItem->title, $supportTask->title);
        $this->assertNotNull($membershipTask);
        $this->assertSame('Client Final Campaign Task', $task->title);
        $this->assertStringContainsString('Final instructions agreed in the quote.', $task->description);
        $this->assertStringContainsString('2 manual campaign checks', $task->description);
        $this->assertNull($task->recurrence_id);
        $this->assertSame(0, DB::table('project_task_recurrences')->where('project_id', $project->id)->count());
        $this->get('/quote_view?id='.$quote->id)->assertOk()->assertSee('Locked')->assertDontSee('> Edit<', false);

        $this->post('/saved_quotes', ['action'=>'onboard_quote', 'proposal_id'=>$quote->id])->assertRedirectContains('/client?id=');
        $this->assertGreaterThanOrEqual(3, DB::table('projects')->where('source_proposal_id', $quote->id)->count());
        $this->assertGreaterThanOrEqual(3, DB::table('project_tasks')->whereIn('project_id', [$project->id,$supportProject->id,$membershipProject->id])->count());
    }

    public function test_support_contract_package_uses_runtime_service_pricing_and_term(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $setupPackage=DB::table('packages')->where('package_type','quote_setup')->where('tier','basic')->first();
        $setupItem=DB::table('package_items')->where('package_id',$setupPackage->id)->where('included',1)->first();
        $supportPackage=DB::table('packages')->where('package_type','support_contract')->where('tier','starter')->first();
        $supportItem=DB::table('package_items')->where('package_id',$supportPackage->id)->where('included',1)->first();
        $expectedSupportMonthly=(float)DB::table('package_items')->where('package_id',$supportPackage->id)->where('included',1)->sum(DB::raw('quantity * unit_price'));
        $answers=[];foreach(config('quote_studio.assessment') as $question){$answers[$question['key']]=$question['options'][0]['value'];}

        $this->post('/quote_studio',[
            'action'=>'save_quote','business_name'=>'Runtime Contract Client','contact_name'=>'Contract Tester','contact_email'=>'contract@example.test','locale'=>'en',
            'assessment_json'=>json_encode($answers),'selected_tier'=>'basic','items_json'=>json_encode([[
                'service_id'=>$setupItem->service_id,'list_price'=>(float)$setupItem->unit_price,'discount_percent'=>0,'custom_price'=>null,'description'=>$setupItem->description,
            ]]),'support_plan'=>'none','support_package_id'=>$supportPackage->id,'support_items_json'=>json_encode([[
                'service_id'=>$supportItem->service_id,'list_price'=>(float)$supportItem->unit_price,'discount_percent'=>0,'custom_price'=>500,'description'=>$supportItem->description,
            ]]),'membership_term'=>12,'tax_percent'=>13,'validity_days'=>7,
        ])->assertSessionHas('success');

        $quote=DB::table('proposals')->where('business_name','Runtime Contract Client')->first();
        $this->assertSame((int)$supportPackage->id,(int)$quote->support_package_id);
        $this->assertSame(500.0,(float)$quote->membership_monthly_price);
        $this->assertSame(6000.0,(float)$quote->membership_amount);
        $this->assertDatabaseHas('proposal_items',['proposal_id'=>$quote->id,'item_type'=>'support_contract','service_id'=>$supportItem->service_id,'quantity'=>12,'total'=>6000]);
        $this->get('/quote_settings')->assertOk()->assertSee('One-time setup packages')->assertSee('Support contract packages')
            ->assertSee('Package total')->assertSee('Monthly package total')->assertSee(money($expectedSupportMonthly).'/mo', false);
        $this->get('/quote_view?id='.$quote->id)->assertOk()
            ->assertSee('One-time setup services')
            ->assertSee('Technical support')
            ->assertSee('Support contract services')
            ->assertSee('Monthly subtotal')
            ->assertSee('Contract value')
            ->assertSee('$6,000.00', false);
    }

    public function test_admin_can_update_service_catalog_price_from_quote_settings(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $service = DB::table('services')->where('name', 'SEO')->first();

        $this->post('/quote_settings', [
            'action'=>'save_quote_service', 'service_id'=>$service->id, 'category_id'=>$service->category_id,
            'name'=>$service->name, 'name_ar'=>$service->name_ar, 'name_he'=>$service->name_he,
            'description'=>$service->description, 'description_ar'=>$service->description_ar, 'description_he'=>$service->description_he,
            'pricing_type'=>$service->pricing_type, 'default_price'=>'1875.50', 'cost_estimate'=>$service->cost_estimate,
            'estimated_hours'=>$service->estimated_hours, 'quote_sort'=>$service->quote_sort,
            'taxable'=>1, 'active'=>1, 'quote_enabled'=>1,
        ])->assertRedirect('/quote_settings')->assertSessionHas('success');

        $this->assertDatabaseHas('services', ['id'=>$service->id, 'default_price'=>1875.50]);
        $this->get('/quote_settings')->assertOk()->assertSee('$1,875.50')->assertSee('data.service_id=data.id', false);
    }

    public function test_admin_can_delete_a_saved_quote_and_its_dependent_records(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $proposalId = DB::table('proposals')->insertGetId([
            'proposal_number'=>'Q-DELETE-TEST', 'builder_version'=>1, 'client_id'=>null, 'package_id'=>null,
            'title'=>'Delete test quote', 'business_name'=>'Delete Test Business', 'contact_name'=>'Delete Test Contact',
            'contact_email'=>'delete@example.test', 'subtotal'=>500, 'total'=>500, 'status'=>'draft',
            'share_token'=>'delete-test-token', 'created_at'=>now()->toIso8601String(), 'updated_at'=>now()->toIso8601String(),
        ]);
        DB::table('proposal_items')->insert([
            'proposal_id'=>$proposalId, 'service_id'=>null, 'description'=>'Temporary quote item',
            'quantity'=>1, 'unit_price'=>500, 'total'=>500,
        ]);
        DB::table('proposal_deliveries')->insert([
            'proposal_id'=>$proposalId, 'recipient'=>'delete@example.test', 'channel'=>'email',
            'locale'=>'en', 'status'=>'recorded', 'sent_at'=>now()->toIso8601String(),
        ]);

        $this->get('/saved_quotes')->assertOk()->assertSee('Delete Test Business')->assertSee('Delete quote');
        $this->post('/saved_quotes', ['action'=>'delete_quote', 'proposal_id'=>$proposalId])
            ->assertRedirect('/saved_quotes')->assertSessionHas('success');

        $this->assertDatabaseMissing('proposals', ['id'=>$proposalId]);
        $this->assertDatabaseMissing('proposal_items', ['proposal_id'=>$proposalId]);
        $this->assertDatabaseMissing('proposal_deliveries', ['proposal_id'=>$proposalId]);
    }

    public function test_accepted_quote_cannot_be_deleted_from_ui_or_backend(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $proposalId = DB::table('proposals')->insertGetId([
            'proposal_number'=>'Q-ACCEPTED-PROTECTED', 'builder_version'=>1, 'client_id'=>null, 'package_id'=>null,
            'title'=>'Protected accepted quote', 'business_name'=>'Protected Client', 'contact_name'=>'Protected Contact',
            'contact_email'=>'protected@example.test', 'subtotal'=>750, 'total'=>750, 'status'=>'accepted',
            'share_token'=>'accepted-protected-token', 'created_at'=>now()->toIso8601String(), 'updated_at'=>now()->toIso8601String(),
        ]);

        $response = $this->get('/saved_quotes')->assertOk()->assertSee('Q-ACCEPTED-PROTECTED')
            ->assertDontSee('<th>Deliveries</th>', false)
            ->assertDontSee('Create revision')
            ->assertDontSee('Revision 1')
            ->assertDontSee('value="duplicate_quote"', false);
        preg_match('/Q-ACCEPTED-PROTECTED.*?<\/tr>/s', $response->getContent(), $acceptedRow);
        $this->assertNotEmpty($acceptedRow);
        $this->assertStringContainsString('Accepted quote is locked', $acceptedRow[0]);
        $this->assertStringNotContainsString('value="delete_quote"', $acceptedRow[0]);

        $this->post('/saved_quotes', ['action'=>'delete_quote', 'proposal_id'=>$proposalId])
            ->assertRedirect('/saved_quotes')->assertSessionHas('danger', 'Accepted and onboarded quotes cannot be deleted. Create a new revision if another quote is required.');
        $this->assertDatabaseHas('proposals', ['id'=>$proposalId, 'status'=>'accepted']);
    }

    public function test_clients_list_uses_accepted_quote_package_and_monthly_value_without_legacy_subscription(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $businessId = DB::table('businesses')->insertGetId([
            'name'=>'XYZ Quote Fallback', 'industry'=>'Professional Services', 'created_at'=>now()->toIso8601String(),
        ]);
        $clientId = DB::table('clients')->insertGetId([
            'business_id'=>$businessId, 'account_manager_id'=>null, 'name'=>'XYZ Contact',
            'email'=>'xyz-fallback@example.test', 'status'=>'active', 'health'=>'healthy',
            'joined_at'=>now()->toDateString(), 'created_at'=>now()->toIso8601String(),
        ]);
        $package = DB::table('packages')->where('package_type', 'quote_setup')->where('tier', 'basic')->first();
        DB::table('proposals')->insert([
            'proposal_number'=>'Q-CLIENT-FALLBACK', 'builder_version'=>1, 'client_id'=>$clientId,
            'package_id'=>$package->id, 'title'=>'XYZ accepted quote', 'business_name'=>'XYZ Quote Fallback',
            'contact_name'=>'XYZ Contact', 'contact_email'=>'xyz-fallback@example.test', 'selected_tier'=>'basic',
            'membership_plan'=>'growth', 'membership_name'=>'Social Media Growth',
            'membership_duration_months'=>12, 'membership_monthly_price'=>0, 'membership_amount'=>17940,
            'subtotal'=>17940, 'total'=>17940, 'status'=>'accepted', 'share_token'=>'xyz-fallback-token',
            'created_at'=>now()->toIso8601String(), 'updated_at'=>now()->toIso8601String(),
        ]);

        $row = collect(app(\App\Services\AgencyService::class)->clients())->firstWhere('id', $clientId);
        $this->assertSame($package->name, $row['package_name']);
        $this->assertSame(1495.0, (float)$row['monthly_price']);
        $this->assertSame(17940.0, (float)$row['accepted_quote_value']);

        $this->get('/clients')->assertOk()
            ->assertSee('XYZ Quote Fallback')->assertSee($package->name)->assertSee('$17,940.00')->assertSee('$1,495.00')
            ->assertSee('Accepted quote')->assertSee('Monthly recurring')
            ->assertSee('Email')->assertSee('Phone')->assertSee('Onboarded')
            ->assertSee('xyz-fallback@example.test')
            ->assertDontSee('Unpaid invoices')->assertDontSee('New Client')
            ->assertDontSee('clients#modal-add')->assertDontSee('name="action" value="create_client"', false)
            ->assertDontSee('<th>Health</th>', false)
            ->assertDontSee('<th>Account manager</th>', false);

        $this->get('/clients?q=XYZ+Quote&package_id='.$package->id.'&status=active&min_monthly=1495&max_monthly=1495')
            ->assertOk()->assertSee('XYZ Quote Fallback')->assertSee('$1,495.00')
            ->assertSee('name="package_id"', false)->assertSee('name="min_monthly"', false)
            ->assertSee('name="max_monthly"', false);

        $today = now()->toDateString();
        $this->get('/saved_quotes?status=accepted&package_id='.$package->id.'&date_from='.$today.'&date_to='.$today)
            ->assertOk()->assertSee('Q-CLIENT-FALLBACK')->assertSee('name="package_id"', false)
            ->assertSee('name="date_from"', false)->assertSee('name="date_to"', false);
    }

    public function test_super_admin_can_use_simplified_project_and_task_workflow_with_filters_and_editing(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $clientId = (int) DB::table('clients')->where('status', 'active')->orderBy('id')->value('id');
        $employeeId = (int) DB::table('employees')->where('status', 'active')->orderBy('id')->value('id');

        $this->post('/projects', [
            'action'=>'create_project', 'client_id'=>$clientId, 'name'=>'Simplified Project QA',
            'project_type'=>'Website', 'start_date'=>'2026-09-01', 'notes'=>'Created from the reduced project form.',
        ])->assertRedirect('/projects')->assertSessionHas('success');

        $projectId = (int) DB::table('projects')->where('name', 'Simplified Project QA')->value('id');
        $this->assertDatabaseHas('projects', [
            'id'=>$projectId, 'client_id'=>$clientId, 'package_id'=>null, 'manager_id'=>null,
            'deadline'=>null, 'budget'=>0, 'estimated_hours'=>0, 'priority'=>'medium', 'status'=>'planning',
        ]);

        $this->post('/tasks', [
            'action'=>'create_task', 'project_id'=>$projectId, 'assigned_employee_id'=>$employeeId,
            'title'=>'Simplified Task QA', 'description'=>'Created without estimated hours.',
            'due_date'=>'2026-09-12', 'priority'=>'medium',
        ])->assertRedirect('/tasks')->assertSessionHas('success');

        $taskId = (int) DB::table('project_tasks')->where('project_id', $projectId)->where('title', 'Simplified Task QA')->value('id');
        $this->assertDatabaseHas('project_tasks', ['id'=>$taskId, 'estimated_hours'=>0, 'status'=>'todo']);

        $this->get('/projects?client_id='.$clientId.'&project_type=Website&status=planning')
            ->assertOk()->assertSee('Simplified Project QA')->assertSee('name="client_id"', false)
            ->assertSee('name="project_type"', false)->assertSee('name="status"', false)
            ->assertSee('name="return_url"', false)->assertSee('data-preserve-table-state', false)
            ->assertSee('tasks?project_id='.$projectId, false)->assertSee('Edit project')
            ->assertDontSee('name="package_id"', false)->assertDontSee('name="manager_id"', false)
            ->assertDontSee('name="budget"', false)->assertDontSee('name="estimated_hours"', false)
            ->assertDontSee('name="deadline"', false);

        $this->get('/tasks?project_id='.$projectId.'&assigned_employee_id='.$employeeId.'&status=todo')
            ->assertOk()->assertSee('Simplified Task QA')->assertSee('name="project_id"', false)
            ->assertSee('name="assigned_employee_id"', false)->assertSee('name="status"', false)
            ->assertSee('name="return_url"', false)->assertSee('data-preserve-table-state', false)
            ->assertSee('Duplicate')->assertSee('Logs')->assertSee('Add progress note')->assertSee('Delete')
            ->assertSee('Edit task')->assertDontSee('name="estimated_hours"', false);

        $projectReturnUrl='/projects?client_id='.$clientId.'&project_type=Website&status=planning&table_search=simplified&table_sort=0&table_direction=desc';
        $this->post('/projects', [
            'action'=>'update_project', 'project_id'=>$projectId, 'client_id'=>$clientId,
            'name'=>'Edited Project QA', 'project_type'=>'Branding', 'start_date'=>'2026-09-02',
            'status'=>'active', 'notes'=>'Edited by Super Admin.', 'return_url'=>$projectReturnUrl,
        ])->assertRedirect($projectReturnUrl)->assertSessionHas('success');
        $this->assertDatabaseHas('projects', ['id'=>$projectId, 'name'=>'Edited Project QA', 'project_type'=>'Branding', 'status'=>'active']);

        $taskReturnUrl='/tasks?project_id='.$projectId.'&assigned_employee_id='.$employeeId.'&status=todo&table_search=simplified&table_page=2';
        $this->post('/tasks', [
            'action'=>'update_task', 'task_id'=>$taskId, 'project_id'=>$projectId,
            'assigned_employee_id'=>$employeeId, 'title'=>'Edited Task QA', 'description'=>'Edited by Super Admin.',
            'due_date'=>'2026-09-15', 'priority'=>'high', 'status'=>'in_progress', 'return_url'=>$taskReturnUrl,
        ])->assertRedirect($taskReturnUrl)->assertSessionHas('success');
        $this->assertDatabaseHas('project_tasks', [
            'id'=>$taskId, 'title'=>'Edited Task QA', 'priority'=>'high', 'status'=>'in_progress', 'estimated_hours'=>0,
        ]);

        $this->post('/tasks', ['action'=>'add_task_note', 'task_id'=>$taskId, 'note'=>'Waiting for the client approval before publishing.', 'return_url'=>$taskReturnUrl])
            ->assertRedirect($taskReturnUrl)->assertSessionHas('success');
        $this->post('/tasks', ['action'=>'task_status', 'id'=>$taskId, 'status'=>'review', 'return_url'=>$taskReturnUrl])
            ->assertRedirect($taskReturnUrl)->assertSessionHas('success');
        $this->post('/tasks', ['action'=>'duplicate_task', 'task_id'=>$taskId, 'return_url'=>$taskReturnUrl])
            ->assertRedirect($taskReturnUrl)->assertSessionHas('success');

        $copyId = (int) DB::table('project_tasks')->where('title', 'Edited Task QA (Copy)')->value('id');
        $this->assertDatabaseHas('project_tasks', [
            'id'=>$copyId, 'project_id'=>$projectId, 'client_id'=>$clientId, 'status'=>'todo', 'actual_hours'=>0,
        ]);
        $this->assertDatabaseHas('audit_logs', ['entity_type'=>'task', 'entity_id'=>$taskId, 'action'=>'note_added']);
        $this->assertDatabaseHas('audit_logs', ['entity_type'=>'task', 'entity_id'=>$taskId, 'action'=>'status_changed']);
        $this->assertDatabaseHas('audit_logs', ['entity_type'=>'task', 'entity_id'=>$taskId, 'action'=>'duplicated']);
        $this->get('/tasks?project_id='.$projectId)->assertOk()
            ->assertSee('Edited Task QA (Copy)')->assertSee('Waiting for the client approval before publishing.')
            ->assertSee('Status Changed')->assertSee('TASK ACTIVITY TRAIL');

        $this->post('/tasks', ['action'=>'delete_task', 'task_id'=>$copyId])
            ->assertRedirect('/tasks')->assertSessionHas('success');
        $this->assertDatabaseMissing('project_tasks', ['id'=>$copyId]);
        $this->assertDatabaseHas('audit_logs', ['entity_type'=>'task', 'entity_id'=>$copyId, 'action'=>'deleted']);
    }

    public function test_multi_assignee_tasks_and_staff_visibility_are_enforced_across_delivery_views(): void
    {
        $superAdmin = User::query()->where('email', 'admin@agencyos.local')->firstOrFail();
        $this->actingAs($superAdmin);
        $clientId = (int) DB::table('clients')->where('status', 'active')->orderBy('id')->value('id');
        $employeeIds = DB::table('employees')->where('status', 'active')->whereNull('user_id')->orderBy('id')->limit(2)->pluck('id')->map(fn ($id): int => (int)$id)->all();
        $this->assertCount(2, $employeeIds);

        $this->post('/projects', [
            'action'=>'create_project', 'client_id'=>$clientId, 'name'=>'Multi Assignment Project QA',
            'project_type'=>'Website', 'start_date'=>now()->toDateString(),
        ])->assertRedirect('/projects')->assertSessionHas('success');
        $projectId = (int) DB::table('projects')->where('name', 'Multi Assignment Project QA')->value('id');

        $this->post('/tasks', [
            'action'=>'create_task', 'project_id'=>$projectId, 'assigned_employee_ids'=>$employeeIds,
            'title'=>'Shared Multi Person Task QA', 'due_date'=>now()->addDays(3)->toDateString(), 'priority'=>'high',
        ])->assertRedirect('/tasks')->assertSessionHas('success');
        $sharedTaskId = (int) DB::table('project_tasks')->where('title', 'Shared Multi Person Task QA')->value('id');
        foreach ($employeeIds as $employeeId) {
            $this->assertDatabaseHas('project_task_assignees', ['task_id'=>$sharedTaskId, 'employee_id'=>$employeeId]);
        }
        $this->get('/tasks?project_id='.$projectId)->assertOk()->assertSee('Shared Multi Person Task QA')
            ->assertSee('name="assigned_employee_ids[]"', false)->assertSee('Assignees');

        $this->post('/tasks', [
            'action'=>'create_task', 'project_id'=>$projectId, 'assigned_employee_ids'=>[$employeeIds[1]],
            'title'=>'Other Employee Exclusive Task QA', 'due_date'=>now()->addDays(3)->toDateString(), 'priority'=>'medium',
        ])->assertRedirect('/tasks')->assertSessionHas('success');
        $exclusiveTaskId = (int) DB::table('project_tasks')->where('title', 'Other Employee Exclusive Task QA')->value('id');

        $roleId = (int) DB::table('roles')->where('slug', 'project_manager')->value('id');
        $staffUserId = (int) DB::table('users')->insertGetId([
            'role_id'=>$roleId, 'name'=>'Scoped Staff QA', 'email'=>'scoped-staff-qa@example.test',
            'password_hash'=>password_hash('Staff@360!', PASSWORD_DEFAULT), 'status'=>'active',
            'created_at'=>date('c'), 'updated_at'=>date('c'),
        ]);
        DB::table('employees')->where('id', $employeeIds[0])->update(['user_id'=>$staffUserId]);
        $staffUser = User::query()->findOrFail($staffUserId);
        $this->actingAs($staffUser);

        $this->get('/projects')->assertOk()->assertSee('Multi Assignment Project QA')
            ->assertDontSee('New Project')->assertDontSee('name="action" value="create_project"', false)
            ->assertDontSee('Delete project');
        $this->get('/tasks?project_id='.$projectId)->assertOk()->assertSee('Shared Multi Person Task QA')
            ->assertDontSee('Other Employee Exclusive Task QA')->assertSee('New Task')->assertDontSee('Delete task');

        $this->post('/projects', [
            'action'=>'create_project', 'client_id'=>$clientId, 'name'=>'Forbidden Staff Project QA',
            'project_type'=>'Website', 'start_date'=>now()->toDateString(),
        ])->assertRedirect('/projects')->assertSessionHas('danger');
        $this->assertDatabaseMissing('projects', ['name'=>'Forbidden Staff Project QA']);

        $this->post('/tasks', [
            'action'=>'create_task', 'project_id'=>$projectId, 'assigned_employee_ids'=>[$employeeIds[1]],
            'title'=>'Staff Created Shared Task QA', 'due_date'=>now()->addDays(4)->toDateString(), 'priority'=>'medium',
        ])->assertRedirect('/tasks')->assertSessionHas('success');
        $staffTaskId = (int) DB::table('project_tasks')->where('title', 'Staff Created Shared Task QA')->value('id');
        $this->assertDatabaseHas('project_task_assignees', ['task_id'=>$staffTaskId, 'employee_id'=>$employeeIds[0]]);
        $this->assertDatabaseHas('project_task_assignees', ['task_id'=>$staffTaskId, 'employee_id'=>$employeeIds[1]]);

        $this->post('/tasks', ['action'=>'delete_task', 'task_id'=>$sharedTaskId])
            ->assertRedirect('/tasks')->assertSessionHas('danger');
        $this->assertDatabaseHas('project_tasks', ['id'=>$sharedTaskId]);

        $month = now()->format('Y-m');
        $this->get('/calendar?month='.$month)->assertOk()->assertSee('MY DELIVERY SCHEDULE')
            ->assertSee('Shared Multi Person Task QA')->assertDontSee('Other Employee Exclusive Task QA')
            ->assertDontSee('All employees');
        $this->get('/dashboard')->assertOk()->assertSee('MY DELIVERY WORKSPACE')->assertSee('My assigned workload')
            ->assertSee('My open tasks')->assertSee('Overdue tasks')->assertSee('My task flow')
            ->assertDontSee('Accepted quote value');
        $this->get('/reports')->assertOk()->assertSee('MY DELIVERY INTELLIGENCE')
            ->assertSee('My task workload')->assertDontSee('Package performance');

        $adminRoleId = (int) DB::table('roles')->where('slug', 'admin')->value('id');
        $adminUserId = (int) DB::table('users')->insertGetId([
            'role_id'=>$adminRoleId, 'name'=>'Administrator QA', 'email'=>'administrator-qa@example.test',
            'password_hash'=>password_hash('Admin@360!', PASSWORD_DEFAULT), 'status'=>'active',
            'created_at'=>date('c'), 'updated_at'=>date('c'),
        ]);
        $this->actingAs(User::query()->findOrFail($adminUserId));
        $this->get('/tasks?project_id='.$projectId)->assertOk()->assertSee('Shared Multi Person Task QA')->assertSee('Other Employee Exclusive Task QA');
        $this->get('/calendar?month='.$month)->assertOk()->assertSee('SHARED SCHEDULE')->assertSee('Other Employee Exclusive Task QA');

        $this->actingAs($superAdmin);
        $this->post('/projects', ['action'=>'delete_project', 'project_id'=>$projectId])
            ->assertRedirect('/projects')->assertSessionHas('success');
        $this->assertDatabaseMissing('projects', ['id'=>$projectId]);
        $this->assertDatabaseMissing('project_tasks', ['id'=>$exclusiveTaskId]);
        $this->assertDatabaseHas('audit_logs', ['entity_type'=>'project', 'entity_id'=>$projectId, 'action'=>'deleted']);
    }

    public function test_calendar_events_are_readable_and_open_full_detail_modals(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $task=DB::table('project_tasks')->orderBy('id')->first();
        DB::table('project_tasks')->where('id',$task->id)->update([
            'title'=>'Calendar Detail QA Task','description'=>'Full task instructions shown in the calendar modal.',
            'due_date'=>'2026-09-09','status'=>'todo','priority'=>'high',
        ]);

        $this->get('/calendar?month=2026-09')->assertOk()
            ->assertSee('Calendar Detail QA Task')
            ->assertSee('data-calendar-event',false)
            ->assertSee('data-target="#calendar-event-task-'.$task->id.'"',false)
            ->assertSee('id="calendar-event-task-'.$task->id.'"',false)
            ->assertSee('Full task instructions shown in the calendar modal.')
            ->assertSee('Open client')->assertSee('Open project tasks')
            ->assertSee('Priority')->assertSee('Assigned to');
    }

    public function test_super_admin_can_edit_role_details_and_permissions(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $roleId = DB::table('roles')->insertGetId([
            'name'=>'Temporary Editor', 'slug'=>'temporary_editor', 'description'=>'Before edit', 'created_at'=>now()->toDateTimeString(),
        ]);
        $dashboardPermission = DB::table('permissions')->where('slug', 'dashboard.access')->value('id');
        $leadsPermission = DB::table('permissions')->where('slug', 'leads.access')->value('id');
        DB::table('role_permissions')->insert(['role_id'=>$roleId, 'permission_id'=>$dashboardPermission]);

        $this->post('/settings', [
            'action'=>'update_role', 'role_id'=>$roleId, 'name'=>'Client Success Manager',
            'slug'=>'client_success_manager', 'description'=>'Owns client retention and account health.',
            'permission_ids'=>[$leadsPermission],
        ])->assertRedirect('/settings')->assertSessionHas('success');

        $this->assertDatabaseHas('roles', [
            'id'=>$roleId, 'name'=>'Client Success Manager', 'slug'=>'client_success_manager',
            'description'=>'Owns client retention and account health.',
        ]);
        $this->assertDatabaseHas('role_permissions', ['role_id'=>$roleId, 'permission_id'=>$leadsPermission]);
        $this->assertDatabaseMissing('role_permissions', ['role_id'=>$roleId, 'permission_id'=>$dashboardPermission]);
        $this->assertDatabaseHas('audit_logs', ['action'=>'updated', 'entity_type'=>'role', 'entity_id'=>$roleId]);

        $this->post('/settings', [
            'action'=>'update_role', 'role_id'=>$roleId, 'name'=>'Client Success Manager',
            'slug'=>'sales', 'description'=>'Duplicate key attempt', 'permission_ids'=>[$leadsPermission],
        ])->assertRedirect('/settings')->assertSessionHas('danger');

        $this->assertDatabaseHas('roles', ['id'=>$roleId, 'slug'=>'client_success_manager']);
    }

    public function test_admin_system_role_cannot_be_edited(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        $permissionCount = DB::table('role_permissions')->where('role_id', $adminRole->id)->count();
        $leadsPermission = DB::table('permissions')->where('slug', 'leads.access')->value('id');

        $this->post('/settings', [
            'action'=>'update_role', 'role_id'=>$adminRole->id, 'name'=>'Changed Admin',
            'slug'=>'changed_admin', 'description'=>'This update must be rejected.',
            'permission_ids'=>[$leadsPermission],
        ])->assertRedirect('/settings')->assertSessionHas('danger');

        $this->assertDatabaseHas('roles', [
            'id'=>$adminRole->id, 'name'=>$adminRole->name, 'slug'=>'admin', 'description'=>$adminRole->description,
        ]);
        $this->assertSame($permissionCount, DB::table('role_permissions')->where('role_id', $adminRole->id)->count());
    }

    public function test_super_admin_can_edit_service_pricing_from_settings(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $service = DB::table('services')->orderBy('id')->first();
        $categoryId = DB::table('service_categories')->where('active', true)->orderBy('id')->value('id');

        $this->post('/settings', [
            'action'=>'update_service', 'service_id'=>$service->id, 'name'=>'QA Updated Service',
            'category_id'=>$categoryId, 'pricing_type'=>'monthly', 'default_price'=>'2195.50',
            'cost_estimate'=>'875.25', 'estimated_hours'=>'16.5', 'description'=>'Updated from service pricing controls.',
            'active'=>'0', 'taxable'=>'0',
        ])->assertRedirect('/settings')->assertSessionHas('success');

        $this->assertDatabaseHas('services', [
            'id'=>$service->id, 'name'=>'QA Updated Service', 'category_id'=>$categoryId,
            'pricing_type'=>'monthly', 'default_price'=>2195.50, 'cost_estimate'=>875.25,
            'estimated_hours'=>16.5, 'active'=>0, 'taxable'=>0,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action'=>'updated', 'entity_type'=>'service', 'entity_id'=>$service->id]);

        $this->get('/settings')
            ->assertOk()
            ->assertDontSee('Service pricing controls')
            ->assertDontSee('Edit QA Updated Service');
    }

    public function test_sales_pipeline_enforces_relationship_workflow_and_synchronizes_records(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $serviceId = DB::table('services')->where('active', true)->orderBy('id')->value('id');
        $serviceName = DB::table('services')->where('id', $serviceId)->value('name');
        $ownerId = DB::table('employees')->where('status', 'active')->orderBy('id')->value('id');

        $this->post('/leads', [
            'action'=>'create_lead', 'first_name'=>'Pipeline', 'last_name'=>'QA',
            'company_name'=>'Pipeline Workflow Verification', 'email'=>'pipeline-workflow@example.test',
            'company_size_range'=>'11_50', 'years_in_business_range'=>'3_5',
            'business_stage'=>'existing_business', 'budget_range'=>'10k_25k',
            'service_ids'=>[$serviceId], 'assigned_employee_id'=>$ownerId,
        ])->assertRedirect('/leads')->assertSessionHas('success');

        $leadId = DB::table('leads')->where('email', 'pipeline-workflow@example.test')->value('id');
        $opportunityId = DB::table('opportunities')->where('lead_id', $leadId)->value('id');
        $qualifiedStageId = DB::table('pipeline_stages')->where('slug', 'qualified')->value('id');
        $discoveryStageId = DB::table('pipeline_stages')->where('slug', 'discovery')->value('id');
        $proposalStageId = DB::table('pipeline_stages')->where('slug', 'proposal')->value('id');
        $negotiationStageId = DB::table('pipeline_stages')->where('slug', 'negotiation')->value('id');
        $wonStageId = DB::table('pipeline_stages')->where('slug', 'won')->value('id');
        $lostStageId = DB::table('pipeline_stages')->where('slug', 'lost')->value('id');

        $this->get('/pipeline')
            ->assertOk()->assertSee('New lead')->assertSee('Opportunity value')
            ->assertSee('Pipeline Workflow Verification')->assertSee('Activity history');

        $this->post('/pipeline', [
            'action'=>'update_opportunity', 'opportunity_id'=>$opportunityId,
            'title'=>'Pipeline QA retainer', 'estimated_value'=>'22500', 'owner_id'=>$ownerId,
            'expected_close_date'=>'2026-09-30', 'service_ids'=>[$serviceId],
            'next_action'=>'Confirm decision committee', 'notes'=>'Pipeline details verified.',
        ])->assertRedirect('/pipeline')->assertSessionHas('success');
        $this->assertDatabaseHas('opportunities', [
            'id'=>$opportunityId, 'title'=>'Pipeline QA retainer', 'estimated_value'=>22500,
            'owner_id'=>$ownerId, 'services'=>$serviceName, 'next_action'=>'Confirm decision committee',
        ]);
        $this->assertDatabaseHas('leads', ['id'=>$leadId, 'assigned_employee_id'=>$ownerId, 'services_interested'=>$serviceName]);
        $this->assertDatabaseHas('audit_logs', ['action'=>'updated', 'entity_type'=>'opportunity', 'entity_id'=>$opportunityId]);

        $this->post('/pipeline', ['action'=>'move_opportunity','opportunity_id'=>$opportunityId,'stage_id'=>$qualifiedStageId])
            ->assertRedirect('/pipeline')->assertSessionHas('success');
        $this->assertDatabaseHas('leads', ['id'=>$leadId, 'status'=>'qualified']);

        $this->post('/pipeline', ['action'=>'move_opportunity','opportunity_id'=>$opportunityId,'stage_id'=>$discoveryStageId])
            ->assertRedirect('/pipeline')->assertSessionHas('danger');
        $this->assertDatabaseHas('opportunities', ['id'=>$opportunityId, 'stage_id'=>$qualifiedStageId]);

        $this->post('/discovery', [
            'action'=>'create_consultation', 'lead_id'=>$leadId, 'main_goal'=>'Validate pipeline workflow',
            'current_problems'=>['Low leads'], 'desired_outcomes'=>['More leads'], 'notes'=>'Pipeline-linked discovery.',
        ])->assertRedirect('/discovery')->assertSessionHas('success');
        $this->assertDatabaseHas('opportunities', ['id'=>$opportunityId, 'stage_id'=>$discoveryStageId]);

        $this->post('/pipeline', ['action'=>'move_opportunity','opportunity_id'=>$opportunityId,'stage_id'=>$proposalStageId])
            ->assertRedirect('/pipeline')->assertSessionHas('danger');
        $this->post('/proposals', [
            'action'=>'create_proposal', 'opportunity_id'=>$opportunityId,
            'title'=>'Pipeline verification proposal', 'description'=>'Validated service scope',
            'amount'=>'22500', 'valid_until'=>'2026-10-15',
        ])->assertRedirect('/proposals')->assertSessionHas('success');
        $this->assertDatabaseHas('proposals', ['opportunity_id'=>$opportunityId, 'lead_id'=>$leadId, 'client_id'=>null]);

        $this->post('/pipeline', ['action'=>'move_opportunity','opportunity_id'=>$opportunityId,'stage_id'=>$proposalStageId])
            ->assertRedirect('/pipeline')->assertSessionHas('success');
        $this->assertDatabaseHas('proposals', ['opportunity_id'=>$opportunityId, 'status'=>'sent']);
        $this->post('/pipeline', ['action'=>'move_opportunity','opportunity_id'=>$opportunityId,'stage_id'=>$negotiationStageId])
            ->assertRedirect('/pipeline')->assertSessionHas('success');

        $this->post('/pipeline', ['action'=>'move_opportunity','opportunity_id'=>$opportunityId,'stage_id'=>$lostStageId,'lost_reason'=>''])
            ->assertRedirect('/pipeline')->assertSessionHas('danger');
        $this->post('/pipeline', ['action'=>'move_opportunity','opportunity_id'=>$opportunityId,'stage_id'=>$lostStageId,'lost_reason'=>'Decision postponed beyond the buying window.'])
            ->assertRedirect('/pipeline')->assertSessionHas('success');
        $this->assertDatabaseHas('opportunities', ['id'=>$opportunityId, 'stage_id'=>$lostStageId, 'probability'=>0, 'lost_reason'=>'Decision postponed beyond the buying window.', 'previous_stage_id'=>$negotiationStageId]);
        $this->assertDatabaseHas('leads', ['id'=>$leadId, 'status'=>'lost', 'lost_reason'=>'Decision postponed beyond the buying window.']);

        $this->post('/pipeline', ['action'=>'move_opportunity','opportunity_id'=>$opportunityId,'stage_id'=>$negotiationStageId])
            ->assertRedirect('/pipeline')->assertSessionHas('success');
        $this->assertDatabaseHas('leads', ['id'=>$leadId, 'status'=>'negotiation', 'lost_reason'=>null, 'lost_at'=>null]);

        $this->post('/pipeline', ['action'=>'move_opportunity','opportunity_id'=>$opportunityId,'stage_id'=>$wonStageId])
            ->assertRedirect()->assertSessionHas('success');
        $clientId = DB::table('leads')->where('id', $leadId)->value('converted_client_id');
        $this->assertNotNull($clientId);
        $this->assertDatabaseHas('leads', ['id'=>$leadId, 'status'=>'converted', 'converted_client_id'=>$clientId]);
        $this->assertDatabaseHas('opportunities', ['id'=>$opportunityId, 'stage_id'=>$wonStageId, 'client_id'=>$clientId, 'probability'=>100]);
        $this->assertDatabaseHas('contacts', ['client_id'=>$clientId, 'email'=>'pipeline-workflow@example.test']);

        $this->get('/pipeline?owner_id='.$ownerId.'&stage_id='.$wonStageId.'&min_value=20000&max_value=25000')
            ->assertOk()->assertSee('Pipeline Workflow Verification')->assertSee('Showing 1 matching opportunities');
    }

    public function test_super_admin_can_create_reorder_and_edit_pipeline_stages(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());

        $this->post('/settings', [
            'action'=>'create_pipeline_stage', 'name'=>'Decision Pending', 'position'=>6,
            'win_probability'=>78, 'color'=>'warning',
        ])->assertRedirect('/settings')->assertSessionHas('success');
        $stageId = DB::table('pipeline_stages')->where('slug', 'decision_pending')->value('id');
        $this->assertNotNull($stageId);
        $this->assertDatabaseHas('pipeline_stages', ['id'=>$stageId, 'name'=>'Decision Pending', 'position'=>6, 'win_probability'=>78, 'is_closed'=>0]);

        $this->post('/settings', [
            'action'=>'update_pipeline_stage', 'stage_id'=>$stageId, 'name'=>'Decision Review',
            'position'=>5, 'win_probability'=>82, 'color'=>'purple',
        ])->assertRedirect('/settings')->assertSessionHas('success');
        $this->assertDatabaseHas('pipeline_stages', ['id'=>$stageId, 'name'=>'Decision Review', 'position'=>5, 'win_probability'=>82, 'color'=>'purple']);
        $this->assertDatabaseHas('audit_logs', ['action'=>'updated', 'entity_type'=>'pipeline_stage', 'entity_id'=>$stageId]);

        $wonStage = DB::table('pipeline_stages')->where('slug', 'won')->first();
        $this->post('/settings', [
            'action'=>'update_pipeline_stage', 'stage_id'=>$wonStage->id, 'name'=>'Won Business',
            'position'=>$wonStage->position, 'win_probability'=>42, 'color'=>'success',
        ])->assertRedirect('/settings')->assertSessionHas('success');
        $this->assertDatabaseHas('pipeline_stages', ['id'=>$wonStage->id, 'name'=>'Won Business', 'win_probability'=>100, 'is_closed'=>1]);

        $this->get('/settings')->assertOk()
            ->assertDontSee('Pipeline stages')
            ->assertDontSee('Lead sources')
            ->assertDontSee('Decision Review');
    }

    public function test_command_center_and_reports_follow_the_quote_to_delivery_workflow(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());

        $this->get('/dashboard')->assertOk()
            ->assertSee('One clear path from accepted scope to delivered work.')
            ->assertSee('Accepted quote value')
            ->assertSee('Recent quotes')
            ->assertSee('Project delivery')
            ->assertSee('Recent workflow activity')
            ->assertDontSee('Sales pipeline velocity')
            ->assertDontSee('Upcoming production');

        $this->get('/reports')->assertOk()
            ->assertSee('Workflow Reports')
            ->assertSee('Quote performance')
            ->assertSee('Package performance')
            ->assertSee('Client value')
            ->assertSee('Project delivery')
            ->assertSee('Task workload')
            ->assertDontSee('Sales funnel')
            ->assertDontSee('Team utilization');
    }

    public function test_client_portal_is_isolated_and_supports_multiple_projects(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        if (DB::table('clients')->where('status', 'active')->count() < 2) {
            $otherBusinessId = DB::table('businesses')->insertGetId([
                'name'=>'Portal Isolation Other Business', 'industry'=>'Quality assurance', 'created_at'=>now(),
            ]);
            DB::table('clients')->insert([
                'business_id'=>$otherBusinessId, 'name'=>'Other Client QA', 'email'=>'other-client@example.test',
                'status'=>'active', 'health'=>'healthy', 'joined_at'=>now()->toDateString(), 'created_at'=>now(),
            ]);
        }
        $clients = DB::table('clients')->where('status', 'active')->orderBy('id')->limit(2)->get();
        $this->assertCount(2, $clients);
        $client = $clients[0];
        $otherClient = $clients[1];
        $businessName = (string) DB::table('businesses')->where('id', $client->business_id)->value('name');
        $employee = DB::table('employees')->where('status', 'active')->orderBy('id')->first();

        foreach (['Client Portal Website', 'Client Portal Campaign'] as $projectName) {
            $this->post('/projects', [
                'action'=>'create_project', 'client_id'=>$client->id, 'name'=>$projectName,
                'project_type'=>'Delivery', 'start_date'=>now()->toDateString(),
            ])->assertRedirect('/projects')->assertSessionHas('success');
        }
        $projectId = (int) DB::table('projects')->where('client_id', $client->id)->where('name', 'Client Portal Website')->value('id');
        $otherProjectId = DB::table('projects')->insertGetId([
            'client_id'=>$otherClient->id, 'name'=>'Private Other Client Project', 'project_type'=>'Delivery',
            'start_date'=>now()->toDateString(), 'status'=>'planning', 'priority'=>'medium', 'budget'=>0,
            'estimated_hours'=>0, 'actual_hours'=>0, 'created_at'=>now(),
        ]);
        $this->post('/tasks', [
            'action'=>'create_task', 'project_id'=>$projectId, 'title'=>'Client visible launch checklist',
            'description'=>'Public delivery scope.', 'due_date'=>now()->toDateString(), 'priority'=>'high',
            'assigned_employee_ids'=>[$employee->id],
        ])->assertRedirect('/tasks')->assertSessionHas('success');
        DB::table('project_tasks')->insert([
            'project_id'=>$otherProjectId, 'client_id'=>$otherClient->id, 'title'=>'Other client confidential task',
            'description'=>'Never expose this task.', 'due_date'=>now()->toDateString(), 'priority'=>'medium',
            'status'=>'todo', 'estimated_hours'=>0, 'actual_hours'=>0, 'created_at'=>now(),
        ]);

        $this->post('/client?id='.$client->id, [
            'action'=>'save_client_portal_access', 'client_id'=>$client->id,
            'name'=>'Portal Client QA', 'email'=>'portal-client-qa@example.test',
            'password'=>'PortalPass!2026', 'status'=>'active',
        ])->assertRedirect('/client?id='.$client->id)->assertSessionHas('success');
        $portalUser = User::query()->where('email', 'portal-client-qa@example.test')->firstOrFail();
        $this->assertDatabaseHas('clients', ['id'=>$client->id, 'portal_user_id'=>$portalUser->id]);

        auth()->logout();
        $this->post('/login', [
            'email'=>'portal-client-qa@example.test', 'password'=>'PortalPass!2026',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($portalUser);
        $this->get('/dashboard')->assertOk()
            ->assertSee('360 CLIENT PORTAL')->assertSee($businessName)
            ->assertSee('Projects in progress')->assertDontSee('Quick add');
        $this->get('/projects')->assertOk()
            ->assertSee('Client Portal Website')->assertSee('Client Portal Campaign')
            ->assertDontSee('Private Other Client Project')->assertDontSee($employee->name);
        $this->get('/tasks')->assertOk()
            ->assertSee('Client visible launch checklist')->assertSee('Open details')
            ->assertDontSee('Other client confidential task')->assertDontSee($employee->name);
        $this->get('/calendar?month='.now()->format('Y-m'))->assertOk()
            ->assertSee('My Project Calendar')->assertSee('Client visible launch checklist')
            ->assertSee('All projects')->assertDontSee('Other client confidential task')->assertDontSee('All employees');
        $this->get('/clients')->assertForbidden();

        $taskId = (int) DB::table('project_tasks')->where('title', 'Client visible launch checklist')->value('id');
        $this->post('/tasks', ['action'=>'task_status', 'id'=>$taskId, 'status'=>'completed'])
            ->assertRedirect('/tasks')->assertSessionHas('danger');
        $this->assertDatabaseHas('project_tasks', ['id'=>$taskId, 'status'=>'todo']);
        $this->post('/tasks', ['action'=>'create_task', 'project_id'=>$projectId, 'title'=>'Client must not create this'])
            ->assertRedirect('/tasks')->assertSessionHas('danger');
        $this->assertDatabaseMissing('project_tasks', ['title'=>'Client must not create this']);
    }

    public function test_repeat_client_email_reuses_account_and_client_can_change_generated_password(): void
    {
        $admin = User::query()->where('email', 'admin@agencyos.local')->firstOrFail();
        $this->actingAs($admin);
        $businessId = DB::table('businesses')->insertGetId([
            'name'=>'Repeat Client QA', 'industry'=>'Quality assurance', 'created_at'=>now(),
        ]);
        $clientId = DB::table('clients')->insertGetId([
            'business_id'=>$businessId, 'name'=>'Repeat Client Contact', 'email'=>'repeat-client@example.test',
            'status'=>'active', 'health'=>'healthy', 'joined_at'=>now()->toDateString(), 'created_at'=>now(),
        ]);
        $package = DB::table('packages')->where('package_type', 'quote_setup')->where('active', 1)->orderBy('display_order')->first();
        $packageItem = DB::table('package_items')->where('package_id', $package->id)->where('included', 1)->orderBy('sort_order')->first();
        $answers = [];
        foreach (config('quote_studio.assessment') as $question) { $answers[$question['key']] = $question['options'][0]['value']; }

        $this->post('/quote_studio', [
            'action'=>'save_quote', 'business_name'=>'Repeat Client QA', 'contact_name'=>'Repeat Client Contact',
            'contact_email'=>'REPEAT-client@example.test', 'business_stage'=>'existing_business', 'years_operating'=>5,
            'locale'=>'en', 'assessment_json'=>json_encode($answers), 'selected_tier'=>$package->tier,
            'items_json'=>json_encode([[
                'service_id'=>$packageItem->service_id, 'list_price'=>(float)$packageItem->unit_price,
                'discount_percent'=>0, 'custom_price'=>null, 'description'=>$packageItem->description,
            ]]),
            'support_plan'=>'none', 'membership_plan'=>'none', 'membership_term'=>0, 'tax_percent'=>13, 'validity_days'=>7,
        ])->assertSessionHas('success');

        $quote = DB::table('proposals')->where('contact_email', 'repeat-client@example.test')->latest('id')->first();
        $this->assertSame($clientId, (int)$quote->client_id);
        $this->assertNull($quote->lead_id);
        $this->post('/saved_quotes', ['action'=>'onboard_quote', 'proposal_id'=>$quote->id])
            ->assertRedirect('/client?id='.$clientId)->assertSessionHas('success');

        $client = DB::table('clients')->where('id', $clientId)->first();
        $this->assertNotNull($client->portal_user_id);
        $this->assertNotNull($client->portal_password_encrypted);
        $firstTemporaryPassword = Crypt::decryptString($client->portal_password_encrypted);
        $this->assertTrue(password_verify($firstTemporaryPassword, (string)DB::table('users')->where('id', $client->portal_user_id)->value('password_hash')));
        $this->get('/client?id='.$clientId)->assertOk()->assertSee('Login credentials')->assertSee($firstTemporaryPassword);

        $this->post('/client?id='.$clientId, [
            'action'=>'reset_client_portal_password', 'client_id'=>$clientId, 'password'=>'',
        ])->assertRedirect('/client?id='.$clientId)->assertSessionHas('success');
        $client = DB::table('clients')->where('id', $clientId)->first();
        $resetTemporaryPassword = Crypt::decryptString($client->portal_password_encrypted);
        $this->assertNotSame($firstTemporaryPassword, $resetTemporaryPassword);

        auth()->logout();
        $this->post('/login', ['email'=>'repeat-client@example.test', 'password'=>$resetTemporaryPassword])
            ->assertRedirect('/dashboard');
        $this->get('/change_password')->assertOk()->assertSee('Change Password')->assertSee('Current password');
        $this->post('/change_password', [
            'action'=>'change_password', 'current_password'=>$resetTemporaryPassword,
            'new_password'=>'ClientChosen!2026', 'new_password_confirmation'=>'ClientChosen!2026',
        ])->assertRedirect('/change_password')->assertSessionHas('success');

        $client = DB::table('clients')->where('id', $clientId)->first();
        $this->assertNull($client->portal_password_encrypted);
        $this->assertNotNull($client->portal_password_changed_at);
        $this->assertTrue(password_verify('ClientChosen!2026', (string)DB::table('users')->where('id', $client->portal_user_id)->value('password_hash')));
    }

    public function test_internal_login_email_does_not_block_client_onboarding(): void
    {
        $admin = User::query()->where('email', 'admin@agencyos.local')->firstOrFail();
        $this->actingAs($admin);
        $internalRoleId = DB::table('roles')->where('slug', 'developer')->value('id');
        DB::table('users')->insert([
            'role_id'=>$internalRoleId, 'name'=>'Internal Email Owner', 'email'=>'internal-owner@example.test',
            'password_hash'=>password_hash('InternalPass!2026', PASSWORD_DEFAULT), 'status'=>'active', 'created_at'=>now(),
        ]);
        $package = DB::table('packages')->where('package_type', 'quote_setup')->where('active', 1)->orderBy('display_order')->first();
        $packageItem = DB::table('package_items')->where('package_id', $package->id)->where('included', 1)->orderBy('sort_order')->first();
        $answers = [];
        foreach (config('quote_studio.assessment') as $question) { $answers[$question['key']] = $question['options'][0]['value']; }

        $this->post('/quote_studio', [
            'action'=>'save_quote', 'business_name'=>'Internal Email Client QA', 'contact_name'=>'Client Contact',
            'contact_email'=>'internal-owner@example.test', 'business_stage'=>'existing_business', 'years_operating'=>3,
            'locale'=>'en', 'assessment_json'=>json_encode($answers), 'selected_tier'=>$package->tier,
            'items_json'=>json_encode([[
                'service_id'=>$packageItem->service_id, 'list_price'=>(float)$packageItem->unit_price,
                'discount_percent'=>0, 'custom_price'=>null, 'description'=>$packageItem->description,
            ]]),
            'support_plan'=>'none', 'membership_plan'=>'none', 'membership_term'=>0, 'tax_percent'=>13, 'validity_days'=>7,
        ])->assertSessionHas('success');

        $quote = DB::table('proposals')->where('contact_email', 'internal-owner@example.test')->latest('id')->first();
        $this->post('/saved_quotes', ['action'=>'onboard_quote', 'proposal_id'=>$quote->id])
            ->assertRedirectContains('/client?id=')->assertSessionHas('success');
        $quote = DB::table('proposals')->where('id', $quote->id)->first();
        $client = DB::table('clients')->where('id', $quote->client_id)->first();
        $this->assertSame('accepted', $quote->status);
        $this->assertNull($client->portal_user_id);
        $this->assertGreaterThan(0, DB::table('projects')->where('client_id', $client->id)->count());
        $this->get('/client?id='.$client->id)->assertOk()
            ->assertSee('Use a different portal email.')
            ->assertSee('The client and delivery work were onboarded normally.');
    }

    public function test_task_updates_and_files_respect_client_visibility(): void
    {
        $admin = User::query()->where('email', 'admin@agencyos.local')->firstOrFail();
        $this->actingAs($admin);
        $client = DB::table('clients')->where('status', 'active')->orderBy('id')->first();
        $projectId = DB::table('projects')->insertGetId([
            'client_id'=>$client->id, 'name'=>'Deliverable Security QA', 'project_type'=>'Delivery',
            'start_date'=>now()->toDateString(), 'status'=>'active', 'priority'=>'medium', 'budget'=>0,
            'estimated_hours'=>0, 'actual_hours'=>0, 'created_at'=>now(),
        ]);
        $taskId = DB::table('project_tasks')->insertGetId([
            'project_id'=>$projectId, 'client_id'=>$client->id, 'title'=>'Secure deliverable task',
            'description'=>'Client-safe task details.', 'due_date'=>now()->addDay()->toDateString(),
            'priority'=>'medium', 'status'=>'in_progress', 'estimated_hours'=>0, 'actual_hours'=>0,
            'created_at'=>now(),
        ]);

        $this->post('/tasks', [
            'action'=>'add_task_note', 'task_id'=>$taskId, 'note'=>'Client-visible milestone reached.',
            'client_visible'=>'1',
        ])->assertRedirect('/tasks')->assertSessionHas('success');
        $this->post('/tasks', [
            'action'=>'add_task_note', 'task_id'=>$taskId, 'note'=>'Internal production note.',
        ])->assertRedirect('/tasks')->assertSessionHas('success');
        $this->post('/tasks', [
            'action'=>'upload_task_file', 'task_id'=>$taskId, 'client_visible'=>'1',
            'task_file'=>UploadedFile::fake()->createWithContent('approved-concept.pdf', 'safe deliverable'),
        ])->assertRedirect('/tasks')->assertSessionHas('success');
        $this->post('/tasks', [
            'action'=>'upload_task_file', 'task_id'=>$taskId,
            'task_file'=>UploadedFile::fake()->createWithContent('internal-notes.txt', 'private note'),
        ])->assertRedirect('/tasks')->assertSessionHas('success');

        $clientFile = DB::table('task_files')->where('task_id', $taskId)->where('visibility', 'client')->first();
        $internalFile = DB::table('task_files')->where('task_id', $taskId)->where('visibility', 'internal')->first();
        $this->assertNotNull($clientFile);
        $this->assertNotNull($internalFile);
        $this->assertDatabaseHas('task_updates', ['task_id'=>$taskId, 'body'=>'Client-visible milestone reached.', 'visibility'=>'client']);
        $this->assertDatabaseHas('task_updates', ['task_id'=>$taskId, 'body'=>'Internal production note.', 'visibility'=>'internal']);

        $this->post('/client?id='.$client->id, [
            'action'=>'save_client_portal_access', 'client_id'=>$client->id,
            'name'=>'Deliverable Client QA', 'email'=>'deliverable-client@example.test',
            'password'=>'PortalPass!2026', 'status'=>'active',
        ])->assertRedirect('/client?id='.$client->id)->assertSessionHas('success');
        $this->actingAs(User::query()->where('email', 'deliverable-client@example.test')->firstOrFail());

        $this->get('/tasks')->assertOk()
            ->assertSee('Secure deliverable task')->assertSee('Client-visible milestone reached.')
            ->assertSee('approved-concept.pdf')->assertDontSee('Internal production note.')
            ->assertDontSee('internal-notes.txt')->assertSee('Read-only access')
            ->assertDontSee('Add a message')->assertDontSee('Share a file');
        $this->get('/task_file?id='.$clientFile->id)->assertOk();
        $this->get('/task_file_view?id='.$clientFile->id)->assertOk();
        $this->get('/task_file?id='.$internalFile->id)->assertNotFound();
        $this->post('/tasks', [
            'action'=>'add_task_note', 'task_id'=>$taskId, 'note'=>'Client feedback posted securely.',
        ])->assertRedirect('/tasks')->assertSessionHas('danger');
        $this->assertDatabaseMissing('task_updates', ['task_id'=>$taskId, 'body'=>'Client feedback posted securely.']);
        $this->post('/tasks', [
            'action'=>'upload_task_file', 'task_id'=>$taskId,
            'task_files'=>[UploadedFile::fake()->createWithContent('client-upload.txt', 'blocked')],
        ])->assertRedirect('/tasks')->assertSessionHas('danger');
        $this->assertDatabaseMissing('task_files', ['task_id'=>$taskId, 'original_name'=>'client-upload.txt']);

        $this->actingAs($admin);
        $this->post('/tasks', [
            'action'=>'update_task_file_visibility', 'file_id'=>$internalFile->id, 'client_visible'=>'1',
        ])->assertRedirect('/tasks')->assertSessionHas('success');
        $this->assertDatabaseHas('task_files', ['id'=>$internalFile->id, 'visibility'=>'client']);
        $internalUpdateId = DB::table('task_updates')->where('task_id',$taskId)->where('body','Internal production note.')->value('id');
        $this->post('/tasks', [
            'action'=>'update_task_note_visibility', 'update_id'=>$internalUpdateId, 'client_visible'=>'1',
        ])->assertRedirect('/tasks')->assertSessionHas('success');
        $this->assertDatabaseHas('task_updates', ['id'=>$internalUpdateId, 'visibility'=>'client']);

        foreach ([$clientFile, $internalFile] as $file) {
            $path = storage_path('app/private/task-files'.DIRECTORY_SEPARATOR.$file->storage_path);
            if (is_file($path)) { @unlink($path); }
        }
    }

    public function test_detailed_invoices_use_bank_milestone_line_items_and_generate_pdf(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $this->get('/settings')->assertOk()->assertSee('Number (OCN/BIN)');
        $this->post('/settings', [
            'action'=>'save_invoice_profile','agency_name'=>'360 Creative Agency',
            'ocn_bin'=>'OCN-BIN-QA-2026','country'=>'Canada',
        ])->assertRedirect('/settings')->assertSessionHas('success');
        $this->assertDatabaseHas('invoice_profiles', ['ocn_bin'=>'OCN-BIN-QA-2026']);
        $client = DB::table('clients')->where('status','active')->orderBy('id')->first();
        $projectId = DB::table('projects')->insertGetId([
            'client_id'=>$client->id,'name'=>'Invoice Workflow QA','project_type'=>'Delivery',
            'start_date'=>now()->toDateString(),'status'=>'active','priority'=>'medium','budget'=>0,
            'estimated_hours'=>0,'actual_hours'=>0,'created_at'=>now(),
        ]);
        $this->post('/settings', [
            'action'=>'save_bank_account','account_name'=>'CAD Operating','bank_name'=>'QA Bank',
            'account_holder'=>'360 Creative Agency','account_number'=>'1234567','currency'=>'CAD',
            'active'=>'1','is_default'=>'1','payment_instructions'=>'Include the invoice number.',
        ])->assertRedirect('/settings')->assertSessionHas('success');
        $bankId = (int) DB::table('bank_accounts')->where('account_name','CAD Operating')->value('id');

        $this->post('/invoices', [
            'action'=>'create_invoice','client_id'=>$client->id,'project_id'=>$projectId,
            'bank_account_id'=>$bankId,'due_date'=>now()->addDays(14)->toDateString(),'status'=>'sent',
            'currency'=>'CAD','purchase_order_number'=>'PO-QA-44','milestone_title'=>'Design approved',
            'milestone_description'=>'The approved design milestone is ready for billing.',
            'item_description'=>['Design milestone','Production assets'],
            'item_quantity'=>[1,2],'item_unit_price'=>[1000,250],
            'discount'=>100,'tax_percent'=>13,'payment_terms'=>'Due within 14 days.',
        ])->assertRedirectContains('/invoice?id=')->assertSessionHas('success');
        $invoice = DB::table('invoices')->where('project_id',$projectId)->latest('id')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(1500.0,(float)$invoice->subtotal);
        $this->assertSame(1582.0,(float)$invoice->total);
        $this->assertSame($bankId,(int)$invoice->bank_account_id);
        $this->assertSame(2,DB::table('invoice_items')->where('invoice_id',$invoice->id)->count());
        $this->get('/invoice?id='.$invoice->id)->assertOk()
            ->assertSee('Design approved')->assertSee('QA Bank')->assertSee('Production assets')
            ->assertSee('Number (OCN/BIN)')->assertSee('OCN-BIN-QA-2026')->assertSee('Invoice PDF');
        $this->get('/invoices')->assertOk()->assertSee('Number (OCN/BIN)')->assertSee('OCN-BIN-QA-2026');
        $this->get('/invoice_pdf?id='.$invoice->id)->assertOk()->assertHeader('content-type','application/pdf');
    }

    public function test_client_details_can_be_edited_from_the_client_listing_workflow(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $client = DB::table('clients')->orderBy('id')->first();
        $sizeId = (int)DB::table('business_sizes')->where('active',1)->orderBy('id')->value('id');
        $managerId = (int)DB::table('employees')->where('status','active')->orderBy('id')->value('id');
        $portalEmail = $client->portal_user_id ? DB::table('users')->where('id',$client->portal_user_id)->value('email') : null;

        $this->get('/clients')->assertOk()->assertSee('Edit')->assertSee('View');
        $this->post('/client?id='.$client->id, [
            'action'=>'update_client_details','client_id'=>$client->id,
            'contact_name'=>'Updated Client Contact','email'=>'updated-client@example.test','phone'=>'+1 416 555 0199',
            'business_name'=>'Updated Client Business','legal_name'=>'Updated Client Business Inc.','industry'=>'Professional Services',
            'website'=>'https://updated-client.example.test','employee_count'=>24,'years_in_business'=>7,
            'business_size_id'=>$sizeId,'account_manager_id'=>$managerId,'tax_number'=>'REG-CLIENT-2026',
            'joined_at'=>'2026-09-01','status'=>'active','address'=>'100 King Street West','city'=>'Toronto','state'=>'Ontario','postal_code'=>'M5X 1A9',
        ])->assertRedirectContains('/client?id='.$client->id)->assertSessionHas('success');

        $this->assertDatabaseHas('clients', ['id'=>$client->id,'name'=>'Updated Client Contact','email'=>'updated-client@example.test','phone'=>'+1 416 555 0199','account_manager_id'=>$managerId,'joined_at'=>'2026-09-01']);
        $this->assertDatabaseHas('businesses', ['id'=>$client->business_id,'name'=>'Updated Client Business','legal_name'=>'Updated Client Business Inc.','tax_number'=>'REG-CLIENT-2026']);
        $this->assertDatabaseHas('contacts', ['client_id'=>$client->id,'name'=>'Updated Client Contact','email'=>'updated-client@example.test','primary_contact'=>1]);
        $this->assertDatabaseHas('business_locations', ['business_id'=>$client->business_id,'address'=>'100 King Street West','city'=>'Toronto','state'=>'Ontario','postal_code'=>'M5X 1A9','primary_location'=>1]);
        if ($client->portal_user_id) { $this->assertSame($portalEmail, DB::table('users')->where('id',$client->portal_user_id)->value('email')); }
        $this->get('/client?id='.$client->id.'&edit=1')->assertOk()->assertSee('Edit client and business details')->assertSee('Updated Client Contact')->assertSee('Contact changes do not alter the separate client portal login.');
    }

    public function test_invoice_partial_refunds_and_cancellations_generate_audited_pdf_documents(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        $client = DB::table('clients')->where('status','active')->orderBy('id')->first();

        $this->post('/invoices', [
            'action'=>'create_invoice','client_id'=>$client->id,'due_date'=>'2026-09-30','status'=>'sent','currency'=>'CAD',
            'item_description'=>['Retainer milestone'],'item_quantity'=>[1],'item_unit_price'=>[1000],'discount'=>0,'tax_percent'=>13,
        ])->assertSessionHas('success');
        $creditInvoice = DB::table('invoices')->latest('id')->first();
        $this->post('/invoice?id='.$creditInvoice->id, [
            'action'=>'create_invoice_adjustment','invoice_id'=>$creditInvoice->id,'adjustment_type'=>'partial_refund',
            'adjustment_date'=>'2026-09-10','amount'=>100,'tax_percent'=>13,'reason'=>'Overcharge correction','method'=>'Bank transfer','reference'=>'RF-QA-100',
        ])->assertRedirectContains('/invoice?id='.$creditInvoice->id)->assertSessionHas('success');
        $credit = DB::table('invoice_adjustments')->where('invoice_id',$creditInvoice->id)->first();
        $this->assertNotNull($credit);
        $this->assertSame('partial_refund',$credit->type);
        $this->assertSame(113.0,(float)$credit->total);
        $this->assertStringStartsWith('CN-', $credit->adjustment_number);
        $this->assertDatabaseHas('invoices', ['id'=>$creditInvoice->id,'status'=>'partially_refunded']);
        $this->get('/invoice?id='.$creditInvoice->id)->assertOk()->assertSeeText('Credit notes & cancellation documents')->assertSee('Overcharge correction')->assertSee('Net invoice value');
        $this->get('/invoice_adjustment_pdf?id='.$credit->id)->assertOk()->assertHeader('content-type','application/pdf');

        $this->post('/invoices', [
            'action'=>'create_invoice','client_id'=>$client->id,'due_date'=>'2026-09-30','status'=>'sent','currency'=>'CAD',
            'item_description'=>['Cancelled milestone'],'item_quantity'=>[1],'item_unit_price'=>[500],'discount'=>0,'tax_percent'=>0,
        ])->assertSessionHas('success');
        $cancelledInvoice = DB::table('invoices')->latest('id')->first();
        $this->post('/invoice?id='.$cancelledInvoice->id, [
            'action'=>'create_invoice_adjustment','invoice_id'=>$cancelledInvoice->id,'adjustment_type'=>'cancellation',
            'adjustment_date'=>'2026-09-11','reason'=>'Project cancelled before delivery','method'=>'Account credit','reference'=>'CXL-QA-500',
        ])->assertRedirectContains('/invoice?id='.$cancelledInvoice->id)->assertSessionHas('success');
        $cancellation = DB::table('invoice_adjustments')->where('invoice_id',$cancelledInvoice->id)->first();
        $this->assertSame('cancellation',$cancellation->type);
        $this->assertSame(500.0,(float)$cancellation->total);
        $this->assertStringStartsWith('CXL-', $cancellation->adjustment_number);
        $this->assertDatabaseHas('invoices', ['id'=>$cancelledInvoice->id,'status'=>'cancelled']);
        $this->get('/invoice_adjustment_pdf?id='.$cancellation->id)->assertOk()->assertHeader('content-type','application/pdf');
        $this->get('/invoices')->assertOk()->assertSee('Gross issued')->assertSee('Partial refunds')->assertSee('Invoice adjustment report')->assertSee($cancellation->adjustment_number);
        $this->get('/reports')->assertOk()->assertSeeText('Invoice reversals & net billing')->assertSee('Net invoiced');
    }

    public function test_reports_render_the_empty_quote_state_without_a_server_error(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());
        DB::table('proposals')->delete();

        $this->get('/reports')->assertOk()
            ->assertSee('Workflow Reports')
            ->assertSee('No quotes yet')
            ->assertSeeText('Invoice reversals & net billing');
    }

    public function test_admin_can_edit_team_profiles_and_linked_login_access(): void
    {
        $adminRoleId = (int)DB::table('roles')->where('slug', 'admin')->value('id');
        $developerRoleId = (int)DB::table('roles')->where('slug', 'developer')->value('id');
        $projectManagerRoleId = (int)DB::table('roles')->where('slug', 'project_manager')->value('id');
        $adminUserId = DB::table('users')->insertGetId([
            'role_id'=>$adminRoleId,
            'name'=>'Team Admin QA',
            'email'=>'team-admin-qa@example.test',
            'password_hash'=>password_hash('AdminTeam!2026', PASSWORD_DEFAULT),
            'status'=>'active',
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
        $this->actingAs(User::query()->findOrFail($adminUserId));

        $employee = DB::table('employees')->whereNull('user_id')->orderBy('id')->first();
        $this->assertNotNull($employee);
        $taskAssignments = DB::table('project_task_assignees')->where('employee_id', $employee->id)->orderBy('task_id')->pluck('task_id')->all();

        $this->get('/team')->assertOk()
            ->assertSee('modal-edit-employee-'.$employee->id, false)
            ->assertSee('Edit team member')
            ->assertSee('Create a system login using this employee email');

        $this->post('/team', [
            'action'=>'update_employee',
            'employee_id'=>$employee->id,
            'name'=>'Updated Team Member',
            'email'=>'updated-team-member@example.test',
            'phone'=>'+1 416 555 0177',
            'job_title'=>'Lead Developer',
            'department'=>'Development',
            'skills'=>'Laravel, JavaScript, Delivery',
            'hourly_cost'=>62.50,
            'capacity_hours'=>32,
            'status'=>'active',
            'create_login'=>'1',
            'role_id'=>$developerRoleId,
            'login_status'=>'active',
            'password'=>'TeamLogin!2026',
        ])->assertRedirect('/team')->assertSessionHas('success');

        $updatedEmployee = DB::table('employees')->where('id', $employee->id)->first();
        $this->assertSame('Updated Team Member', $updatedEmployee->name);
        $this->assertSame('updated-team-member@example.test', $updatedEmployee->email);
        $this->assertSame('Lead Developer', $updatedEmployee->job_title);
        $this->assertSame(62.5, (float)$updatedEmployee->hourly_cost);
        $this->assertSame(32.0, (float)$updatedEmployee->capacity_hours);
        $this->assertNotNull($updatedEmployee->user_id);
        $login = DB::table('users')->where('id', $updatedEmployee->user_id)->first();
        $this->assertSame($developerRoleId, (int)$login->role_id);
        $this->assertSame('active', $login->status);
        $this->assertTrue(password_verify('TeamLogin!2026', $login->password_hash));
        $this->assertSame($taskAssignments, DB::table('project_task_assignees')->where('employee_id', $employee->id)->orderBy('task_id')->pluck('task_id')->all());

        $this->post('/team', [
            'action'=>'update_employee',
            'employee_id'=>$employee->id,
            'name'=>'Updated Team Member',
            'email'=>'updated-team-member@example.test',
            'phone'=>'+1 416 555 0177',
            'job_title'=>'Lead Developer',
            'department'=>'Development',
            'skills'=>'Laravel, JavaScript, Delivery',
            'hourly_cost'=>62.50,
            'capacity_hours'=>32,
            'status'=>'inactive',
            'role_id'=>$projectManagerRoleId,
            'login_status'=>'inactive',
        ])->assertRedirect('/team')->assertSessionHas('success');

        $this->assertDatabaseHas('employees', ['id'=>$employee->id,'status'=>'inactive']);
        $this->assertDatabaseHas('users', ['id'=>$updatedEmployee->user_id,'role_id'=>$projectManagerRoleId,'status'=>'inactive']);
        $this->assertDatabaseHas('audit_logs', ['action'=>'updated','entity_type'=>'employee','entity_id'=>$employee->id]);
    }

    public function test_non_admin_with_team_view_access_cannot_edit_team_members(): void
    {
        $employeeRoleId = (int)DB::table('roles')->where('slug', 'employee')->value('id');
        $teamPermissionId = (int)DB::table('permissions')->where('slug', 'team.access')->value('id');
        $userId = DB::table('users')->insertGetId([
            'role_id'=>$employeeRoleId,
            'name'=>'Team Viewer QA',
            'email'=>'team-viewer-qa@example.test',
            'password_hash'=>password_hash('TeamViewer!2026', PASSWORD_DEFAULT),
            'status'=>'active',
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
        DB::table('user_permissions')->insert([
            'user_id'=>$userId,
            'permission_id'=>$teamPermissionId,
            'allowed'=>1,
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
        $this->actingAs(User::query()->findOrFail($userId));
        $employee = DB::table('employees')->orderBy('id')->first();

        $this->get('/team')->assertOk()->assertDontSee('modal-edit-employee-'.$employee->id, false);
        $this->post('/team', [
            'action'=>'update_employee',
            'employee_id'=>$employee->id,
            'name'=>'Unauthorized Change',
            'email'=>$employee->email,
            'department'=>$employee->department,
            'hourly_cost'=>$employee->hourly_cost,
            'capacity_hours'=>$employee->capacity_hours,
            'status'=>$employee->status,
        ])->assertRedirect('/team')->assertSessionHas('danger', 'Only administrators can edit team members.');

        $this->assertDatabaseMissing('employees', ['id'=>$employee->id,'name'=>'Unauthorized Change']);
    }
}

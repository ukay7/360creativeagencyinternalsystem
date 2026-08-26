<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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

        foreach (['dashboard','quote_studio','saved_quotes','quote_settings','leads','pipeline','discovery','clients','packages','services','proposals','contracts','projects','tasks','visits','content','media','calendar','invoices','time','team','reports','settings','audit','search?q=client'] as $module) {
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
        $this->get('/dashboard')->assertOk()->assertSee('Lead follow-up reminders')->assertSee('Laravel Qualified Lead');

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

    public function test_sidebar_uses_database_driven_sections_in_business_order(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@agencyos.local')->firstOrFail());

        $navigation = app(\App\Services\Auth::class)->navigationItems();

        $this->assertSame(
            ['Command Center', 'Quote & Onboarding', 'Project Delivery', 'Content & Marketing', 'Operations & Finance', 'Settings'],
            array_column($navigation, 'label')
        );

        $groups = [];
        foreach ($navigation as $node) {
            if ($node['type'] === 'group') {
                $groups[$node['label']] = array_column($node['children'], 'label');
            }
        }

        $this->assertSame(['New Quote', 'Saved Quotes', 'Clients', 'Quote Settings'], $groups['Quote & Onboarding']);
        $this->assertSame(['Projects', 'Tasks', 'Time Tracking'], $groups['Project Delivery']);
        $this->assertSame(['Content Visits', 'Content Calendar', 'Media Library'], $groups['Content & Marketing']);
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
            'tax_enabled'=>1,'tax_percent'=>13,'validity_days'=>14,
        ]);

        $quote=DB::table('proposals')->where('business_name','Quote Journey Test')->first();
        $response->assertRedirect('/quote_view?id='.$quote->id)->assertSessionHas('success');
        $this->assertSame('ar',$quote->locale);
        $this->assertSame(10,(int)$quote->assessment_score);
        $this->assertSame('medium',$quote->recommended_tier);
        $this->assertSame('basic',$quote->selected_tier);
        $this->assertGreaterThan(0,(float)$quote->support_amount);
        $this->assertSame(495.0,(float)$quote->membership_amount);
        $this->assertDatabaseHas('leads',['email'=>'rami@example.test','status'=>'proposal']);
        $this->assertDatabaseHas('proposal_items',['proposal_id'=>$quote->id,'item_type'=>'service']);
        $this->assertDatabaseHas('proposal_items',['proposal_id'=>$quote->id,'item_type'=>'support']);
        $this->assertDatabaseHas('proposal_items',['proposal_id'=>$quote->id,'item_type'=>'membership']);
        $this->get('/quote_view?id='.$quote->id.'&lang=ar')->assertOk()->assertSee('ملخص العرض')->assertSee($items[0]->description_ar);
        $this->get('/quote/share/'.$quote->share_token.'?lang=he')->assertOk()->assertSee('סיכום הצעה')->assertSee($items[0]->description_he);
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
            ->assertSee('QA Updated Service')
            ->assertSee('Edit QA Updated Service');
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

        $this->get('/settings')->assertOk()->assertSee('Decision Review')->assertSee('Add stage')->assertSee('protected closing stage');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

final class AgencySeeder
{
    public static function run(Database $db): void
    {
        if ((int) $db->scalar('SELECT COUNT(*) FROM roles') > 0) {
            self::ensureDemoExtensions($db);
            self::ensureQuoteStudio($db);
            return;
        }

        $db->transaction(function () use ($db): void {
            $now = now()->toDateTimeString();
            $roles = [
                ['Super Admin', 'super_admin', 'Full system access'],
                ['Admin', 'admin', 'Agency operations and configuration'],
                ['Sales', 'sales', 'CRM, pipeline, proposals and clients'],
                ['Project Manager', 'project_manager', 'Projects, tasks, clients and calendar'],
                ['Marketing', 'marketing', 'Content, campaigns and client work'],
                ['Creative', 'creative', 'Visits, media and assigned work'],
                ['Developer', 'developer', 'Development projects, tasks and time'],
                ['Employee', 'employee', 'Assigned work only'],
            ];
            foreach ($roles as $role) {
                $db->insert('roles', ['name' => $role[0], 'slug' => $role[1], 'description' => $role[2], 'created_at' => $now]);
            }

            $permissions = [
                '*' => 'Full access', 'dashboard.view' => 'View dashboard', 'crm.manage' => 'Manage CRM',
                'clients.manage' => 'Manage clients', 'packages.manage' => 'Manage packages',
                'projects.manage' => 'Manage projects', 'tasks.manage' => 'Manage tasks',
                'content.manage' => 'Manage content', 'finance.manage' => 'Manage finance',
                'team.manage' => 'Manage team', 'reports.view' => 'View reports', 'settings.manage' => 'Manage settings',
                'dashboard.access'=>'Access Command Center','leads.access'=>'Access Leads','pipeline.access'=>'Access Sales Pipeline',
                'discovery.access'=>'Access Discovery','clients.access'=>'Access Clients','packages.access'=>'Access Packages',
                'services.access'=>'Access Services','proposals.access'=>'Access Proposals','contracts.access'=>'Access Contracts',
                'projects.access'=>'Access Projects','tasks.access'=>'Access Tasks','visits.access'=>'Access Content Visits',
                'content.access'=>'Access Content Calendar','media.access'=>'Access Media Library','calendar.access'=>'Access Agency Calendar',
                'invoices.access'=>'Access Invoices','time.access'=>'Access Time Tracking','team.access'=>'Access Team',
                'reports.access'=>'Access Reports','settings.access'=>'Access Settings','audit.access'=>'Access Audit Log',
            ];
            foreach ($permissions as $slug => $name) {
                if(!(int)$db->scalar('SELECT COUNT(*) FROM permissions WHERE slug=?',[$slug])){$db->insert('permissions', ['name' => $name, 'slug' => $slug]);}
            }

            $permissionGroups = [
                'admin' => ['dashboard.view','crm.manage','clients.manage','packages.manage','projects.manage','tasks.manage','content.manage','finance.manage','team.manage','reports.view','settings.manage','dashboard.access','leads.access','pipeline.access','discovery.access','clients.access','packages.access','services.access','proposals.access','contracts.access','projects.access','tasks.access','visits.access','content.access','media.access','calendar.access','invoices.access','time.access','team.access','reports.access','settings.access','audit.access'],
                'sales' => ['dashboard.view','crm.manage','clients.manage','packages.manage','finance.manage','reports.view','dashboard.access','calendar.access','leads.access','pipeline.access','discovery.access','clients.access','contracts.access','packages.access','services.access','proposals.access','invoices.access','reports.access'],
                'project_manager' => ['dashboard.view','clients.manage','projects.manage','tasks.manage','content.manage','reports.view','dashboard.access','calendar.access','clients.access','contracts.access','projects.access','tasks.access','time.access','visits.access','content.access','media.access','reports.access'],
                'marketing' => ['dashboard.view','clients.manage','tasks.manage','content.manage','dashboard.access','calendar.access','clients.access','tasks.access','time.access','visits.access','content.access','media.access'],
                'creative' => ['dashboard.view','tasks.manage','content.manage','dashboard.access','calendar.access','tasks.access','time.access','visits.access','content.access','media.access'],
                'developer' => ['dashboard.view','clients.manage','projects.manage','tasks.manage','dashboard.access','calendar.access','clients.access','projects.access','tasks.access','time.access'],
                'employee' => ['dashboard.view','tasks.manage','dashboard.access','calendar.access','tasks.access','time.access'],
            ];
            foreach ($permissionGroups as $roleSlug => $slugs) {
                $roleId = (int) $db->scalar('SELECT id FROM roles WHERE slug = ?', [$roleSlug]);
                foreach ($slugs as $slug) {
                    $permissionId = (int) $db->scalar('SELECT id FROM permissions WHERE slug = ?', [$slug]);
                    $db->insert('role_permissions', ['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }

            $adminName = (string) env('SEED_ADMIN_NAME', 'Jordan Blake');
            $adminEmail = (string) env('SEED_ADMIN_EMAIL', 'admin@agencyos.local');
            $adminPassword = (string) env('SEED_ADMIN_PASSWORD', '');
            if ($adminPassword === '') {
                if (app()->environment('production')) {
                    throw new \RuntimeException('SEED_ADMIN_PASSWORD must be configured before production seeding.');
                }
                $adminPassword = 'Admin@360!';
            }

            $superRole = (int) $db->scalar('SELECT id FROM roles WHERE slug = ?', ['super_admin']);
            $adminId = $db->insert('users', [
                'role_id' => $superRole, 'name' => $adminName, 'email' => $adminEmail,
                'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT), 'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $adminEmployee = $db->insert('employees', [
                'user_id' => $adminId, 'name' => $adminName, 'email' => $adminEmail,
                'phone' => '+1 416 555 0100', 'job_title' => 'Agency Director', 'department' => 'Administration',
                'skills' => 'Strategy, Client Success, Operations', 'hourly_cost' => 85, 'capacity_hours' => 40,
                'status' => 'active', 'created_at' => $now,
            ]);
            $employees = [
                ['Maya Chen','maya@agencyos.local','Account Director','Sales',58,'Sales, Proposals, Strategy'],
                ['Noah Williams','noah@agencyos.local','Project Manager','Marketing',52,'Project Management, Campaigns'],
                ['Priya Shah','priya@agencyos.local','Senior Designer','Design',48,'Branding, UI Design, Illustration'],
                ['Ethan Cole','ethan@agencyos.local','Content Producer','Photography',44,'Photography, Video, Editing'],
                ['Ava Martinez','ava@agencyos.local','Full-stack Developer','Development',55,'PHP, JavaScript, E-commerce'],
            ];
            $employeeIds = [];
            foreach ($employees as $employee) {
                $employeeIds[$employee[0]] = $db->insert('employees', [
                    'name' => $employee[0], 'email' => $employee[1], 'job_title' => $employee[2],
                    'department' => $employee[3], 'hourly_cost' => $employee[4], 'capacity_hours' => 40,
                    'skills' => $employee[5], 'status' => 'active', 'created_at' => $now,
                ]);
            }

            foreach ([['Small Business',1,19],['Medium Business',20,99],['Large Business',100,null]] as $size) {
                $db->insert('business_sizes', ['name' => $size[0], 'min_employees' => $size[1], 'max_employees' => $size[2], 'active' => 1]);
            }
            foreach (['Website','Google','Instagram','Facebook','Referral','Cold outreach','Phone','Email','Event','Existing client','Other'] as $source) {
                $db->insert('lead_sources', ['name' => $source, 'active' => 1]);
            }
            $stages = [
                ['New Lead','new',1,10,'info',0],['Contacted','contacted',2,20,'primary',0],
                ['Qualified','qualified',3,55,'success',0],['Discovery','discovery',4,65,'warning',0],
                ['Proposal Sent','proposal',5,75,'purple',0],['Negotiation','negotiation',6,90,'warning',0],
                ['Won','won',7,100,'success',1],['Lost','lost',8,0,'danger',1],
            ];
            foreach ($stages as $stage) {
                $db->insert('pipeline_stages', ['name'=>$stage[0],'slug'=>$stage[1],'position'=>$stage[2],'win_probability'=>$stage[3],'color'=>$stage[4],'is_closed'=>$stage[5]]);
            }

            $categoryIds = [];
            foreach (['Web & E-commerce','Brand & Creative','Social & Content','Search & Reputation','Strategy & Consulting'] as $category) {
                $categoryIds[$category] = $db->insert('service_categories', ['name' => $category, 'active' => 1]);
            }
            $services = [
                ['Website Design','Web & E-commerce','per_project',3495,1350,32],
                ['Website Development','Web & E-commerce','per_project',6995,2950,70],
                ['Website Maintenance','Web & E-commerce','monthly',495,190,5],
                ['E-commerce Development','Web & E-commerce','per_project',8995,3800,90],
                ['Branding','Brand & Creative','per_project',3495,1280,30],
                ['Logo Design','Brand & Creative','one_time',1495,520,12],
                ['Photography','Brand & Creative','per_visit',650,280,5],
                ['Videography','Brand & Creative','per_visit',950,440,7],
                ['Social Media Management','Social & Content','monthly',1495,610,20],
                ['Social Media Strategy','Social & Content','one_time',1250,420,10],
                ['Content Creation','Social & Content','monthly',1295,540,18],
                ['SEO','Search & Reputation','monthly',995,390,12],
                ['Google Business Management','Search & Reputation','monthly',495,175,5],
                ['Google Review Management','Search & Reputation','monthly',395,130,4],
                ['Business Strategy','Strategy & Consulting','hourly',225,85,1],
                ['Marketing Strategy','Strategy & Consulting','per_project',2495,850,20],
                ['Business Development','Strategy & Consulting','monthly',1995,780,20],
                ['Consulting','Strategy & Consulting','hourly',225,85,1],
            ];
            $serviceIds = [];
            foreach ($services as $service) {
                $serviceIds[$service[0]] = $db->insert('services', [
                    'category_id'=>$categoryIds[$service[1]], 'name'=>$service[0], 'description'=>$service[0] . ' delivered by the agency team.',
                    'pricing_type'=>$service[2], 'default_price'=>$service[3], 'cost_estimate'=>$service[4],
                    'estimated_hours'=>$service[5], 'taxable'=>1, 'active'=>1, 'created_at'=>$now,
                ]);
            }

            $packages = [
                ['Small Business Launch','new_business',2995,0,0],['Medium Business Launch','new_business',4995,0,0],['Large Business Launch','new_business',7500,0,0],
                ['Small Business Refresh','business_refresh',1995,0,0],['Medium Business Refresh','business_refresh',3495,0,0],['Large Business Refresh','business_refresh',0,0,0],
                ['Starter Website','website',1495,0,0],['Growth Website','website',3495,0,1],['Premium Website','website',6995,0,0],
                ['Essential','monthly',0,1495,0],['Growth','monthly',0,2495,1],['Premium','monthly',0,3995,0],
            ];
            $packageIds = [];
            foreach ($packages as $package) {
                $packageIds[$package[0]] = $db->insert('packages', [
                    'name'=>$package[0], 'package_type'=>$package[1], 'description'=>'A measurable, outcome-focused ' . strtolower(str_replace('_',' ',$package[1])) . ' package.',
                    'featured'=>$package[4], 'active'=>1, 'created_at'=>$now, 'updated_at'=>$now,
                ]);
                $db->insert('package_pricing', [
                    'package_id'=>$packageIds[$package[0]], 'business_size_id'=>null, 'base_price'=>$package[2],
                    'monthly_fee'=>$package[3], 'setup_fee'=>0, 'minimum_price'=>($package[2] ?: $package[3]),
                    'maximum_price'=>null, 'discount_percent'=>0, 'tax_percent'=>0, 'deposit_percent'=>30,
                    'effective_from'=>date('Y-m-01'), 'effective_to'=>null,
                ]);
            }
            $monthlyPackageDetails = [
                'Essential' => [3,3,350,['Social Media Management','Content Creation','Google Business Management']],
                'Growth' => [4,4,300,['Social Media Management','Content Creation','Photography','Videography','Google Business Management']],
                'Premium' => [6,4,200,['Social Media Management','Content Creation','Photography','Videography','SEO','Website Maintenance','Google Review Management']],
            ];
            foreach ($monthlyPackageDetails as $packageName => $detail) {
                $packageId = $packageIds[$packageName];
                $db->insert('package_limits', ['package_id'=>$packageId,'limit_key'=>'content_visits','label'=>'On-site content visits','included_quantity'=>$detail[0],'unit'=>'visits','overage_price'=>$detail[2],'period'=>'month']);
                $db->insert('package_limits', ['package_id'=>$packageId,'limit_key'=>'visit_duration','label'=>'Maximum visit duration','included_quantity'=>$detail[1],'unit'=>'hours','overage_price'=>0,'period'=>'visit']);
                foreach ($detail[3] as $serviceName) {
                    $service = $db->first('SELECT id, cost_estimate, estimated_hours FROM services WHERE name = ?', [$serviceName]);
                    $db->insert('package_items', ['package_id'=>$packageId,'service_id'=>$service['id'],'quantity'=>1,'scope_note'=>'Included in monthly scope','estimated_cost'=>$service['cost_estimate'],'estimated_hours'=>$service['estimated_hours']]);
                }
            }
            foreach ([['Additional content visit','per_visit',300,140],['Additional website page','one_time',350,160],['Additional revision round','one_time',225,95],['Additional social platform','monthly',395,145],['Additional development hour','hourly',175,55]] as $addon) {
                $db->insert('add_ons', ['name'=>$addon[0],'pricing_type'=>$addon[1],'price'=>$addon[2],'cost_estimate'=>$addon[3],'active'=>1]);
            }

            $smallSize = (int) $db->scalar('SELECT id FROM business_sizes WHERE name = ?', ['Small Business']);
            $mediumSize = (int) $db->scalar('SELECT id FROM business_sizes WHERE name = ?', ['Medium Business']);
            $clientData = [
                ['Northstar Coffee Roasters','Northstar Coffee','Hospitality',12,'Sofia Reyes','sofia@northstar.example','healthy',$employeeIds['Maya Chen']],
                ['Cedar & Stone Interiors','Cedar & Stone','Interior Design',24,'Daniel Kim','daniel@cedarstone.example','healthy',$employeeIds['Noah Williams']],
                ['Harborview Dental Group','Harborview Dental','Healthcare',38,'Dr. Lena Brooks','lena@harborview.example','attention',$employeeIds['Maya Chen']],
                ['Summit Fitness Studio','Summit Fitness','Fitness',16,'Marcus Lee','marcus@summitfit.example','at_risk',$employeeIds['Noah Williams']],
            ];
            $clientIds = [];
            foreach ($clientData as $index => $client) {
                $businessId = $db->insert('businesses', ['business_size_id'=>$client[3] >= 20 ? $mediumSize : $smallSize,'name'=>$client[0],'legal_name'=>$client[0] . ' Inc.','industry'=>$client[2],'website'=>'https://' . strtolower(str_replace([' ','&'],['',''],explode(' ',$client[1])[0])) . '.example','employee_count'=>$client[3],'years_in_business'=>5+$index,'created_at'=>$now]);
                $clientId = $db->insert('clients', ['business_id'=>$businessId,'account_manager_id'=>$client[7],'name'=>$client[4],'email'=>$client[5],'phone'=>'+1 416 555 01' . str_pad((string)(20+$index),2,'0',STR_PAD_LEFT),'status'=>'active','health'=>$client[6],'health_notes'=>$client[6] === 'healthy' ? null : 'Account manager follow-up required.','joined_at'=>date('Y-m-d', strtotime('-' . (2+$index) . ' months')),'created_at'=>$now]);
                $clientIds[$client[1]] = $clientId;
                $locationId = $db->insert('business_locations', ['business_id'=>$businessId,'name'=>'Main location','address'=>(100+$index*45) . ' King Street','city'=>'Toronto','state'=>'ON','postal_code'=>'M5V 1A1','primary_location'=>1]);
                $db->insert('contacts', ['client_id'=>$clientId,'name'=>$client[4],'title'=>'Owner','email'=>$client[5],'phone'=>'+1 416 555 0101','primary_contact'=>1]);
            }

            $subscriptions = [
                ['Northstar Coffee','Growth',2495,'2026-12-01'],['Cedar & Stone','Premium',3995,'2026-10-15'],
                ['Harborview Dental','Growth',2495,'2026-09-02'],['Summit Fitness','Essential',1495,'2026-08-28'],
            ];
            $subscriptionIds = [];
            foreach ($subscriptions as $subscription) {
                $subscriptionIds[$subscription[0]] = $db->insert('subscriptions', ['client_id'=>$clientIds[$subscription[0]],'package_id'=>$packageIds[$subscription[1]],'monthly_price'=>$subscription[2],'start_date'=>date('Y-m-01',strtotime('-3 months')),'renewal_date'=>$subscription[3],'contract_end_date'=>$subscription[3],'billing_frequency'=>'monthly','deposit'=>0,'discount_percent'=>0,'tax_percent'=>0,'status'=>'active','created_at'=>$now]);
            }

            $sourceReferral = (int) $db->scalar('SELECT id FROM lead_sources WHERE name = ?', ['Referral']);
            $sourceWebsite = (int) $db->scalar('SELECT id FROM lead_sources WHERE name = ?', ['Website']);
            $leadRows = [
                ['Olivia','Grant','Lumen Wellness','olivia@lumenwellness.example','Wellness',12000,'qualified',82,$sourceReferral,'SEO, Website Development'],
                ['Theo','Morgan','Oakline Legal','theo@oakline.example','Legal',8000,'contacted',64,$sourceWebsite,'Branding, Website Design'],
                ['Amara','Singh','Bloom Pediatrics','amara@bloom.example','Healthcare',18000,'proposal',91,$sourceReferral,'Social Media Management, Content Creation'],
                ['Lucas','Ford','Fieldwork Supply Co.','lucas@fieldwork.example','Retail',24000,'new',55,$sourceWebsite,'E-commerce Development'],
            ];
            $leadIds = [];
            foreach ($leadRows as $i => $lead) {
                $leadIds[$lead[2]] = $db->insert('leads', ['first_name'=>$lead[0],'last_name'=>$lead[1],'company_name'=>$lead[2],'email'=>$lead[3],'industry'=>$lead[4],'source_id'=>$lead[8],'status'=>$lead[6],'lead_score'=>$lead[7],'assigned_employee_id'=>$employeeIds['Maya Chen'],'estimated_budget'=>$lead[5],'services_interested'=>$lead[9],'notes'=>'Interested in a focused growth plan.','next_follow_up_at'=>date('Y-m-d',strtotime('+' . ($i+1) . ' days')),'last_contact_at'=>date('Y-m-d',strtotime('-' . ($i+1) . ' days')),'created_at'=>$now,'updated_at'=>$now]);
            }
            foreach ([['Lumen Wellness','Qualified',12000,55],['Oakline Legal','Contacted',8000,20],['Bloom Pediatrics','Proposal Sent',18000,70],['Fieldwork Supply Co.','New Lead',24000,10]] as $opportunity) {
                $stageId = (int) $db->scalar('SELECT id FROM pipeline_stages WHERE name = ?', [$opportunity[1]]);
                $db->insert('opportunities', ['lead_id'=>$leadIds[$opportunity[0]],'stage_id'=>$stageId,'owner_id'=>$employeeIds['Maya Chen'],'title'=>$opportunity[0] . ' engagement','estimated_value'=>$opportunity[2],'services'=>'Growth services','probability'=>$opportunity[3],'expected_close_date'=>date('Y-m-d',strtotime('+'.($opportunity[3] % 25 + 7).' days')),'next_action'=>'Confirm scope and decision process','created_at'=>$now,'updated_at'=>$now]);
            }

            $projectRows = [
                ['Northstar Coffee','Autumn Content Campaign','Marketing',12000,180,'active','high',$employeeIds['Noah Williams']],
                ['Cedar & Stone','Portfolio Website Rebuild','Website',14500,210,'active','high',$employeeIds['Ava Martinez']],
                ['Harborview Dental','Local Search Growth','SEO',9000,120,'review','medium',$employeeIds['Maya Chen']],
                ['Summit Fitness','Member Acquisition Sprint','Social Media',6500,95,'waiting','urgent',$employeeIds['Noah Williams']],
            ];
            $projectIds = [];
            foreach ($projectRows as $i => $project) {
                $projectIds[$project[0]] = $db->insert('projects', ['client_id'=>$clientIds[$project[0]],'package_id'=>$packageIds[$subscriptions[$i][1]],'manager_id'=>$project[7],'name'=>$project[1],'project_type'=>$project[2],'start_date'=>date('Y-m-d',strtotime('-25 days')),'deadline'=>date('Y-m-d',strtotime('+'.(6+$i*8).' days')),'budget'=>$project[3],'estimated_hours'=>$project[4],'actual_hours'=>20+$i*11,'status'=>$project[5],'priority'=>$project[6],'notes'=>'Client-visible milestones are tracked weekly.','created_at'=>$now]);
            }
            $taskRows = [
                ['Northstar Coffee','Edit fall menu launch reel',$employeeIds['Ethan Cole'],'in_progress','high',6],
                ['Northstar Coffee','Approve September content plan',$employeeIds['Noah Williams'],'review','medium',3],
                ['Cedar & Stone','Build project gallery',$employeeIds['Ava Martinez'],'in_progress','high',18],
                ['Cedar & Stone','Finalize mobile UI',$employeeIds['Priya Shah'],'todo','high',12],
                ['Harborview Dental','Review location keyword map',$employeeIds['Maya Chen'],'review','medium',5],
                ['Summit Fitness','Request campaign feedback',$employeeIds['Noah Williams'],'waiting','urgent',2],
            ];
            foreach ($taskRows as $i => $task) {
                $db->insert('project_tasks', ['project_id'=>$projectIds[$task[0]],'client_id'=>$clientIds[$task[0]],'assigned_employee_id'=>$task[2],'title'=>$task[1],'description'=>'Complete the assigned deliverable and attach final notes.','due_date'=>date('Y-m-d',strtotime(($i === 5 ? '-1' : '+'.($i+1)).' days')),'priority'=>$task[4],'status'=>$task[3],'estimated_hours'=>$task[5],'actual_hours'=>max(1,$task[5]-2),'created_at'=>$now]);
            }
            foreach ([['Northstar Coffee',$employeeIds['Ethan Cole'],7.5],['Cedar & Stone',$employeeIds['Ava Martinez'],8],['Harborview Dental',$employeeIds['Maya Chen'],4.5]] as $entry) {
                $db->insert('time_entries', ['employee_id'=>$entry[1],'client_id'=>$clientIds[$entry[0]],'project_id'=>$projectIds[$entry[0]],'task_id'=>null,'entry_date'=>date('Y-m-d',strtotime('-1 day')),'hours'=>$entry[2],'description'=>'Production and project delivery work','billable'=>1,'created_at'=>$now]);
            }

            foreach ([
                ['Northstar Coffee',-7,'completed','Reels, product photography'],['Northstar Coffee',3,'confirmed','Seasonal campaign'],
                ['Cedar & Stone',1,'confirmed','Portfolio photography'],['Harborview Dental',5,'scheduled','Staff content, testimonials'],
                ['Summit Fitness',-3,'completed','Promotional video'],
            ] as $visit) {
                $db->insert('content_visits', ['client_id'=>$clientIds[$visit[0]],'subscription_id'=>$subscriptionIds[$visit[0]],'assigned_employee_id'=>$employeeIds['Ethan Cole'],'visit_date'=>date('Y-m-d',strtotime(($visit[1] >= 0 ? '+' : '').$visit[1].' days')),'start_time'=>'10:00','end_time'=>'13:00','visit_type'=>'Content production','status'=>$visit[2],'purpose'=>$visit[3],'equipment'=>'Camera kit, lighting, audio','content_captured'=>$visit[2] === 'completed' ? 'Photos and short-form video' : null,'is_additional'=>0,'additional_charge'=>0,'created_at'=>$now]);
            }
            foreach ([
                ['Northstar Coffee','Autumn roast carousel','Instagram','Carousel','internal_review',2],
                ['Cedar & Stone','Before and after reel','Instagram','Reel','client_review',4],
                ['Harborview Dental','Meet the hygienist','Facebook','Video','approved',5],
                ['Summit Fitness','Fall challenge launch','TikTok','Video','draft',7],
            ] as $content) {
                $db->insert('content_items', ['client_id'=>$clientIds[$content[0]],'project_id'=>$projectIds[$content[0]],'assigned_employee_id'=>$employeeIds['Ethan Cole'],'title'=>$content[1],'platform'=>$content[2],'content_type'=>$content[3],'caption'=>'Draft campaign copy ready for review.','hashtags'=>'#localbusiness #growth','scheduled_at'=>date('Y-m-d 12:00:00',strtotime('+'.$content[5].' days')),'status'=>$content[4],'approval_status'=>$content[4] === 'approved' ? 'approved' : 'pending','created_at'=>$now]);
            }

            $invoiceRows = [
                ['INV-2026-1041','Northstar Coffee',2495,2495,'paid',-12],
                ['INV-2026-1042','Cedar & Stone',3995,1500,'partially_paid',-3],
                ['INV-2026-1043','Harborview Dental',2495,0,'overdue',-5],
                ['INV-2026-1044','Summit Fitness',1495,0,'sent',8],
            ];
            foreach ($invoiceRows as $invoice) {
                $invoiceId = $db->insert('invoices', ['invoice_number'=>$invoice[0],'client_id'=>$clientIds[$invoice[1]],'project_id'=>$projectIds[$invoice[1]],'package_id'=>(int)$db->scalar('SELECT package_id FROM subscriptions WHERE id = ?',[$subscriptionIds[$invoice[1]]]),'issue_date'=>date('Y-m-d',strtotime('-20 days')),'due_date'=>date('Y-m-d',strtotime(($invoice[5] >= 0 ? '+' : '').$invoice[5].' days')),'subtotal'=>$invoice[2],'discount'=>0,'tax'=>0,'total'=>$invoice[2],'amount_paid'=>$invoice[3],'status'=>$invoice[4],'notes'=>'Monthly agency services','created_at'=>$now]);
                $db->insert('invoice_items', ['invoice_id'=>$invoiceId,'description'=>'Monthly retainer services','quantity'=>1,'unit_price'=>$invoice[2],'total'=>$invoice[2]]);
                if ($invoice[3] > 0) {
                    $db->insert('payments', ['invoice_id'=>$invoiceId,'amount'=>$invoice[3],'payment_date'=>date('Y-m-d',strtotime('-4 days')),'method'=>'Bank transfer','reference'=>'PAY-' . substr($invoice[0],-4),'recorded_by'=>$adminId,'created_at'=>$now]);
                }
            }
            $proposalId = $db->insert('proposals', ['proposal_number'=>'PROP-2026-021','client_id'=>$clientIds['Harborview Dental'],'package_id'=>$packageIds['Growth'],'title'=>'Local Growth Partnership','summary'=>'A focused monthly growth engagement.','subtotal'=>2495,'discount'=>0,'tax'=>0,'total'=>2495,'deposit'=>750,'status'=>'sent','valid_until'=>date('Y-m-d',strtotime('+12 days')),'created_at'=>$now]);
            $db->insert('proposal_items', ['proposal_id'=>$proposalId,'description'=>'Growth monthly package','quantity'=>1,'unit_price'=>2495,'total'=>2495]);

            foreach ($clientIds as $businessName => $clientId) {
                $db->insert('activities', ['client_id'=>$clientId,'user_id'=>$adminId,'type'=>'client.created','description'=>'Client account created and onboarding started.','entity_type'=>'client','entity_id'=>$clientId,'created_at'=>date('c',strtotime('-30 days'))]);
                $db->insert('activities', ['client_id'=>$clientId,'user_id'=>$adminId,'type'=>'subscription.activated','description'=>'Monthly package activated.','entity_type'=>'subscription','entity_id'=>$subscriptionIds[$businessName],'created_at'=>date('c',strtotime('-22 days'))]);
            }
            $db->insert('notifications', ['user_id'=>$adminId,'type'=>'invoice.overdue','title'=>'Invoice INV-2026-1043 is overdue','body'=>'Harborview Dental has an outstanding balance of $2,495.','link'=>'invoices','created_at'=>$now]);
            $db->insert('notifications', ['user_id'=>$adminId,'type'=>'visit.upcoming','title'=>'Content visit tomorrow','body'=>'Cedar & Stone portfolio shoot is scheduled for tomorrow.','link'=>'visits','created_at'=>$now]);
        });
        self::ensureDemoExtensions($db);
        self::ensureQuoteStudio($db);
    }

    private static function ensureQuoteStudio(Database $db): void
    {
        $now = now()->toDateTimeString();
        self::ensureQuotePlans($db);
        $profiles = [
            'Website Design' => ['تصميم المواقع','עיצוב אתרים','UX/UI design, responsive page layouts, and a conversion-focused visual system.','تصميم UX/UI وتخطيطات متجاوبة ونظام بصري يركز على التحويل.','עיצוב UX/UI, פריסות רספונסיביות ומערכת חזותית ממוקדת המרה.'],
            'Website Development' => ['تطوير المواقع','פיתוח אתרים','Responsive front-end and CMS development, forms, analytics, launch, and quality assurance.','تطوير واجهة متجاوبة ونظام إدارة محتوى ونماذج وتحليلات وإطلاق واختبار جودة.','פיתוח רספונסיבי ו-CMS, טפסים, אנליטיקה, השקה ובקרת איכות.'],
            'Website Maintenance' => ['صيانة المواقع','תחזוקת אתרים','Monthly updates, backups, monitoring, and minor content changes.','تحديثات ونسخ احتياطي ومراقبة وتعديلات محتوى بسيطة شهرياً.','עדכונים, גיבויים, ניטור ושינויי תוכן קטנים מדי חודש.'],
            'E-commerce Development' => ['تطوير التجارة الإلكترونية','פיתוח מסחר אלקטרוני','Storefront, product catalogue, checkout, payment setup, and order workflow configuration.','واجهة متجر وكتالوج منتجات ودفع وإعداد بوابة الدفع وسير عمل الطلبات.','חנות, קטלוג מוצרים, תשלום, סליקה והגדרת תהליך הזמנות.'],
            'Branding' => ['الهوية التجارية','מיתוג','Brand strategy, visual direction, colour, typography, and core identity guidelines.','استراتيجية العلامة والاتجاه البصري والألوان والخطوط وإرشادات الهوية.','אסטרטגיית מותג, כיוון חזותי, צבע, טיפוגרפיה והנחיות זהות.'],
            'Logo Design' => ['تصميم الشعار','עיצוב לוגו','Primary logo concept, refinement rounds, and production-ready file formats.','مفهوم شعار أساسي وجولات تحسين وملفات نهائية جاهزة للاستخدام.','קונספט לוגו מרכזי, סבבי ליטוש וקבצים מוכנים לשימוש.'],
            'Photography' => ['التصوير الفوتوغرافي','צילום','Professional on-location photography, colour correction, and selected edited images.','تصوير احترافي في الموقع وتصحيح ألوان وتسليم صور مختارة ومعدلة.','צילום מקצועי במקום, תיקוני צבע ומסירת תמונות ערוכות נבחרות.'],
            'Videography' => ['تصوير الفيديو','וידאו','Professional capture, edit, sound polish, and social-ready video exports.','تصوير احترافي ومونتاج وتحسين صوت ونسخ جاهزة للمنصات الاجتماعية.','צילום מקצועי, עריכה, שיפור סאונד ויצוא מותאם לרשתות.'],
            'Social Media Management' => ['إدارة وسائل التواصل','ניהול רשתות חברתיות','Monthly publishing calendar, posting, community monitoring, and performance reporting.','تقويم نشر شهري وجدولة ومتابعة المجتمع وتقارير أداء.','לוח פרסום חודשי, העלאה, ניטור קהילה ודוחות ביצועים.'],
            'Social Media Strategy' => ['استراتيجية التواصل الاجتماعي','אסטרטגיית סושיאל','Audience, channel, content-pillar, cadence, and measurement plan.','خطة الجمهور والقنوات ومحاور المحتوى والوتيرة والقياس.','תוכנית קהל, ערוצים, עמודי תוכן, תדירות ומדידה.'],
            'Content Creation' => ['إنشاء المحتوى','יצירת תוכן','Branded copy and creative assets sized for the selected monthly channels.','نصوص وأصول إبداعية بهوية العلامة ومقاسات مناسبة للقنوات المختارة.','קופי ונכסים יצירתיים ממותגים ומותאמים לערוצים שנבחרו.'],
            'SEO' => ['تحسين محركات البحث','קידום אורגני','Technical and on-page SEO, keyword mapping, local optimization, and reporting.','تحسين تقني وداخلي وكلمات مفتاحية وتحسين محلي وتقارير.','SEO טכני ותוכן, מיפוי מילות מפתח, קידום מקומי ודוחות.'],
            'Google Business Management' => ['إدارة ملف Google للأعمال','ניהול Google Business','Profile optimization, updates, photo publishing, and insight monitoring.','تحسين الملف ونشر التحديثات والصور ومتابعة الإحصاءات.','אופטימיזציה לפרופיל, עדכונים, תמונות וניטור נתונים.'],
            'Google Review Management' => ['إدارة تقييمات Google','ניהול ביקורות Google','Review-request workflow, response guidance, and reputation monitoring.','سير طلب التقييمات وإرشادات الرد ومراقبة السمعة.','תהליך בקשת ביקורות, הנחיות תגובה וניטור מוניטין.'],
            'Business Strategy' => ['استراتيجية الأعمال','אסטרטגיה עסקית','Focused advisory sessions, priorities, road map, and documented next actions.','جلسات استشارية وأولويات وخارطة طريق وخطوات تالية موثقة.','פגישות ייעוץ, סדרי עדיפויות, מפת דרכים וצעדים מתועדים.'],
            'Marketing Strategy' => ['استراتيجية التسويق','אסטרטגיית שיווק','Market positioning, campaign plan, channel mix, budget direction, and KPIs.','تموضع السوق وخطة حملات ومزيج قنوات وتوجيه ميزانية ومؤشرات أداء.','מיצוב שוק, תוכנית קמפיינים, תמהיל ערוצים, תקציב ומדדים.'],
            'Business Development' => ['تطوير الأعمال','פיתוח עסקי','Pipeline planning, outreach structure, partnership targets, and sales enablement.','تخطيط مسار المبيعات وهيكل التواصل وأهداف الشراكات وتمكين المبيعات.','תכנון צינור מכירות, פנייה יזומה, יעדי שותפים ותמיכה במכירות.'],
            'Consulting' => ['الاستشارات','ייעוץ','Flexible expert advisory billed by the hour with written recommendations.','استشارات خبراء مرنة بالساعة مع توصيات مكتوبة.','ייעוץ מומחים גמיש לפי שעה עם המלצות כתובות.'],
        ];

        $sort = 10;
        foreach ($profiles as $name => [$nameAr,$nameHe,$description,$descriptionAr,$descriptionHe]) {
            $db->execute('UPDATE services SET name_ar=?,name_he=?,description=?,description_ar=?,description_he=?,quote_enabled=1,quote_sort=? WHERE name=?', [$nameAr,$nameHe,$description,$descriptionAr,$descriptionHe,$sort,$name]);
            $sort += 10;
        }

        $tiers = [
            'basic' => [
                'Basic Setup','الإعداد الأساسي','חבילת בסיס','A focused foundation for a small or new business.','أساس عملي لشركة صغيرة أو جديدة.','בסיס ממוקד לעסק קטן או חדש.',
                ['Logo Design'=>900,'Website Design'=>2200,'Website Development'=>2800,'Google Business Management'=>450,'Social Media Strategy'=>650],
            ],
            'medium' => [
                'Medium Setup','الإعداد المتوسط','חבילת ביניים','A growth-ready brand, website, search, and content system.','نظام علامة وموقع وبحث ومحتوى جاهز للنمو.','מערכת מותג, אתר, חיפוש ותוכן שמוכנה לצמיחה.',
                ['Branding'=>2800,'Logo Design'=>1100,'Website Design'=>3200,'Website Development'=>5200,'SEO'=>1200,'Google Business Management'=>550,'Social Media Strategy'=>950,'Content Creation'=>1400,'Photography'=>750],
            ],
            'pro' => [
                'Pro Setup','الإعداد الاحترافي','חבילת פרו','An advanced multi-channel system for scale, automation, and sustained growth.','نظام متقدم متعدد القنوات للتوسع والأتمتة والنمو المستمر.','מערכת רב-ערוצית מתקדמת לצמיחה, אוטומציה והתרחבות.',
                ['Branding'=>3495,'Logo Design'=>1495,'Website Design'=>4200,'Website Development'=>6995,'E-commerce Development'=>8995,'SEO'=>1800,'Google Business Management'=>750,'Google Review Management'=>595,'Social Media Strategy'=>1250,'Content Creation'=>2200,'Photography'=>950,'Videography'=>1450,'Marketing Strategy'=>2495,'Business Strategy'=>1350,'Business Development'=>1995],
            ],
        ];

        $tierGuidance = [
            'basic'=>[' Delivered as a focused single-location foundation with one primary workflow and essential launch handover.',' يتم تقديمها كأساس عملي لموقع واحد مع سير عمل رئيسي وتسليم أساسي عند الإطلاق.',' נמסר כבסיס ממוקד למיקום אחד, עם תהליך מרכזי והדרכת השקה בסיסית.'],
            'medium'=>[' Delivered with expanded multi-page scope, two key workflows, analytics, optimization, and team handover.',' يتم تقديمها بنطاق موسع متعدد الصفحات وسيري عمل أساسيين وتحليلات وتحسين وتسليم للفريق.',' נמסר בהיקף רב-עמודי מורחב, שני תהליכים מרכזיים, אנליטיקה, אופטימיזציה והדרכת צוות.'],
            'pro'=>[' Delivered as an advanced scalable system with custom workflows, integrations, automation, testing, and documented handover.',' يتم تقديمها كنظام متقدم قابل للتوسع مع مسارات مخصصة وتكاملات وأتمتة واختبار وتسليم موثق.',' נמסר כמערכת מתקדמת וניתנת להרחבה עם תהליכים מותאמים, אינטגרציות, אוטומציה, בדיקות ותיעוד.'],
        ];
        $developmentScopes = [
            'basic'=>['Up to 5 responsive pages in a CMS, contact form, Google Analytics, basic on-page SEO, device testing, launch, and a 60-minute handover.','حتى 5 صفحات متجاوبة على نظام إدارة محتوى ونموذج تواصل وتحليلات Google وتحسين أساسي واختبار وإطلاق وجلسة تسليم 60 دقيقة.','עד 5 עמודים רספונסיביים ב-CMS, טופס יצירת קשר, Google Analytics, SEO בסיסי, בדיקות, השקה והדרכה של 60 דקות.'],
            'medium'=>['Up to 10 CMS pages, blog/news, advanced forms, analytics and conversion events, speed optimization, staging, cross-device QA, launch, and team training.','حتى 10 صفحات ونظام مدونة ونماذج متقدمة وتحليلات للأهداف وتحسين سرعة وبيئة تجريب واختبار شامل وإطلاق وتدريب الفريق.','עד 10 עמודי CMS, בלוג, טפסים מתקדמים, אירועי המרה, שיפור מהירות, סביבת בדיקות, QA, השקה והדרכת צוות.'],
            'pro'=>['Up to 20 CMS pages with custom content structures, gated or multilingual sections, CRM/payment/API integrations, automation, accessibility and performance QA, deployment, documentation, and administrator training.','حتى 20 صفحة بهياكل محتوى مخصصة وأقسام محمية أو متعددة اللغات وتكامل CRM أو دفع أو API وأتمتة واختبار وصول وأداء ونشر وتوثيق وتدريب المدير.','עד 20 עמודי CMS עם מבני תוכן מותאמים, אזורים מוגנים או רב-לשוניים, אינטגרציות CRM/סליקה/API, אוטומציה, בדיקות נגישות וביצועים, תיעוד והדרכת מנהל.'],
        ];

        $order = 1;
        foreach ($tiers as $tier => [$name,$nameAr,$nameHe,$description,$descriptionAr,$descriptionHe,$items]) {
            $package = $db->first("SELECT id FROM packages WHERE package_type='quote_setup' AND tier=? LIMIT 1", [$tier]);
            if ($package) {
                $packageId = (int)$package['id'];
                $db->update('packages', $packageId, ['name'=>$name,'name_ar'=>$nameAr,'name_he'=>$nameHe,'description'=>$description,'description_ar'=>$descriptionAr,'description_he'=>$descriptionHe,'featured'=>$tier==='medium'?1:0,'active'=>1,'display_order'=>$order,'updated_at'=>$now]);
            } else {
                $packageId = $db->insert('packages', ['name'=>$name,'name_ar'=>$nameAr,'name_he'=>$nameHe,'package_type'=>'quote_setup','tier'=>$tier,'description'=>$description,'description_ar'=>$descriptionAr,'description_he'=>$descriptionHe,'featured'=>$tier==='medium'?1:0,'active'=>1,'display_order'=>$order,'created_at'=>$now,'updated_at'=>$now]);
            }

            $basePrice = array_sum($items);
            $pricing = $db->first('SELECT id FROM package_pricing WHERE package_id=? AND effective_to IS NULL ORDER BY id DESC LIMIT 1', [$packageId]);
            if ($pricing) {
                $db->update('package_pricing', (int)$pricing['id'], ['base_price'=>$basePrice,'minimum_price'=>$basePrice,'tax_percent'=>13]);
            } else {
                $db->insert('package_pricing', ['package_id'=>$packageId,'business_size_id'=>null,'base_price'=>$basePrice,'monthly_fee'=>0,'setup_fee'=>0,'minimum_price'=>$basePrice,'maximum_price'=>null,'discount_percent'=>0,'tax_percent'=>13,'deposit_percent'=>30,'effective_from'=>date('Y-m-d'),'effective_to'=>null]);
            }

            $db->execute('DELETE FROM package_items WHERE package_id=?', [$packageId]);
            $itemOrder = 1;
            foreach ($items as $serviceName => $price) {
                $service = $db->first('SELECT id,description,description_ar,description_he,cost_estimate,estimated_hours FROM services WHERE name=?', [$serviceName]);
                if (! $service) { continue; }
                $prefix = ucfirst($tier).' scope: ';
                $description = $serviceName === 'Website Development' ? $developmentScopes[$tier][0] : $prefix.$service['description'].$tierGuidance[$tier][0];
                $descriptionAr = $serviceName === 'Website Development' ? $developmentScopes[$tier][1] : ($tier==='basic'?'النطاق الأساسي: ':($tier==='medium'?'النطاق المتوسط: ':'النطاق الاحترافي: ')).$service['description_ar'].$tierGuidance[$tier][1];
                $descriptionHe = $serviceName === 'Website Development' ? $developmentScopes[$tier][2] : ($tier==='basic'?'היקף בסיסי: ':($tier==='medium'?'היקף ביניים: ':'היקף מקצועי: ')).$service['description_he'].$tierGuidance[$tier][2];
                $db->insert('package_items', [
                    'package_id'=>$packageId,'service_id'=>$service['id'],'quantity'=>1,'unit_price'=>$price,
                    'scope_note'=>$description,'description'=>$description,
                    'description_ar'=>$descriptionAr,
                    'description_he'=>$descriptionHe,
                    'included'=>1,'sort_order'=>$itemOrder,'estimated_cost'=>$service['cost_estimate'],'estimated_hours'=>$service['estimated_hours'],
                ]);
                $itemOrder++;
            }
            $order++;
        }
    }

    private static function ensureQuotePlans(Database $db): void
    {
        $supportPlans = [
            ['none','No support','بدون دعم','ללא תמיכה','No technical support commitment.','بدون التزام بالدعم الفني.','ללא התחייבות לתמיכה טכנית.',0,0,0,1],
            ['3_months','3 months','3 أشهر','3 חודשים','Priority technical support for three months.','دعم فني ذو أولوية لمدة ثلاثة أشهر.','תמיכה טכנית בעדיפות לשלושה חודשים.',25,1,3,2],
            ['6_months','6 months','6 أشهر','6 חודשים','Extended technical support for six months.','دعم فني ممتد لمدة ستة أشهر.','תמיכה טכנית מורחבת לשישה חודשים.',20,2,6,3],
            ['8_months','8 months','8 أشهر','8 חודשים','Continuity support across an eight-month delivery period.','دعم استمراري خلال فترة تنفيذ مدتها ثمانية أشهر.','תמיכת המשכיות לאורך תקופת ביצוע של שמונה חודשים.',18,3,8,4],
            ['12_months','12 months','12 شهراً','12 חודשים','Annual technical support and continuity coverage.','دعم فني سنوي وتغطية استمرارية.','תמיכה טכנית שנתית וכיסוי המשכיות.',18,4,12,5],
            ['custom','Custom support','دعم مخصص','תמיכה מותאמת','Enter a plan name, duration, and percentage. The amount is calculated from the setup subtotal.','أدخل اسم الخطة والمدة والنسبة، ويتم احتساب المبلغ من مجموع الإعداد.','הזינו שם תוכנית, משך ואחוז. הסכום יחושב מסכום ההקמה.',0,0,0,99],
        ];
        foreach ($supportPlans as [$code,$name,$nameAr,$nameHe,$description,$descriptionAr,$descriptionHe,$rate,$multiplier,$months,$sort]) {
            $values=['name'=>$name,'name_ar'=>$nameAr,'name_he'=>$nameHe,'description'=>$description,'description_ar'=>$descriptionAr,'description_he'=>$descriptionHe,'rate_percent'=>$rate,'multiplier'=>$multiplier,'duration_months'=>$months,'active'=>1,'sort_order'=>$sort];
            $existing=$db->first('SELECT id FROM quote_support_plans WHERE code=?',[$code]);
            if($existing){$db->update('quote_support_plans',(int)$existing['id'],$values);}else{$db->insert('quote_support_plans',['code'=>$code]+$values);}
        }

        $memberships = [
            ['none','No membership','بدون عضوية','ללא חברות','Setup-only engagement.','تنفيذ الإعداد فقط.','התקשרות להקמה בלבד.',0,0,1],
            ['starter','Social Media Essential','التواصل الاجتماعي الأساسي','סושיאל Essential','One platform, 8 monthly posts, scheduling, community monitoring, and a monthly report.','منصة واحدة و8 منشورات شهرياً وجدولة ومتابعة المجتمع وتقرير شهري.','פלטפורמה אחת, 8 פוסטים בחודש, תזמון, ניטור קהילה ודוח חודשי.',995,0,2],
            ['growth','Social Media Growth','نمو التواصل الاجتماعي','סושיאל Growth','Two platforms, 12 monthly posts, short-form content, community management, and optimization.','منصتان و12 منشوراً شهرياً ومحتوى قصير وإدارة مجتمع وتحسين مستمر.','שתי פלטפורמות, 12 פוסטים בחודש, תוכן קצר, ניהול קהילה ואופטימיזציה.',1495,1,3],
            ['premium','Social Media Pro','التواصل الاجتماعي الاحترافي','סושיאל Pro','Three platforms, 20 monthly posts, video, active community management, campaigns, and strategy reporting.','ثلاث منصات و20 منشوراً شهرياً وفيديو وإدارة مجتمع نشطة وحملات وتقارير استراتيجية.','שלוש פלטפורמות, 20 פוסטים בחודש, וידאו, ניהול קהילה פעיל, קמפיינים ודוחות אסטרטגיים.',2495,0,4],
            ['custom','Custom social media plan','باقة تواصل اجتماعي مخصصة','תוכנית סושיאל מותאמת','Enter a custom plan name, monthly price, and term for this client.','أدخل اسم الباقة والسعر الشهري والمدة لهذا العميل.','הזינו שם תוכנית, מחיר חודשי ותקופה מותאמים ללקוח.',0,0,99],
        ];
        foreach ($memberships as [$code,$name,$nameAr,$nameHe,$description,$descriptionAr,$descriptionHe,$price,$featured,$sort]) {
            $values=['name'=>$name,'name_ar'=>$nameAr,'name_he'=>$nameHe,'description'=>$description,'description_ar'=>$descriptionAr,'description_he'=>$descriptionHe,'monthly_price'=>$price,'featured'=>$featured,'active'=>1,'sort_order'=>$sort];
            $existing=$db->first('SELECT id FROM quote_memberships WHERE code=?',[$code]);
            if($existing){$db->update('quote_memberships',(int)$existing['id'],$values);}else{$db->insert('quote_memberships',['code'=>$code]+$values);}
        }
        $db->execute("UPDATE quote_memberships SET active=0 WHERE code IN ('seo_reputation','content_creation')");
    }

    private static function ensureDemoExtensions(Database $db): void
    {
        if ((int)$db->scalar('SELECT COUNT(*) FROM contracts') === 0 && (int)$db->scalar('SELECT COUNT(*) FROM clients') > 0) {
            $clientId=(int)$db->scalar('SELECT id FROM clients ORDER BY id LIMIT 1');
            $subscription=$db->first("SELECT package_id,start_date,contract_end_date FROM subscriptions WHERE client_id=? AND status='active' LIMIT 1",[$clientId]);
            $projectId=$db->scalar('SELECT id FROM projects WHERE client_id=? ORDER BY id LIMIT 1',[$clientId]);
            $db->insert('contracts',['client_id'=>$clientId,'package_id'=>$subscription['package_id']??null,'project_id'=>$projectId?:null,'start_date'=>$subscription['start_date']??date('Y-m-d'),'end_date'=>$subscription['contract_end_date']??date('Y-m-d',strtotime('+1 year')),'payment_terms'=>'Monthly in advance, Net 14','renewal_type'=>'automatic','cancellation_terms'=>'30 days written notice before renewal.','scope'=>'Monthly marketing services and measurable content production allowances.','status'=>'active']);
        }
        if ((int)$db->scalar('SELECT COUNT(*) FROM consultations') === 0 && (int)$db->scalar('SELECT COUNT(*) FROM leads') > 0) {
            $leadId=(int)$db->scalar('SELECT id FROM leads ORDER BY lead_score DESC LIMIT 1');
            $employeeId=$db->scalar('SELECT id FROM employees ORDER BY id LIMIT 1');
            $db->insert('consultations',['lead_id'=>$leadId,'client_id'=>null,'business_goals'=>json_encode(['main_goal'=>'Increase qualified appointments','revenue_goal'=>'25% year-over-year growth','acquisition_goal'=>'40 new customers per month','expansion_plans'=>'Open a second location next year','challenges'=>'Inconsistent lead flow','competitors'=>'Three established local providers']),'marketing_snapshot'=>json_encode(['website'=>'Functional but dated','social_media'=>'Irregular posting','google_business'=>'Active','advertising'=>'Small Google Ads campaign','seo_status'=>'No formal strategy','budget'=>'$4,000/month','agency_experience'=>'Worked with freelancers previously']),'target_customer'=>json_encode(['target_customer'=>'Local families and professionals','geographic_market'=>'Greater Toronto Area','demographics'=>'Ages 28–55','problems'=>'Needs trusted local expertise','why_choose'=>'Responsive service and strong reputation']),'current_problems'=>json_encode(['Website outdated','Poor SEO','Low leads']),'desired_outcomes'=>json_encode(['More appointments','Better website','Better Google presence']),'notes'=>'Decision team wants a phased recommendation with measurable outcomes.','completed_by'=>$employeeId,'completed_at'=>date('c',strtotime('-2 days'))]);
        }
    }
}

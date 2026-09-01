<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AgencyService;
use App\Services\Auth;
use App\Services\Database;
use App\Services\QuoteStudioService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

final class AgencyController extends Controller
{
    private Database $db;
    private Auth $auth;
    private AgencyService $agency;
    private QuoteStudioService $quotes;

    public function __construct(Database $db, Auth $auth)
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->agency = new AgencyService($db, $auth);
        $this->quotes = new QuoteStudioService($db, $auth);
    }

    public function show(Request $request, string $module = 'dashboard'): mixed
    {
        $route = preg_replace('/[^a-z_]/', '', $module) ?: 'dashboard';
        $permissionMap = [
            'dashboard'=>'dashboard.access','leads'=>'leads.access','lead'=>'leads.access','pipeline'=>'pipeline.access','discovery'=>'discovery.access','clients'=>'clients.access','client'=>'clients.access','contracts'=>'contracts.access',
            'packages'=>'packages.access','services'=>'services.access','proposals'=>'proposals.access','projects'=>'projects.access','tasks'=>'tasks.access',
            'visits'=>'visits.access','content'=>'content.access','media'=>'media.access','media_download'=>'media.access','calendar'=>'calendar.access','invoices'=>'invoices.access','team'=>'team.access','time'=>'time.access',
            'reports'=>'reports.access','settings'=>'settings.access','audit'=>'audit.access','search'=>'dashboard.access',
            'quote_studio'=>'proposals.access','saved_quotes'=>'proposals.access','quote_view'=>'proposals.access','quote_settings'=>'settings.access',
        ];

        if (! isset($permissionMap[$route])) {
            return $this->laravelPage('error', ['title'=>'Page not found','message'=>'The requested workspace area does not exist.'], $route, 404);
        }
        if (! $this->auth->can($permissionMap[$route])) {
            return $this->laravelPage('error', ['title'=>'Access denied','message'=>'Your role does not have permission to open this area.'], $route, 403);
        }
        if ($route === 'media_download') {
            return $this->laravelDownloadMedia((int) ($request->query('id') ?? $request->route('extra', 0)));
        }

        $options = $this->agency->options();
        return match ($route) {
            'dashboard' => $this->laravelPage('dashboard', ['title'=>'Agency Command Center','data'=>$this->agency->dashboard()], $route),
            'leads' => $this->laravelPage('entity-list', array_merge($this->leadPage($options, $request->query()), ['rows'=>$this->agency->leads($request->query())]), $route),
            'lead' => ($lead = $this->agency->lead((int) ($request->query('id') ?? $request->route('extra', 0))))
                ? $this->laravelPage('lead', ['title'=>$lead['company_name'],'lead'=>$lead,'options'=>$options], $route)
                : $this->laravelPage('error', ['title'=>'Lead not found','message'=>'The lead record does not exist.'], $route, 404),
            'pipeline' => $this->laravelPage('pipeline', ['title'=>'Sales Pipeline','stages'=>$this->agency->pipeline($request->query()),'options'=>$options,'filters'=>$request->query()], $route),
            'discovery' => $this->laravelPage('discovery', ['title'=>'Discovery & Consultation','rows'=>$this->agency->consultations(),'options'=>$options,'selectedLeadId'=>(int)$request->query('lead_id',0)], $route),
            'clients' => $this->laravelPage('entity-list', array_merge($this->clientPage($options), ['rows'=>$this->agency->clients()]), $route),
            'client' => ($client = $this->agency->client((int) ($request->query('id') ?? $request->route('extra', 0))))
                ? $this->laravelPage('client', ['title'=>$client['business_name'],'client'=>$client,'options'=>$options], $route)
                : $this->laravelPage('error', ['title'=>'Client not found','message'=>'The client record does not exist.'], $route, 404),
            'packages' => $this->laravelPage('packages', ['title'=>'Package Builder','packages'=>$this->agency->packages(),'options'=>$options], $route),
            'services' => $this->laravelPage('entity-list', array_merge($this->servicePage($options), ['rows'=>$this->agency->services()]), $route),
            'proposals' => $this->laravelPage('entity-list', array_merge($this->proposalPage($options, (int)$request->query('opportunity_id', 0)), ['rows'=>$this->agency->proposals()]), $route),
            'contracts' => $this->laravelPage('entity-list', array_merge($this->contractPage($options), ['rows'=>$this->agency->contracts()]), $route),
            'projects' => $this->laravelPage('entity-list', array_merge($this->projectPage($options), ['rows'=>$this->agency->projects()]), $route),
            'tasks' => $this->laravelPage('entity-list', array_merge($this->taskPage($options), ['rows'=>$this->agency->tasks()]), $route),
            'visits' => $this->laravelPage('entity-list', array_merge($this->visitPage($options), ['rows'=>$this->agency->visits()]), $route),
            'content' => $this->laravelPage('entity-list', array_merge($this->contentPage($options), ['rows'=>$this->agency->content()]), $route),
            'media' => $this->laravelPage('media', ['title'=>'Media Library','rows'=>$this->agency->media(),'options'=>$options], $route),
            'calendar' => $this->calendarPage($request, $options, $route),
            'invoices' => $this->laravelPage('entity-list', array_merge($this->invoicePage($options), ['rows'=>$this->agency->invoices()]), $route),
            'team' => $this->laravelPage('entity-list', array_merge($this->teamPage($options), ['rows'=>$this->agency->employees()]), $route),
            'time' => $this->laravelPage('entity-list', array_merge($this->timePage($options), ['rows'=>$this->agency->timeEntries()]), $route),
            'reports' => $this->laravelPage('reports', ['title'=>'Agency Reports','reports'=>$this->agency->reports()], $route),
            'settings' => $this->laravelPage('settings', ['title'=>'Settings','options'=>$options,'services'=>$this->agency->services(),'stages'=>$this->agency->pipeline(),'roles'=>$this->agency->rolesWithPermissions(),'permissions'=>$this->agency->permissions(),'systemUsers'=>$this->agency->systemUsersWithAccess(),'navigationItems'=>$this->agency->navigationConfiguration()], $route),
            'audit' => $this->laravelPage('audit', ['title'=>'Audit Log','rows'=>$this->agency->auditLogs()], $route),
            'search' => $this->laravelPage('search', ['title'=>'Search','query'=>trim((string) $request->query('q', '')),'results'=>$this->agency->search(trim((string) $request->query('q', '')))], $route),
            'quote_studio' => $this->laravelPage('quote-studio', ['title'=>'New Quote','quoteData'=>$this->quotes->builderData((int)$request->query('id',0))], $route),
            'saved_quotes' => $this->laravelPage('saved-quotes', ['title'=>'Saved Quotes','quotes'=>$this->quotes->savedQuotes($request->query()),'filters'=>$request->query()], $route),
            'quote_view' => ($quote=$this->quotes->quote((int)$request->query('id',0)))
                ? $this->laravelPage('quote-view', ['title'=>$quote['proposal_number'],'quote'=>$quote,'company'=>config('quote_studio.company'),'locales'=>config('quote_studio.locales')], $route)
                : $this->laravelPage('error',['title'=>'Quote not found','message'=>'The requested quote does not exist.'],$route,404),
            'quote_settings' => $this->laravelPage('quote-settings', ['title'=>'Quote Settings','quoteSettings'=>$this->quotes->settingsData()], $route),
        };
    }

    public function publicQuote(Request $request, string $token): mixed
    {
        $quote=$this->quotes->quoteByToken($token);
        if(!$quote){abort(404);}
        return response()->view('agency.quote-public',['quote'=>$quote,'company'=>config('quote_studio.company'),'locales'=>config('quote_studio.locales'),'publicMode'=>true]);
    }

    public function setLanguage(Request $request): mixed
    {
        $locale = (string) $request->input('locale', config('ui_languages.default', 'en'));
        if (! array_key_exists($locale, (array) config('ui_languages.locales'))) {
            $locale = (string) config('ui_languages.default', 'en');
        }
        $request->session()->put('ui_locale', $locale);

        return redirect()->back();
    }

    public function action(Request $request, string $module = 'dashboard'): mixed
    {
        $route = $this->safeRoute(preg_replace('/[^a-z_]/', '', $module) ?: 'dashboard');
        $action = (string) $request->input('action', '');

        if ($action === 'logout') {
            $this->auth->logout();
            return redirect()->route('login')->with('success', 'You have been signed out securely.');
        }

        $input = $request->all();
        try {
            $upload = function () use ($request, $input): int {
                $file = $request->file('media_file');
                $legacyFile = $file ? ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$file->getPathname(),'size'=>$file->getSize(),'name'=>$file->getClientOriginalName()] : [];
                return $this->agency->uploadMedia($input, $legacyFile);
            };
            $handlers = [
                'create_lead'=>['leads.access', fn()=>$this->agency->createLead($input), 'leads', 'Lead created and added to the pipeline.'],
                'update_lead'=>['leads.access', fn()=>$this->agency->updateLead($input), 'lead', 'Lead details and qualification updated.'],
                'add_lead_followup'=>['leads.access', fn()=>$this->agency->addLeadFollowup($input), 'lead', 'Follow-up saved to the lead timeline.'],
                'update_lead_followup'=>['leads.access', fn()=>$this->agency->updateLeadFollowup($input), 'lead', 'Follow-up correction saved with an audit record.'],
                'create_consultation'=>['discovery.access', fn()=>$this->agency->createConsultation($input), 'discovery', 'Discovery consultation saved to the relationship record.'],
                'convert_lead'=>['leads.access', fn()=>$this->agency->convertLead((int)$input['lead_id']), 'clients', 'Lead converted into a client.'],
                'create_client'=>['clients.access', fn()=>$this->agency->createClient($input), 'clients', 'Client created successfully.'],
                'create_package'=>['packages.access', fn()=>$this->agency->createPackage($input), 'packages', 'Package, pricing, services, and limits saved.'],
                'create_service'=>['services.access', fn()=>$this->agency->createService($input), 'services', 'Service added to the catalog.'],
                'update_service'=>['settings.access', fn()=>$this->agency->updateService($input), 'settings', 'Service pricing updated successfully.'],
                'create_proposal'=>['proposals.access', fn()=>$this->agency->createProposal($input), 'proposals', 'Proposal drafted successfully.'],
                'create_contract'=>['contracts.access', fn()=>$this->agency->createContract($input), 'contracts', 'Contract created and linked to the client.'],
                'create_project'=>['projects.access', fn()=>$this->agency->createProject($input), 'projects', 'Project created successfully.'],
                'create_task'=>['tasks.access', fn()=>$this->agency->createTask($input), 'tasks', 'Task created successfully.'],
                'create_visit'=>['visits.access', fn()=>$this->agency->createVisit($input), 'visits', 'Content visit scheduled.'],
                'complete_visit'=>['visits.access', fn()=>$this->agency->completeVisit((int)$input['visit_id']), 'visits', 'Visit completed and allowance usage recalculated.'],
                'create_content'=>['content.access', fn()=>$this->agency->createContent($input), 'content', 'Content item created.'],
                'upload_media'=>['media.access', $upload, 'media', 'Media uploaded securely.'],
                'create_invoice'=>['invoices.access', fn()=>$this->agency->createInvoice($input), 'invoices', 'Invoice created and marked as sent.'],
                'record_payment'=>['invoices.access', fn()=>$this->agency->recordPayment($input), 'invoices', 'Payment recorded and balance updated.'],
                'create_employee'=>['team.access', fn()=>$this->agency->createEmployee($input), 'team', 'Employee created successfully.'],
                'log_time'=>['time.access', fn()=>$this->agency->logTime($input), 'time', 'Time entry saved and project hours updated.'],
                'update_opportunity'=>['pipeline.access', fn()=>$this->agency->updateOpportunity($input), 'pipeline', 'Opportunity details updated.'],
                'move_opportunity'=>['pipeline.access', fn()=>$this->agency->moveOpportunity($input), 'pipeline', 'Opportunity moved and related records synchronized.'],
                'task_status'=>['tasks.access', fn()=>$this->agency->updateSimpleStatus('task',(int)$input['id'],(string)$input['status']), 'tasks', 'Task status updated.'],
                'content_status'=>['content.access', fn()=>$this->agency->updateSimpleStatus('content',(int)$input['id'],(string)$input['status']), 'content', 'Content status updated.'],
                'content_approval'=>['content.access', fn()=>$this->agency->updateContentApproval((int)$input['id'],(string)$input['approval_status'],(string)($input['approval_comments']??'')), 'content', 'Content approval updated.'],
                'add_note'=>['clients.access', fn()=>$this->agency->addNote((int)$input['client_id'],(string)$input['body']), 'client', 'Note added.'],
                'update_health'=>['clients.access', fn()=>$this->agency->updateClientHealth((int)$input['client_id'],(string)$input['health'],(string)($input['health_notes']??'')), 'client', 'Client health updated.'],
                'assign_package'=>['packages.access', fn()=>$this->agency->assignPackage($input), 'client', 'Package assigned to the client.'],
                'update_package_pricing'=>['packages.access', fn()=>$this->agency->updatePackagePricing($input), 'packages', 'Package pricing updated with a new effective date.'],
                'create_role'=>['settings.access', fn()=>$this->agency->createRole($input), 'settings', 'Role created successfully.'],
                'update_role'=>['settings.access', fn()=>$this->agency->updateRole($input), 'settings', 'Role details and permissions updated.'],
                'update_role_permissions'=>['settings.access', fn()=>$this->agency->updateRolePermissions((int)$input['role_id'],(array)($input['permission_ids']??[])), 'settings', 'Role permissions updated.'],
                'update_user_access'=>['settings.access', fn()=>$this->agency->updateUserAccess($input), 'settings', 'User role and access overrides updated.'],
                'create_pipeline_stage'=>['settings.access', fn()=>$this->agency->createPipelineStage($input), 'settings', 'Pipeline stage created.'],
                'update_pipeline_stage'=>['settings.access', fn()=>$this->agency->updatePipelineStage($input), 'settings', 'Pipeline stage updated and probabilities synchronized.'],
                'save_quote'=>['proposals.access', fn()=>$this->quotes->save($input), 'quote_view', 'Quote saved. You can now review, print, or send it.'],
                'duplicate_quote'=>['proposals.access', fn()=>$this->quotes->duplicate((int)$input['proposal_id']), 'quote_studio', 'A new editable quote revision was created.'],
                'delete_quote'=>['proposals.access', fn()=>$this->quotes->deleteQuote((int)$input['proposal_id']), 'saved_quotes', 'Quote deleted successfully.'],
                'record_quote_delivery'=>['proposals.access', fn()=>$this->quotes->recordDelivery($input), 'quote_view', 'Quote delivery was recorded. You can send the prepared email draft now.'],
                'onboard_quote'=>['proposals.access', fn()=>$this->quotes->onboard((int)$input['proposal_id']), 'client', 'Quote accepted and the client was onboarded.'],
                'save_quote_service'=>['settings.access', fn()=>$this->quotes->saveService($input), 'quote_settings', 'Service catalog updated.'],
                'save_quote_package'=>['settings.access', fn()=>$this->quotes->savePackage($input), 'quote_settings', 'Setup package saved.'],
                'add_quote_package_item'=>['settings.access', fn()=>$this->quotes->addPackageItem($input), 'quote_settings', 'Service added to the setup package.'],
                'save_quote_package_item'=>['settings.access', fn()=>$this->quotes->savePackageItem($input), 'quote_settings', 'Package item price and description updated.'],
                'remove_quote_package_item'=>['settings.access', fn()=>$this->quotes->removePackageItem((int)$input['package_item_id']), 'quote_settings', 'Service removed from the setup package.'],
                'save_quote_plan'=>['settings.access', fn()=>$this->quotes->savePlan($input), 'quote_settings', 'Support or membership plan updated.'],
            ];
            if (! isset($handlers[$action])) { throw new InvalidArgumentException('Unsupported request.'); }
            [$permission,$handler,$successRoute,$message] = $handlers[$action];
            if (! $this->auth->can($permission)) { throw new InvalidArgumentException('Your role cannot perform this action.'); }
            $result = $handler();
            $parameters = [];
            if (in_array($action, ['add_note','update_health','assign_package'], true)) { $parameters['id'] = (int) $input['client_id']; }
            if (in_array($action, ['update_lead','add_lead_followup','update_lead_followup'], true)) { $parameters['id'] = (int) $input['lead_id']; }
            if ($action === 'convert_lead' && is_int($result)) { $successRoute = 'client'; $parameters['id'] = $result; }
            if ($action === 'move_opportunity' && is_int($result)) { $successRoute = 'client'; $parameters['id'] = $result; }
            if (in_array($action,['save_quote','duplicate_quote','record_quote_delivery'],true) && is_int($result)) { $parameters['id'] = $action==='record_quote_delivery' ? (int)$input['proposal_id'] : $result; }
            if ($action === 'onboard_quote' && is_int($result)) { $parameters['id'] = $result; }
            return redirect(agency_url($successRoute, $parameters))->with('success', $message);
        } catch (Throwable $exception) {
            report($exception);
            $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The request could not be completed. Please review the information and try again.';
            $parameters = $route === 'client' && ! empty($input['client_id']) ? ['id'=>(int)$input['client_id']] : [];
            if ($route === 'lead' && ! empty($input['lead_id'])) { $parameters = ['id'=>(int)$input['lead_id']]; }
            return redirect(agency_url($route, $parameters))->with('danger', $message)->withInput();
        }
    }

    private function calendarPage(Request $request, array $options, string $route): mixed
    {
        $candidate = (string) ($request->query('month') ?? $request->route('extra', ''));
        $month = preg_match('/^\d{4}-\d{2}$/', $candidate) ? $candidate : date('Y-m');
        return $this->laravelPage('calendar', ['title'=>'Agency Calendar','month'=>$month,'events'=>$this->agency->calendarEvents($month),'options'=>$options], $route);
    }

    private function laravelPage(string $view, array $data, string $route, int $status = 200): mixed
    {
        $flashes = [];
        foreach (['success', 'danger'] as $type) {
            if (session()->has($type)) { $flashes[] = ['type'=>$type, 'message'=>(string) session($type)]; }
        }
        return response()->view('layouts.app', array_merge($data, ['auth'=>$this->auth,'flashes'=>$flashes,'currentRoute'=>$route,'contentView'=>'agency.'.$view]), $status);
    }

    private function laravelDownloadMedia(int $id): mixed
    {
        $media = $this->db->first('SELECT * FROM media WHERE id=?', [$id]);
        if (! $media) { abort(404); }
        $base = realpath(storage_path('app/private/uploads'));
        $path = realpath(storage_path('app/private/uploads/'.$media['file_path']));
        if (! $base || ! $path || ! str_starts_with($path, $base.DIRECTORY_SEPARATOR) || ! is_file($path)) { abort(404); }
        return response()->download($path, $media['original_name'], ['Content-Type'=>$media['mime_type']]);
    }

    public function handle(): void
    {
        $route = preg_replace('/[^a-z_]/', '', (string)($_GET['route'] ?? 'dashboard')) ?: 'dashboard';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handlePost($route);
            return;
        }

        if (!$this->auth->check()) {
            $this->render('login', ['title' => 'Sign in', 'standalone' => true]);
            return;
        }

        if ($route === 'login') {
            $this->redirect('dashboard');
        }

        $permissionMap = [
            'dashboard'=>'dashboard.view','leads'=>'crm.manage','lead'=>'crm.manage','pipeline'=>'crm.manage','discovery'=>'crm.manage','clients'=>'clients.manage','client'=>'clients.manage','contracts'=>'clients.manage',
            'packages'=>'packages.manage','services'=>'packages.manage','proposals'=>'crm.manage','projects'=>'projects.manage','tasks'=>'tasks.manage',
            'visits'=>'content.manage','content'=>'content.manage','media'=>'content.manage','media_download'=>'content.manage','calendar'=>'dashboard.view','invoices'=>'finance.manage','team'=>'team.manage','time'=>'tasks.manage',
            'reports'=>'reports.view','settings'=>'settings.manage','audit'=>'settings.manage','search'=>'dashboard.view',
        ];
        if (isset($permissionMap[$route]) && !$this->auth->can($permissionMap[$route])) {
            http_response_code(403);
            $this->render('error', ['title'=>'Access denied','message'=>'Your role does not have permission to open this area.']);
            return;
        }

        $options = $this->agency->options();
        switch ($route) {
            case 'dashboard':
                $this->render('dashboard', ['title'=>'Agency Command Center','data'=>$this->agency->dashboard()]);
                break;
            case 'leads':
                $this->render('entity-list', array_merge($this->leadPage($options, $_GET), ['rows'=>$this->agency->leads($_GET)]));
                break;
            case 'lead':
                $lead = $this->agency->lead((int)($_GET['id'] ?? 0));
                if (!$lead) { http_response_code(404); $this->render('error',['title'=>'Lead not found','message'=>'The lead record does not exist.']); break; }
                $this->render('lead', ['title'=>$lead['company_name'],'lead'=>$lead,'options'=>$options]);
                break;
            case 'pipeline':
                $this->render('pipeline', ['title'=>'Sales Pipeline','stages'=>$this->agency->pipeline()]);
                break;
            case 'discovery':
                $this->render('discovery', ['title'=>'Discovery & Consultation','rows'=>$this->agency->consultations(),'options'=>$options,'selectedLeadId'=>(int)($_GET['lead_id']??0)]);
                break;
            case 'clients':
                $this->render('entity-list', array_merge($this->clientPage($options), ['rows'=>$this->agency->clients()]));
                break;
            case 'client':
                $client = $this->agency->client((int)($_GET['id'] ?? 0));
                if (!$client) { http_response_code(404); $this->render('error',['title'=>'Client not found','message'=>'The client record does not exist.']); break; }
                $this->render('client', ['title'=>$client['business_name'],'client'=>$client,'options'=>$options]);
                break;
            case 'packages':
                $this->render('packages', ['title'=>'Package Builder','packages'=>$this->agency->packages(),'options'=>$options]);
                break;
            case 'services':
                $this->render('entity-list', array_merge($this->servicePage($options), ['rows'=>$this->agency->services()]));
                break;
            case 'proposals':
                $this->render('entity-list', array_merge($this->proposalPage($options), ['rows'=>$this->agency->proposals()]));
                break;
            case 'contracts':
                $this->render('entity-list', array_merge($this->contractPage($options), ['rows'=>$this->agency->contracts()]));
                break;
            case 'projects':
                $this->render('entity-list', array_merge($this->projectPage($options), ['rows'=>$this->agency->projects()]));
                break;
            case 'tasks':
                $this->render('entity-list', array_merge($this->taskPage($options), ['rows'=>$this->agency->tasks()]));
                break;
            case 'visits':
                $this->render('entity-list', array_merge($this->visitPage($options), ['rows'=>$this->agency->visits()]));
                break;
            case 'content':
                $this->render('entity-list', array_merge($this->contentPage($options), ['rows'=>$this->agency->content()]));
                break;
            case 'media':
                $this->render('media', ['title'=>'Media Library','rows'=>$this->agency->media(),'options'=>$options]);
                break;
            case 'media_download':
                $this->downloadMedia((int)($_GET['id'] ?? 0));
                break;
            case 'calendar':
                $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? (string)$_GET['month'] : date('Y-m');
                $this->render('calendar', ['title'=>'Agency Calendar','month'=>$month,'events'=>$this->agency->calendarEvents($month),'options'=>$options]);
                break;
            case 'invoices':
                $this->render('entity-list', array_merge($this->invoicePage($options), ['rows'=>$this->agency->invoices()]));
                break;
            case 'team':
                $this->render('entity-list', array_merge($this->teamPage($options), ['rows'=>$this->agency->employees()]));
                break;
            case 'time':
                $this->render('entity-list', array_merge($this->timePage($options), ['rows'=>$this->agency->timeEntries()]));
                break;
            case 'reports':
                $this->render('reports', ['title'=>'Agency Reports','reports'=>$this->agency->reports()]);
                break;
            case 'settings':
                $this->render('settings', ['title'=>'Settings','options'=>$options,'services'=>$this->agency->services(),'stages'=>$this->agency->pipeline(),'roles'=>$this->agency->rolesWithPermissions(),'permissions'=>$this->agency->permissions()]);
                break;
            case 'audit':
                $this->render('audit', ['title'=>'Audit Log','rows'=>$this->agency->auditLogs()]);
                break;
            case 'search':
                $query = trim((string)($_GET['q'] ?? ''));
                $this->render('search', ['title'=>'Search','query'=>$query,'results'=>$this->agency->search($query)]);
                break;
            default:
                http_response_code(404);
                $this->render('error', ['title'=>'Page not found','message'=>'The requested workspace area does not exist.']);
        }
    }

    private function handlePost(string $route): void
    {
        $action = (string)($_POST['action'] ?? '');
        try {
            Csrf::verify($_POST['_token'] ?? null);
            if ($action === 'login') {
                if (!$this->auth->attempt((string)($_POST['email'] ?? ''),(string)($_POST['password'] ?? ''))) {
                    throw new InvalidArgumentException('The email or password is incorrect.');
                }
                flash('success','Welcome back. Your agency command center is ready.');
                $this->redirect('dashboard');
            }
            if ($action === 'logout') {
                $this->auth->logout();
                flash('success','You have been signed out securely.');
                $this->redirect('login');
            }
            if (!$this->auth->check()) {
                throw new InvalidArgumentException('Sign in to continue.');
            }

            $handlers = [
                'create_lead'=>['crm.manage', fn()=>$this->agency->createLead($_POST), 'leads', 'Lead created and added to the pipeline.'],
                'update_lead'=>['crm.manage', fn()=>$this->agency->updateLead($_POST), 'lead', 'Lead details and qualification updated.'],
                'add_lead_followup'=>['crm.manage', fn()=>$this->agency->addLeadFollowup($_POST), 'lead', 'Follow-up saved to the lead timeline.'],
                'update_lead_followup'=>['crm.manage', fn()=>$this->agency->updateLeadFollowup($_POST), 'lead', 'Follow-up correction saved with an audit record.'],
                'create_consultation'=>['crm.manage', fn()=>$this->agency->createConsultation($_POST), 'discovery', 'Discovery consultation saved to the relationship record.'],
                'convert_lead'=>['crm.manage', fn()=>$this->agency->convertLead((int)$_POST['lead_id']), 'clients', 'Lead converted into a client.'],
                'create_client'=>['clients.manage', fn()=>$this->agency->createClient($_POST), 'clients', 'Client created successfully.'],
                'create_package'=>['packages.manage', fn()=>$this->agency->createPackage($_POST), 'packages', 'Package, pricing, services, and limits saved.'],
                'create_service'=>['packages.manage', fn()=>$this->agency->createService($_POST), 'services', 'Service added to the catalog.'],
                'update_service'=>['settings.manage', fn()=>$this->agency->updateService($_POST), 'settings', 'Service pricing updated successfully.'],
                'create_proposal'=>['crm.manage', fn()=>$this->agency->createProposal($_POST), 'proposals', 'Proposal drafted successfully.'],
                'create_contract'=>['clients.manage', fn()=>$this->agency->createContract($_POST), 'contracts', 'Contract created and linked to the client.'],
                'create_project'=>['projects.manage', fn()=>$this->agency->createProject($_POST), 'projects', 'Project created successfully.'],
                'create_task'=>['tasks.manage', fn()=>$this->agency->createTask($_POST), 'tasks', 'Task created successfully.'],
                'create_visit'=>['content.manage', fn()=>$this->agency->createVisit($_POST), 'visits', 'Content visit scheduled.'],
                'complete_visit'=>['content.manage', fn()=>$this->agency->completeVisit((int)$_POST['visit_id']), 'visits', 'Visit completed and allowance usage recalculated.'],
                'create_content'=>['content.manage', fn()=>$this->agency->createContent($_POST), 'content', 'Content item created.'],
                'upload_media'=>['content.manage', fn()=>$this->agency->uploadMedia($_POST,$_FILES['media_file'] ?? []), 'media', 'Media uploaded securely.'],
                'create_invoice'=>['finance.manage', fn()=>$this->agency->createInvoice($_POST), 'invoices', 'Invoice created and marked as sent.'],
                'record_payment'=>['finance.manage', fn()=>$this->agency->recordPayment($_POST), 'invoices', 'Payment recorded and balance updated.'],
                'create_employee'=>['team.manage', fn()=>$this->agency->createEmployee($_POST), 'team', 'Employee created successfully.'],
                'log_time'=>['tasks.manage', fn()=>$this->agency->logTime($_POST), 'time', 'Time entry saved and project hours updated.'],
                'move_opportunity'=>['crm.manage', fn()=>$this->agency->moveOpportunity((int)$_POST['opportunity_id'],(int)$_POST['stage_id']), 'pipeline', 'Opportunity moved.'],
                'task_status'=>['tasks.manage', fn()=>$this->agency->updateSimpleStatus('task',(int)$_POST['id'],(string)$_POST['status']), 'tasks', 'Task status updated.'],
                'content_status'=>['content.manage', fn()=>$this->agency->updateSimpleStatus('content',(int)$_POST['id'],(string)$_POST['status']), 'content', 'Content status updated.'],
                'content_approval'=>['content.manage', fn()=>$this->agency->updateContentApproval((int)$_POST['id'],(string)$_POST['approval_status'],(string)($_POST['approval_comments']??'')), 'content', 'Content approval updated.'],
                'add_note'=>['clients.manage', fn()=>$this->agency->addNote((int)$_POST['client_id'],(string)$_POST['body']), 'client', 'Note added.'],
                'update_health'=>['clients.manage', fn()=>$this->agency->updateClientHealth((int)$_POST['client_id'],(string)$_POST['health'],(string)($_POST['health_notes'] ?? '')), 'client', 'Client health updated.'],
                'assign_package'=>['packages.manage', fn()=>$this->agency->assignPackage($_POST), 'client', 'Package assigned to the client.'],
                'update_package_pricing'=>['packages.manage', fn()=>$this->agency->updatePackagePricing($_POST), 'packages', 'Package pricing updated with a new effective date.'],
                'update_role'=>['settings.manage', fn()=>$this->agency->updateRole($_POST), 'settings', 'Role details and permissions updated.'],
                'update_role_permissions'=>['settings.manage', fn()=>$this->agency->updateRolePermissions((int)$_POST['role_id'],(array)($_POST['permission_ids']??[])), 'settings', 'Role permissions updated.'],
            ];
            if (!isset($handlers[$action])) {
                throw new InvalidArgumentException('Unsupported request.');
            }
            [$permission,$handler,$successRoute,$message] = $handlers[$action];
            if (!$this->auth->can($permission)) {
                throw new InvalidArgumentException('Your role cannot perform this action.');
            }
            $result = $handler();
            flash('success',$message);
            $params = [];
            if (in_array($action,['add_note','update_health','assign_package'],true)) { $params['id']=(int)$_POST['client_id']; }
            if (in_array($action,['update_lead','add_lead_followup','update_lead_followup'],true)) { $params['id']=(int)$_POST['lead_id']; }
            if ($action==='convert_lead' && is_int($result)) { $successRoute='client'; $params['id']=$result; }
            $this->redirect($successRoute,$params);
        } catch (Throwable $exception) {
            $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The request could not be completed. Please review the information and try again.';
            if (!($exception instanceof InvalidArgumentException)) {
                @file_put_contents(dirname(__DIR__).'/storage/app.log','['.date('c').'] '.$exception->getMessage().PHP_EOL,FILE_APPEND|LOCK_EX);
            }
            flash('danger',$message);
            $params = [];
            if (!empty($_POST['client_id']) && $route==='client') { $params['id']=(int)$_POST['client_id']; }
            if (!empty($_POST['lead_id']) && $route==='lead') { $params['id']=(int)$_POST['lead_id']; }
            $this->redirect($this->safeRoute($route),$params);
        }
    }

    private function render(string $view, array $viewData): void
    {
        $viewData['auth'] = $this->auth;
        $viewData['flashes'] = pull_flashes();
        extract($viewData, EXTR_SKIP);
        if (!empty($standalone)) {
            require dirname(__DIR__).'/resources/views/'.$view.'.php';
            return;
        }
        $contentView = dirname(__DIR__).'/resources/views/'.$view.'.php';
        require dirname(__DIR__).'/resources/views/layout.php';
    }

    private function redirect(string $route, array $params = []): void
    {
        header('Location: '.url($route,$params));
        exit;
    }

    private function safeRoute(string $route): string
    {
        $allowed = ['login','dashboard','leads','lead','pipeline','discovery','clients','client','packages','services','proposals','contracts','projects','tasks','visits','content','media','calendar','invoices','team','time','reports','settings','audit','quote_studio','saved_quotes','quote_view','quote_settings'];
        return in_array($route,$allowed,true) ? $route : 'dashboard';
    }

    private function leadPage(array $o, array $filters = []): array
    {
        return [
            'title'=>'Lead Management','subtitle'=>'Capture, qualify, and convert every opportunity.','route'=>'leads','addLabel'=>'New Lead','action'=>'create_lead',
            'columns'=>[['company_name','Company','lead_link'],['contact','Contact','lead_contact'],['source_name','Source','text'],['status','Status','badge'],['lead_score','Fit score','score'],['conversion_probability','Conversion','percent'],['estimated_budget','Budget','money'],['next_follow_up_at','Next follow-up','followup_due'],['owner_name','Owner','text']],
            'filters'=>$filters,'filterOptions'=>['employees'=>$o['employees'],'sources'=>$o['sources']],
            'fields'=>[
                ['first_name','First name','text',true],['last_name','Last name','text',true],
                ['company_name','Company','text',true],['email','Email','email',true],
                ['phone','Phone','text',false],['industry','Industry','text',false],
                ['source_id','Lead source','select',false,$o['sources']],['assigned_employee_id','Owner','select',false,$o['employees']],
                ['company_size_range','Company size','select',false,$this->simpleOptions(['1_10'=>'1–10 people','11_50'=>'11–50 people','51_100'=>'51–100 people','101_250'=>'101–250 people','251_plus'=>'251+ people'])],
                ['years_in_business_range','Years in business','select',false,$this->simpleOptions(['under_1'=>'Less than 1 year','1_2'=>'1–2 years','3_5'=>'3–5 years','6_10'=>'6–10 years','10_plus'=>'10+ years'])],
                ['business_stage','Business stage','select',false,$this->simpleOptions(['new_business'=>'New business','existing_business'=>'Existing business'])],
                ['budget_range','Estimated budget range','select',false,$this->simpleOptions(['under_1k'=>'Under $1,000','1k_5k'=>'$1,000–$5,000','5k_10k'=>'$5,000–$10,000','10k_25k'=>'$10,000–$25,000','25k_50k'=>'$25,000–$50,000','50k_plus'=>'$50,000+'])],
                ['service_ids','Services interested','multicheck',false,$o['services']],
                ['next_follow_up_at','Next follow-up','date',false],['notes','Notes','textarea',false],
                ['allow_duplicate','Allow creation if this is intentionally a separate opportunity','checkbox',false],
            ],
        ];
    }
    private function clientPage(array $o): array { $sizes=$this->db->all("SELECT id,name FROM business_sizes WHERE active=1"); return ['title'=>'Clients','subtitle'=>'Every relationship, engagement, and balance in one place.','route'=>'clients','addLabel'=>'New Client','action'=>'create_client','columns'=>[['business_name','Business','client_link'],['name','Primary contact','text'],['package_name','Package','text'],['monthly_price','Monthly value','money'],['health','Health','health'],['outstanding','Outstanding','money'],['account_manager','Account manager','text']], 'fields'=>[['business_name','Business name','text',true],['contact_name','Primary contact','text',true],['email','Email','email',true],['phone','Phone','text',false],['industry','Industry','text',false],['website','Website','url',false],['employee_count','Employees','number',false],['business_size_id','Business size','select',false,$sizes],['account_manager_id','Account manager','select',false,$o['employees']],['package_id','Starting package','select',false,$o['packages']],['joined_at','Start date','date',true,null,date('Y-m-d')]]]; }
    private function servicePage(array $o): array { return ['title'=>'Service Catalog','subtitle'=>'Central pricing and delivery assumptions used by packages.','route'=>'services','addLabel'=>'New Service','action'=>'create_service','columns'=>[['name','Service','strong'],['category_name','Category','text'],['pricing_type','Pricing type','human'],['default_price','Default price','money'],['cost_estimate','Est. cost','money'],['estimated_hours','Est. hours','hours'],['active','Status','active']], 'fields'=>[['name','Service name','text',true],['category_id','Category','select',true,$o['categories']],['pricing_type','Pricing type','select',true,$this->simpleOptions(['one_time'=>'One-time','monthly'=>'Monthly','hourly'=>'Hourly','per_visit'=>'Per visit','per_project'=>'Per project','custom'=>'Custom quote'])],['default_price','Default price','number',true,null,0],['cost_estimate','Estimated internal cost','number',true,null,0],['estimated_hours','Estimated hours','number',true,null,0],['description','Description','textarea',false]]]; }
    private function proposalPage(array $o, int $selectedOpportunityId = 0): array { return ['title'=>'Proposals','subtitle'=>'Scope and price engagements before work begins.','route'=>'proposals','addLabel'=>'New Proposal','action'=>'create_proposal','columns'=>[['proposal_number','Proposal','strong'],['business_name','Client / Lead','text'],['title','Engagement','text'],['package_name','Package','text'],['total','Total','money'],['status','Status','badge'],['valid_until','Valid until','date']], 'fields'=>[['opportunity_id','Sales opportunity','select',false,$o['opportunities'],$selectedOpportunityId],['client_id','Existing client (without opportunity)','select',false,$o['clients']],['package_id','Package','select',false,$o['packages']],['title','Proposal title','text',true],['summary','Executive summary','textarea',false],['description','Line item / scope','textarea',true],['amount','Subtotal','number',true],['discount','Discount','number',false,null,0],['tax_percent','Tax %','number',false,null,0],['deposit','Deposit','number',false,null,0],['valid_until','Valid until','date',true,null,date('Y-m-d',strtotime('+14 days'))]]]; }
    private function contractPage(array $o): array { return ['title'=>'Contracts','subtitle'=>'Control scope, terms, renewal dates, and client commitments.','route'=>'contracts','addLabel'=>'New Contract','action'=>'create_contract','columns'=>[['business_name','Client','strong'],['package_name','Package','text'],['project_name','Project','text'],['start_date','Start','date'],['end_date','End','date'],['days_left','Days left','days_left'],['renewal_type','Renewal','human'],['status','Status','badge']], 'fields'=>[['client_id','Client','select',true,$o['clients']],['package_id','Package','select',false,$o['packages']],['project_id','Project','select',false,$o['projects']],['start_date','Start date','date',true,null,date('Y-m-d')],['end_date','End date','date',false,null,date('Y-m-d',strtotime('+1 year'))],['status','Status','select',true,$this->simpleOptions(['draft'=>'Draft','active'=>'Active','expired'=>'Expired','terminated'=>'Terminated'])],['renewal_type','Renewal','select',true,$this->simpleOptions(['manual'=>'Manual','automatic'=>'Automatic','none'=>'No renewal'])],['payment_terms','Payment terms','textarea',false],['cancellation_terms','Cancellation terms','textarea',false],['scope','Scope','textarea',true]]]; }
    private function projectPage(array $o): array { return ['title'=>'Projects','subtitle'=>'Control scope, deadlines, hours, and delivery health.','route'=>'projects','addLabel'=>'New Project','action'=>'create_project','columns'=>[['name','Project','strong'],['business_name','Client','text'],['project_type','Type','text'],['status','Status','badge'],['priority','Priority','priority'],['deadline','Deadline','date'],['progress','Tasks','progress'],['budget','Budget','money'],['manager_name','Manager','text']], 'fields'=>[['client_id','Client','select',true,$o['clients']],['package_id','Package','select',false,$o['packages']],['manager_id','Project manager','select',false,$o['employees']],['name','Project name','text',true],['project_type','Type','select',true,$this->simpleOptions(array_combine(['Website','Branding','Social Media','Photography','Videography','SEO','Business Development','Marketing','E-commerce','Consulting'],['Website','Branding','Social Media','Photography','Videography','SEO','Business Development','Marketing','E-commerce','Consulting']))],['start_date','Start date','date',true,null,date('Y-m-d')],['deadline','Deadline','date',false],['budget','Budget','number',true],['estimated_hours','Estimated hours','number',true],['priority','Priority','select',true,$this->simpleOptions(['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'])],['notes','Notes','textarea',false]]]; }
    private function taskPage(array $o): array { return ['title'=>'Tasks','subtitle'=>'Keep every client deliverable moving to done.','route'=>'tasks','addLabel'=>'New Task','action'=>'create_task','columns'=>[['title','Task','strong'],['business_name','Client','text'],['project_name','Project','text'],['assignee','Assignee','text'],['status','Status','status_form'],['priority','Priority','priority'],['due_date','Due date','date'],['estimated_hours','Estimate','hours']], 'fields'=>[['project_id','Project','select',true,$o['projects']],['assigned_employee_id','Assignee','select',false,$o['employees']],['title','Task title','text',true],['description','Description','textarea',false],['due_date','Due date','date',false],['priority','Priority','select',true,$this->simpleOptions(['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'])],['estimated_hours','Estimated hours','number',false,null,0]]]; }
    private function visitPage(array $o): array { return ['title'=>'Content Visits','subtitle'=>'Schedule production and automatically meter package allowances.','route'=>'visits','addLabel'=>'Schedule Visit','action'=>'create_visit','columns'=>[['business_name','Client','text'],['visit_date','Date','date'],['time','Time','visit_time'],['purpose','Purpose','text'],['assignee','Assigned to','text'],['status','Status','badge'],['usage','Allowance','usage'],['additional_charge','Charge','money']], 'fields'=>[['client_id','Client','select',true,$o['clients']],['subscription_id','Subscription','select',false,$o['subscriptions']],['assigned_employee_id','Assigned employee','select',false,$o['employees']],['visit_date','Visit date','date',true],['start_time','Start time','time',false],['end_time','End time','time',false],['visit_type','Visit type','select',true,$this->simpleOptions(['Content production'=>'Content production','Photography'=>'Photography','Videography'=>'Videography','Campaign shoot'=>'Campaign shoot'])],['purpose','Purpose','textarea',false],['equipment','Equipment','text',false],['notes','Notes','textarea',false]]]; }
    private function contentPage(array $o): array { return ['title'=>'Content Calendar','subtitle'=>'Plan, approve, and publish content across every client.','route'=>'content','addLabel'=>'New Content','action'=>'create_content','columns'=>[['title','Content','strong'],['business_name','Client','text'],['platform','Platform','text'],['content_type','Format','text'],['scheduled_at','Scheduled','datetime'],['status','Status','content_status'],['approval_status','Approval','approval_form'],['assignee','Owner','text']], 'fields'=>[['client_id','Client','select',true,$o['clients']],['project_id','Project','select',false,$o['projects']],['assigned_employee_id','Assigned employee','select',false,$o['employees']],['title','Content title','text',true],['platform','Platform','select',true,$this->simpleOptions(array_combine(['Instagram','Facebook','TikTok','LinkedIn','Google Business','YouTube'],['Instagram','Facebook','TikTok','LinkedIn','Google Business','YouTube']))],['content_type','Format','select',true,$this->simpleOptions(array_combine(['Post','Carousel','Reel','Story','Video','Photo'],['Post','Carousel','Reel','Story','Video','Photo']))],['caption','Caption','textarea',false],['hashtags','Hashtags','text',false],['scheduled_at','Scheduled at','datetime-local',false]]]; }
    private function invoicePage(array $o): array { return ['title'=>'Invoices & Payments','subtitle'=>'Track billing, collections, and outstanding balances.','route'=>'invoices','addLabel'=>'New Invoice','action'=>'create_invoice','columns'=>[['invoice_number','Invoice','strong'],['business_name','Client','text'],['issue_date','Issued','date'],['due_date','Due','date'],['total','Total','money'],['amount_paid','Paid','money'],['amount_due','Balance','money'],['status','Status','invoice_status']], 'fields'=>[['client_id','Client','select',true,$o['clients']],['project_id','Project','select',false,$o['projects']],['package_id','Package','select',false,$o['packages']],['description','Line item','textarea',true],['amount','Subtotal','number',true],['discount','Discount','number',false,null,0],['tax_percent','Tax %','number',false,null,0],['due_date','Due date','date',true,null,date('Y-m-d',strtotime('+14 days'))],['notes','Notes','textarea',false]]]; }
    private function teamPage(array $o): array { return ['title'=>'Team','subtitle'=>'Capacity, access, cost, and active assignments.','route'=>'team','addLabel'=>'New Employee','action'=>'create_employee','columns'=>[['name','Employee','strong'],['job_title','Role','text'],['department','Department','text'],['skills','Skills','text'],['hourly_cost','Internal cost','money_hour'],['hours_week','Hours / 7d','hours'],['open_tasks','Open tasks','number'],['status','Status','badge']], 'fields'=>[['name','Full name','text',true],['email','Email','email',true],['phone','Phone','text',false],['job_title','Job title','text',false],['department','Department','select',true,$this->simpleOptions(array_combine(['Development','Design','Social Media','Photography','Videography','Sales','Marketing','Administration'],['Development','Design','Social Media','Photography','Videography','Sales','Marketing','Administration']))],['skills','Skills','textarea',false],['hourly_cost','Hourly internal cost','number',true],['capacity_hours','Weekly capacity','number',true,null,40],['create_login','Create system login','checkbox',false],['role_id','Login role','select',false,$o['roles']],['password','Temporary password','password',false]]]; }
    private function timePage(array $o): array { return ['title'=>'Time Tracking','subtitle'=>'Measure delivery effort and protect project profitability.','route'=>'time','addLabel'=>'Log Time','action'=>'log_time','columns'=>[['entry_date','Date','date'],['employee_name','Employee','text'],['business_name','Client','text'],['project_name','Project','text'],['description','Work performed','text'],['hours','Hours','hours'],['internal_cost','Internal cost','money'],['billable','Billable','yesno']], 'fields'=>[['employee_id','Employee','select',true,$o['employees']],['project_id','Project','select',true,$o['projects']],['entry_date','Date','date',true,null,date('Y-m-d')],['hours','Hours','number',true],['description','Work performed','textarea',true],['billable','Billable time','checkbox',false,null,1]]]; }
    private function simpleOptions(array $map): array { $result=[]; foreach($map as $id=>$name){$result[]=['id'=>$id,'name'=>$name];} return $result; }

    private function downloadMedia(int $id): void
    {
        $media=$this->agency->mediaItem($id);
        if(!$media){http_response_code(404);$this->render('error',['title'=>'File not found','message'=>'The requested media record does not exist.']);return;}
        $base=realpath(dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'uploads');
        $path=realpath(dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.$media['file_path']);
        if(!$base || !$path || strpos($path,$base.DIRECTORY_SEPARATOR)!==0 || !is_file($path)){http_response_code(404);$this->render('error',['title'=>'File unavailable','message'=>'The file is no longer available in storage.']);return;}
        header('Content-Type: '.$media['mime_type']);
        header('Content-Length: '.filesize($path));
        header('Content-Disposition: attachment; filename="'.str_replace(['"',"\r","\n"],'',basename($media['original_name'])).'"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}

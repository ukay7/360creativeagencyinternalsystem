<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class AgencyService
{
    private Database $db;
    private Auth $auth;

    public function __construct(Database $db, Auth $auth)
    {
        $this->db = $db;
        $this->auth = $auth;
    }

    public function dashboard(): array
    {
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $yearStart = date('Y-01-01');
        $today = date('Y-m-d');
        $weekEnd = date('Y-m-d', strtotime('+7 days'));

        return [
            'kpis' => [
                'mrr' => (float) $this->db->scalar("SELECT COALESCE(SUM(monthly_price),0) FROM subscriptions WHERE status = 'active'"),
                'revenue_month' => (float) $this->db->scalar('SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date BETWEEN ? AND ?', [$monthStart, $monthEnd]),
                'revenue_year' => (float) $this->db->scalar('SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date >= ?', [$yearStart]),
                'outstanding' => (float) $this->db->scalar("SELECT COALESCE(SUM(total - amount_paid),0) FROM invoices WHERE status NOT IN ('paid','cancelled')"),
                'pipeline' => (float) $this->db->scalar("SELECT COALESCE(SUM(o.estimated_value),0) FROM opportunities o JOIN pipeline_stages s ON s.id=o.stage_id WHERE s.is_closed=0"),
                'active_clients' => (int) $this->db->scalar("SELECT COUNT(*) FROM clients WHERE status='active'"),
                'open_projects' => (int) $this->db->scalar("SELECT COUNT(*) FROM projects WHERE status NOT IN ('completed','cancelled')"),
                'overdue_tasks' => (int) $this->db->scalar("SELECT COUNT(*) FROM project_tasks WHERE due_date < ? AND status != 'completed'", [$today]),
                'overdue_lead_followups' => (int) $this->db->scalar("SELECT COUNT(*) FROM leads WHERE converted_client_id IS NULL AND status!='lost' AND next_follow_up_at IS NOT NULL AND next_follow_up_at < NOW()"),
            ],
            'pipeline_stages' => $this->db->all("SELECT s.name, s.color, COUNT(o.id) AS deals, COALESCE(SUM(o.estimated_value),0) AS value FROM pipeline_stages s LEFT JOIN opportunities o ON o.stage_id=s.id WHERE s.is_closed=0 GROUP BY s.id ORDER BY s.position"),
            'revenue_by_client' => $this->db->all("SELECT b.name, COALESCE(SUM(p.amount),0) AS value FROM clients c JOIN businesses b ON b.id=c.business_id LEFT JOIN invoices i ON i.client_id=c.id LEFT JOIN payments p ON p.invoice_id=i.id GROUP BY c.id ORDER BY value DESC LIMIT 6"),
            'tasks' => $this->db->all("SELECT t.*, e.name AS assignee, b.name AS business_name FROM project_tasks t JOIN clients c ON c.id=t.client_id JOIN businesses b ON b.id=c.business_id LEFT JOIN employees e ON e.id=t.assigned_employee_id WHERE t.status != 'completed' ORDER BY CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 ELSE 3 END, t.due_date LIMIT 7"),
            'visits' => $this->db->all("SELECT v.*, b.name AS business_name, e.name AS assignee FROM content_visits v JOIN clients c ON c.id=v.client_id JOIN businesses b ON b.id=c.business_id LEFT JOIN employees e ON e.id=v.assigned_employee_id WHERE v.visit_date BETWEEN ? AND ? AND v.status NOT IN ('cancelled','completed') ORDER BY v.visit_date LIMIT 6", [$today, $weekEnd]),
            'renewals' => $this->db->all("SELECT s.*, b.name AS business_name, p.name AS package_name, DATEDIFF(s.renewal_date, ?) AS days_left FROM subscriptions s JOIN clients c ON c.id=s.client_id JOIN businesses b ON b.id=c.business_id JOIN packages p ON p.id=s.package_id WHERE s.status='active' AND s.renewal_date BETWEEN ? AND DATE_ADD(?, INTERVAL 45 DAY) ORDER BY s.renewal_date", [$today, $today, $today]),
            'notifications' => $this->db->all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5', [$this->auth->user()['id']]),
            'lead_followup_reminders' => $this->db->all("SELECT l.id,l.company_name,l.next_follow_up_at,l.lead_score,e.name AS owner_name,CASE WHEN l.next_follow_up_at<NOW() THEN 'overdue' ELSE 'today' END AS reminder_state FROM leads l LEFT JOIN employees e ON e.id=l.assigned_employee_id WHERE l.converted_client_id IS NULL AND l.status!='lost' AND l.next_follow_up_at IS NOT NULL AND l.next_follow_up_at<=DATE_ADD(NOW(),INTERVAL 1 DAY) ORDER BY l.next_follow_up_at LIMIT 8"),
        ];
    }

    public function leads(array $filters = []): array
    {
        $where = [];
        $parameters = [];
        if (! empty($filters['assigned_employee_id'])) { $where[]='l.assigned_employee_id=?'; $parameters[]=(int)$filters['assigned_employee_id']; }
        if (! empty($filters['source_id'])) { $where[]='l.source_id=?'; $parameters[]=(int)$filters['source_id']; }
        if (! empty($filters['status'])) { $where[]='l.status=?'; $parameters[]=(string)$filters['status']; }
        if (($filters['min_fit_score'] ?? '') !== '' && is_numeric($filters['min_fit_score'])) { $where[]='l.lead_score>=?'; $parameters[]=max(0,min(100,(int)$filters['min_fit_score'])); }
        switch ((string)($filters['follow_up_state'] ?? '')) {
            case 'overdue': $where[]="l.converted_client_id IS NULL AND l.status!='lost' AND l.next_follow_up_at IS NOT NULL AND l.next_follow_up_at<NOW()"; break;
            case 'due_today': $where[]="l.converted_client_id IS NULL AND l.status!='lost' AND DATE(l.next_follow_up_at)=CURDATE()"; break;
            case 'upcoming': $where[]="l.converted_client_id IS NULL AND l.status!='lost' AND l.next_follow_up_at>NOW()"; break;
            case 'unscheduled': $where[]="l.converted_client_id IS NULL AND l.status!='lost' AND l.next_follow_up_at IS NULL"; break;
        }
        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';

        return $this->db->all("SELECT l.*, COALESCE(si.service_names,l.services_interested) AS services_interested, ls.name AS source_name, e.name AS owner_name, COALESCE(ps.win_probability,0) AS conversion_probability, CASE WHEN l.converted_client_id IS NOT NULL THEN 'converted' WHEN l.status='lost' THEN 'closed' WHEN l.next_follow_up_at IS NULL THEN 'unscheduled' WHEN l.next_follow_up_at<NOW() THEN 'overdue' WHEN DATE(l.next_follow_up_at)=CURDATE() THEN 'due_today' ELSE 'upcoming' END AS follow_up_state FROM leads l LEFT JOIN lead_sources ls ON ls.id=l.source_id LEFT JOIN employees e ON e.id=l.assigned_employee_id LEFT JOIN opportunities o ON o.lead_id=l.id LEFT JOIN pipeline_stages ps ON ps.id=o.stage_id LEFT JOIN (SELECT lsi.lead_id, GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') AS service_names FROM lead_service_interests lsi JOIN services s ON s.id=lsi.service_id GROUP BY lsi.lead_id) si ON si.lead_id=l.id{$whereSql} ORDER BY CASE WHEN l.converted_client_id IS NULL AND l.status!='lost' AND l.next_follow_up_at IS NOT NULL AND l.next_follow_up_at<NOW() THEN 0 ELSE 1 END,l.created_at DESC", $parameters);
    }

    public function lead(int $id): ?array
    {
        $lead = $this->db->first("SELECT l.*, ls.name AS source_name, e.name AS owner_name, o.id AS opportunity_id, ps.name AS pipeline_stage_name, ps.color AS pipeline_stage_color, COALESCE(ps.win_probability,0) AS conversion_probability FROM leads l LEFT JOIN lead_sources ls ON ls.id=l.source_id LEFT JOIN employees e ON e.id=l.assigned_employee_id LEFT JOIN opportunities o ON o.lead_id=l.id LEFT JOIN pipeline_stages ps ON ps.id=o.stage_id WHERE l.id=?", [$id]);
        if (! $lead) {
            return null;
        }

        $lead['service_ids'] = array_map('intval', array_column($this->db->all('SELECT service_id FROM lead_service_interests WHERE lead_id=? ORDER BY service_id', [$id]), 'service_id'));
        $serviceNames = array_column($this->db->all('SELECT s.name FROM lead_service_interests lsi JOIN services s ON s.id=lsi.service_id WHERE lsi.lead_id=? ORDER BY s.name', [$id]), 'name');
        if ($serviceNames) {
            $lead['services_interested'] = implode(', ', $serviceNames);
        }
        $lead['followups'] = $this->db->all('SELECT lf.*, u.name AS user_name FROM lead_followups lf JOIN users u ON u.id=lf.user_id WHERE lf.lead_id=? ORDER BY lf.followed_up_at DESC, lf.id DESC', [$id]);
        $lead['consultations'] = $this->db->all('SELECT c.*, e.name AS completed_by_name FROM consultations c LEFT JOIN employees e ON e.id=c.completed_by WHERE c.lead_id=? ORDER BY c.completed_at DESC,c.id DESC', [$id]);
        foreach($lead['consultations'] as &$consultation){$consultation['business_goals_data']=json_decode($consultation['business_goals']??'{}',true)?:[];$consultation['marketing_snapshot_data']=json_decode($consultation['marketing_snapshot']??'{}',true)?:[];$consultation['target_customer_data']=json_decode($consultation['target_customer']??'{}',true)?:[];$consultation['problems_data']=json_decode($consultation['current_problems']??'[]',true)?:[];$consultation['outcomes_data']=json_decode($consultation['desired_outcomes']??'[]',true)?:[];}

        return $lead;
    }

    public function consultations(): array
    {
        $rows=$this->db->all("SELECT co.*, COALESCE(b.name,l.company_name) AS business_name, COALESCE(c.name,CONCAT(l.first_name, ' ', l.last_name)) AS contact_name, e.name AS completed_by_name FROM consultations co LEFT JOIN leads l ON l.id=co.lead_id LEFT JOIN clients c ON c.id=co.client_id LEFT JOIN businesses b ON b.id=c.business_id LEFT JOIN employees e ON e.id=co.completed_by ORDER BY co.completed_at DESC, co.id DESC");
        foreach($rows as &$row){$row['business_goals_data']=json_decode($row['business_goals']??'{}',true)?:[];$row['marketing_snapshot_data']=json_decode($row['marketing_snapshot']??'{}',true)?:[];$row['target_customer_data']=json_decode($row['target_customer']??'{}',true)?:[];$row['problems_data']=json_decode($row['current_problems']??'[]',true)?:[];$row['outcomes_data']=json_decode($row['desired_outcomes']??'[]',true)?:[];}
        return $rows;
    }

    public function clients(): array
    {
        return $this->db->all("SELECT c.*, b.name AS business_name, b.industry,
            COALESCE(
                (SELECT s.monthly_price FROM subscriptions s WHERE s.client_id=c.id AND s.status='active' ORDER BY s.id DESC LIMIT 1),
                (SELECT q.membership_monthly_price FROM proposals q WHERE q.client_id=c.id AND q.builder_version IS NOT NULL AND q.status='accepted' ORDER BY COALESCE(q.updated_at,q.created_at) DESC,q.id DESC LIMIT 1),
                0
            ) AS monthly_price,
            COALESCE(
                (SELECT p.name FROM subscriptions s JOIN packages p ON p.id=s.package_id WHERE s.client_id=c.id AND s.status='active' ORDER BY s.id DESC LIMIT 1),
                (SELECT qp.name FROM proposals q LEFT JOIN packages qp ON qp.id=q.package_id WHERE q.client_id=c.id AND q.builder_version IS NOT NULL AND q.status='accepted' ORDER BY COALESCE(q.updated_at,q.created_at) DESC,q.id DESC LIMIT 1)
            ) AS package_name,
            COALESCE((SELECT SUM(i.total-i.amount_paid) FROM invoices i WHERE i.client_id=c.id AND i.status NOT IN ('paid','cancelled')),0) AS outstanding
            FROM clients c JOIN businesses b ON b.id=c.business_id ORDER BY b.name");
    }

    public function client(int $id): ?array
    {
        $client = $this->db->first("SELECT c.*, b.name AS business_name, b.industry, b.website, b.employee_count, bs.name AS business_size, e.name AS account_manager, s.id AS subscription_id, s.monthly_price, s.renewal_date, p.name AS package_name, p.id AS package_id FROM clients c JOIN businesses b ON b.id=c.business_id LEFT JOIN business_sizes bs ON bs.id=b.business_size_id LEFT JOIN employees e ON e.id=c.account_manager_id LEFT JOIN subscriptions s ON s.client_id=c.id AND s.status='active' LEFT JOIN packages p ON p.id=s.package_id WHERE c.id=?", [$id]);
        if (!$client) {
            return null;
        }
        $client['contacts'] = $this->db->all('SELECT * FROM contacts WHERE client_id=? ORDER BY primary_contact DESC', [$id]);
        $client['projects'] = $this->db->all('SELECT * FROM projects WHERE client_id=? ORDER BY created_at DESC', [$id]);
        $client['tasks'] = $this->db->all('SELECT t.*, e.name AS assignee FROM project_tasks t LEFT JOIN employees e ON e.id=t.assigned_employee_id WHERE t.client_id=? ORDER BY t.due_date', [$id]);
        $client['invoices'] = $this->db->all('SELECT * FROM invoices WHERE client_id=? ORDER BY issue_date DESC', [$id]);
        $client['visits'] = $this->db->all('SELECT v.*, e.name AS assignee FROM content_visits v LEFT JOIN employees e ON e.id=v.assigned_employee_id WHERE v.client_id=? ORDER BY visit_date DESC', [$id]);
        $client['content'] = $this->db->all('SELECT * FROM content_items WHERE client_id=? ORDER BY scheduled_at DESC', [$id]);
        $client['activities'] = $this->db->all('SELECT a.*, u.name AS user_name FROM activities a LEFT JOIN users u ON u.id=a.user_id WHERE a.client_id=? ORDER BY a.created_at DESC LIMIT 30', [$id]);
        $client['notes'] = $this->db->all('SELECT n.*, u.name AS user_name FROM notes n JOIN users u ON u.id=n.user_id WHERE n.client_id=? ORDER BY n.created_at DESC', [$id]);
        $client['visit_usage'] = $client['subscription_id'] ? $this->visitUsage((int) $client['subscription_id']) : null;
        $client['profitability'] = $this->clientProfitability($id);
        return $client;
    }

    public function pipeline(array $filters = []): array
    {
        $stages = $this->db->all('SELECT * FROM pipeline_stages ORDER BY position,id');
        $clauses = [];
        $parameters = [];

        if (($ownerId = $this->nullableInt($filters['owner_id'] ?? null))) {
            $clauses[] = 'o.owner_id=?';
            $parameters[] = $ownerId;
        }
        if (($stageId = $this->nullableInt($filters['stage_id'] ?? null))) {
            $clauses[] = 'o.stage_id=?';
            $parameters[] = $stageId;
        }
        if (($serviceId = $this->nullableInt($filters['service_id'] ?? null))) {
            $serviceName = $this->db->scalar('SELECT name FROM services WHERE id=? AND active=1', [$serviceId]);
            if ($serviceName) {
                $clauses[] = 'o.services LIKE ?';
                $parameters[] = '%'.$serviceName.'%';
            }
        }
        if (($minimum = trim((string)($filters['min_value'] ?? ''))) !== '' && is_numeric($minimum)) {
            $clauses[] = 'o.estimated_value>=?';
            $parameters[] = max(0, (float)$minimum);
        }
        if (($maximum = trim((string)($filters['max_value'] ?? ''))) !== '' && is_numeric($maximum)) {
            $clauses[] = 'o.estimated_value<=?';
            $parameters[] = max(0, (float)$maximum);
        }
        $closeWindow = (string)($filters['close_window'] ?? '');
        if ($closeWindow === 'overdue') {
            $clauses[] = 'o.expected_close_date<CURDATE()';
        } elseif ($closeWindow === 'next_7') {
            $clauses[] = 'o.expected_close_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)';
        } elseif ($closeWindow === 'next_30') {
            $clauses[] = 'o.expected_close_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)';
        } elseif ($closeWindow === 'no_date') {
            $clauses[] = 'o.expected_close_date IS NULL';
        }
        if (($query = trim((string)($filters['q'] ?? ''))) !== '') {
            $clauses[] = '(o.title LIKE ? OR l.company_name LIKE ? OR b.name LIKE ? OR e.name LIKE ?)';
            array_push($parameters, ...array_fill(0, 4, '%'.$query.'%'));
        }

        $filterSql = $clauses ? ' AND '.implode(' AND ', $clauses) : '';
        foreach ($stages as &$stage) {
            $stageParameters = array_merge([(int)$stage['id']], $parameters);
            $stage['opportunities'] = $this->db->all(
                "SELECT o.*, COALESCE(b.name,l.company_name) AS business_name, e.name AS owner_name, l.converted_client_id, l.email AS lead_email, ps.slug AS stage_slug, previous_stage.name AS previous_stage_name, COALESCE((SELECT COUNT(*) FROM consultations co WHERE (o.lead_id IS NOT NULL AND co.lead_id=o.lead_id) OR (o.client_id IS NOT NULL AND co.client_id=o.client_id)),0) AS discovery_count, COALESCE((SELECT COUNT(*) FROM proposals p WHERE p.opportunity_id=o.id),0) AS proposal_count, TIMESTAMPDIFF(DAY,COALESCE(o.stage_entered_at,o.updated_at,o.created_at),NOW()) AS stage_age_days FROM opportunities o JOIN pipeline_stages ps ON ps.id=o.stage_id LEFT JOIN pipeline_stages previous_stage ON previous_stage.id=o.previous_stage_id LEFT JOIN leads l ON l.id=o.lead_id LEFT JOIN clients c ON c.id=o.client_id LEFT JOIN businesses b ON b.id=c.business_id LEFT JOIN employees e ON e.id=o.owner_id WHERE o.stage_id=?{$filterSql} ORDER BY CASE WHEN o.expected_close_date IS NULL THEN 1 ELSE 0 END,o.expected_close_date,o.updated_at DESC",
                $stageParameters
            );
            foreach ($stage['opportunities'] as &$opportunity) {
                $opportunity['history'] = $this->opportunityHistory((int)$opportunity['id']);
            }
        }
        return $stages;
    }

    public function packages(): array
    {
        $packages = $this->db->all("SELECT p.*, pp.base_price, pp.monthly_fee, pp.setup_fee, pp.effective_from, COALESCE(SUM(pi.estimated_cost),0) AS estimated_cost, COALESCE(SUM(pi.estimated_hours),0) AS estimated_hours FROM packages p LEFT JOIN package_pricing pp ON pp.package_id=p.id AND pp.effective_to IS NULL LEFT JOIN package_items pi ON pi.package_id=p.id GROUP BY p.id ORDER BY CASE p.package_type WHEN 'monthly' THEN 1 WHEN 'website' THEN 2 ELSE 3 END, COALESCE(pp.monthly_fee, pp.base_price)");
        foreach ($packages as &$package) {
            $package['limits'] = $this->db->all('SELECT * FROM package_limits WHERE package_id=? ORDER BY id', [$package['id']]);
            $package['services'] = $this->db->all('SELECT s.name, pi.scope_note FROM package_items pi JOIN services s ON s.id=pi.service_id WHERE pi.package_id=?', [$package['id']]);
            $price = (float) ($package['monthly_fee'] ?: $package['base_price']);
            $package['profit'] = $price - (float) $package['estimated_cost'];
            $package['margin'] = $price > 0 ? ($package['profit'] / $price * 100) : 0;
        }
        return $packages;
    }

    public function projects(): array
    {
        return $this->db->all("SELECT p.*, b.name AS business_name, e.name AS manager_name, COUNT(t.id) AS task_count, SUM(CASE WHEN t.status='completed' THEN 1 ELSE 0 END) AS completed_tasks FROM projects p JOIN clients c ON c.id=p.client_id JOIN businesses b ON b.id=c.business_id LEFT JOIN employees e ON e.id=p.manager_id LEFT JOIN project_tasks t ON t.project_id=p.id GROUP BY p.id ORDER BY p.deadline");
    }

    public function tasks(): array
    {
        return $this->db->all("SELECT t.*, b.name AS business_name, p.name AS project_name, e.name AS assignee FROM project_tasks t JOIN clients c ON c.id=t.client_id JOIN businesses b ON b.id=c.business_id JOIN projects p ON p.id=t.project_id LEFT JOIN employees e ON e.id=t.assigned_employee_id ORDER BY CASE t.status WHEN 'in_progress' THEN 1 WHEN 'review' THEN 2 WHEN 'todo' THEN 3 ELSE 4 END, t.due_date");
    }

    public function visits(): array
    {
        $rows = $this->db->all("SELECT v.*, b.name AS business_name, p.name AS package_name, e.name AS assignee FROM content_visits v JOIN clients c ON c.id=v.client_id JOIN businesses b ON b.id=c.business_id LEFT JOIN subscriptions s ON s.id=v.subscription_id LEFT JOIN packages p ON p.id=s.package_id LEFT JOIN employees e ON e.id=v.assigned_employee_id ORDER BY v.visit_date DESC");
        foreach ($rows as &$row) {
            $row['usage'] = $row['subscription_id'] ? $this->visitUsage((int) $row['subscription_id']) : null;
        }
        return $rows;
    }

    public function content(): array
    {
        return $this->db->all("SELECT ci.*, b.name AS business_name, e.name AS assignee FROM content_items ci JOIN clients c ON c.id=ci.client_id JOIN businesses b ON b.id=c.business_id LEFT JOIN employees e ON e.id=ci.assigned_employee_id ORDER BY ci.scheduled_at");
    }

    public function media(): array
    {
        return $this->db->all("SELECT m.*, b.name AS business_name, p.name AS project_name, e.name AS uploaded_by FROM media m JOIN clients c ON c.id=m.client_id JOIN businesses b ON b.id=c.business_id LEFT JOIN projects p ON p.id=m.project_id LEFT JOIN employees e ON e.id=m.created_by ORDER BY m.created_at DESC");
    }

    public function mediaItem(int $id): ?array
    {
        return $this->db->first('SELECT * FROM media WHERE id=?', [$id]);
    }

    public function invoices(): array
    {
        return $this->db->all("SELECT i.*, b.name AS business_name, (i.total-i.amount_paid) AS amount_due FROM invoices i JOIN clients c ON c.id=i.client_id JOIN businesses b ON b.id=c.business_id ORDER BY i.issue_date DESC");
    }

    public function employees(): array
    {
        return $this->db->all("SELECT e.*, COUNT(DISTINCT t.id) AS open_tasks, COALESCE(SUM(CASE WHEN te.entry_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN te.hours ELSE 0 END),0) AS hours_week FROM employees e LEFT JOIN project_tasks t ON t.assigned_employee_id=e.id AND t.status!='completed' LEFT JOIN time_entries te ON te.employee_id=e.id GROUP BY e.id ORDER BY e.name");
    }

    public function timeEntries(): array
    {
        return $this->db->all("SELECT te.*, e.name AS employee_name, b.name AS business_name, p.name AS project_name, (te.hours*e.hourly_cost) AS internal_cost FROM time_entries te JOIN employees e ON e.id=te.employee_id JOIN clients c ON c.id=te.client_id JOIN businesses b ON b.id=c.business_id JOIN projects p ON p.id=te.project_id ORDER BY te.entry_date DESC");
    }

    public function services(): array
    {
        return $this->db->all('SELECT s.*, sc.name AS category_name FROM services s JOIN service_categories sc ON sc.id=s.category_id ORDER BY sc.name, s.name');
    }

    public function proposals(): array
    {
        return $this->db->all("SELECT p.*, COALESCE(b.name,l.company_name) AS business_name, pk.name AS package_name FROM proposals p LEFT JOIN clients c ON c.id=p.client_id LEFT JOIN businesses b ON b.id=c.business_id LEFT JOIN leads l ON l.id=p.lead_id LEFT JOIN packages pk ON pk.id=p.package_id ORDER BY p.created_at DESC");
    }

    public function contracts(): array
    {
        return $this->db->all("SELECT co.*, b.name AS business_name, p.name AS package_name, pr.name AS project_name, DATEDIFF(co.end_date, CURDATE()) AS days_left FROM contracts co JOIN clients c ON c.id=co.client_id JOIN businesses b ON b.id=c.business_id LEFT JOIN packages p ON p.id=co.package_id LEFT JOIN projects pr ON pr.id=co.project_id ORDER BY co.end_date");
    }

    public function reports(): array
    {
        return [
            'sales' => $this->db->all("SELECT s.name AS stage, COUNT(o.id) AS deals, COALESCE(SUM(o.estimated_value),0) AS value, COALESCE(SUM(o.estimated_value*o.probability/100),0) AS weighted FROM pipeline_stages s LEFT JOIN opportunities o ON o.stage_id=s.id GROUP BY s.id ORDER BY s.position"),
            'clients' => $this->db->all("SELECT b.name, p.name AS package_name, s.monthly_price, c.health, COALESCE(SUM(i.total),0) AS invoiced, COALESCE(SUM(i.amount_paid),0) AS collected FROM clients c JOIN businesses b ON b.id=c.business_id LEFT JOIN subscriptions s ON s.client_id=c.id AND s.status='active' LEFT JOIN packages p ON p.id=s.package_id LEFT JOIN invoices i ON i.client_id=c.id GROUP BY c.id ORDER BY invoiced DESC"),
            'profitability' => $this->db->all("SELECT p.name AS project_name, b.name AS business_name, p.budget AS revenue, COALESCE(SUM(te.hours*e.hourly_cost),0) AS actual_cost, p.budget-COALESCE(SUM(te.hours*e.hourly_cost),0) AS gross_profit, CASE WHEN p.budget>0 THEN ((p.budget-COALESCE(SUM(te.hours*e.hourly_cost),0))/p.budget*100) ELSE 0 END AS margin FROM projects p JOIN clients c ON c.id=p.client_id JOIN businesses b ON b.id=c.business_id LEFT JOIN time_entries te ON te.project_id=p.id LEFT JOIN employees e ON e.id=te.employee_id GROUP BY p.id ORDER BY margin DESC"),
            'team' => $this->db->all("SELECT e.name, e.department, COALESCE(SUM(te.hours),0) AS hours, COALESCE(SUM(te.hours*e.hourly_cost),0) AS internal_cost, COUNT(DISTINCT CASE WHEN t.status!='completed' THEN t.id END) AS open_tasks FROM employees e LEFT JOIN time_entries te ON te.employee_id=e.id AND te.entry_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) LEFT JOIN project_tasks t ON t.assigned_employee_id=e.id GROUP BY e.id ORDER BY hours DESC"),
        ];
    }

    public function calendarEvents(string $month): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $events = [];
        foreach ($this->db->all("SELECT t.id,t.title,t.due_date AS event_date,t.assigned_employee_id AS employee_id,t.client_id,t.project_id,b.name AS client_name,'task' AS event_type,t.status FROM project_tasks t JOIN clients c ON c.id=t.client_id JOIN businesses b ON b.id=c.business_id WHERE t.due_date BETWEEN ? AND ? AND t.status!='completed'", [$start,$end]) as $row) { $events[] = $row; }
        foreach ($this->db->all("SELECT v.id,CONCAT(b.name, ' · ', v.visit_type) AS title,v.visit_date AS event_date,v.assigned_employee_id AS employee_id,v.client_id,NULL AS project_id,b.name AS client_name,'visit' AS event_type,v.status FROM content_visits v JOIN clients c ON c.id=v.client_id JOIN businesses b ON b.id=c.business_id WHERE v.visit_date BETWEEN ? AND ? AND v.status!='cancelled'", [$start,$end]) as $row) { $events[] = $row; }
        foreach ($this->db->all("SELECT ci.id,ci.title,date(ci.scheduled_at) AS event_date,ci.assigned_employee_id AS employee_id,ci.client_id,ci.project_id,b.name AS client_name,'content' AS event_type,ci.status FROM content_items ci JOIN clients c ON c.id=ci.client_id JOIN businesses b ON b.id=c.business_id WHERE date(ci.scheduled_at) BETWEEN ? AND ?", [$start,$end]) as $row) { $events[] = $row; }
        foreach ($this->db->all("SELECT p.id,CONCAT(p.name, ' deadline') AS title,p.deadline AS event_date,p.manager_id AS employee_id,p.client_id,p.id AS project_id,b.name AS client_name,'deadline' AS event_type,p.status FROM projects p JOIN clients c ON c.id=p.client_id JOIN businesses b ON b.id=c.business_id WHERE p.deadline BETWEEN ? AND ? AND p.status NOT IN ('completed','cancelled')", [$start,$end]) as $row) { $events[] = $row; }
        foreach ($this->db->all("SELECT s.id,CONCAT(b.name, ' renewal') AS title,s.renewal_date AS event_date,NULL AS employee_id,s.client_id,NULL AS project_id,b.name AS client_name,'renewal' AS event_type,s.status FROM subscriptions s JOIN clients c ON c.id=s.client_id JOIN businesses b ON b.id=c.business_id WHERE s.renewal_date BETWEEN ? AND ? AND s.status='active'", [$start,$end]) as $row) { $events[] = $row; }
        usort($events, static fn(array $a, array $b): int => strcmp($a['event_date'], $b['event_date']));
        return $events;
    }

    public function auditLogs(): array
    {
        return $this->db->all('SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 100');
    }

    public function rolesWithPermissions(): array
    {
        $roles=$this->db->all("SELECT * FROM roles WHERE slug!='super_admin' ORDER BY id");
        foreach($roles as &$role){$role['protected']=$role['slug']==='admin';$role['permission_ids']=array_map('intval',array_column($this->db->all("SELECT rp.permission_id FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.slug LIKE '%.access'",[$role['id']]),'permission_id'));}
        return $roles;
    }

    public function permissions(): array
    {
        return $this->db->all("SELECT p.*,n.position AS navigation_position,g.slug AS group_slug,g.label AS group_label,g.icon AS group_icon,g.position AS group_position FROM permissions p JOIN navigation_items n ON n.permission_slug=p.slug LEFT JOIN navigation_groups g ON g.id=n.group_id WHERE p.slug LIKE '%.access' ORDER BY COALESCE(g.position,n.position),n.position,p.name");
    }

    public function systemUsersWithAccess(): array
    {
        $users=$this->db->all("SELECT u.id,u.name,u.email,u.role_id,u.status,u.last_login_at,r.name AS role_name,r.slug AS role_slug,e.department,e.job_title FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN employees e ON e.user_id=u.id ORDER BY u.name");
        foreach($users as &$user){
            $user['permission_overrides']=[];
            foreach($this->db->all('SELECT permission_id,allowed FROM user_permissions WHERE user_id=?',[$user['id']]) as $override){
                $user['permission_overrides'][(int)$override['permission_id']]=(bool)$override['allowed'];
            }
        }
        return $users;
    }

    public function navigationConfiguration(): array
    {
        return $this->db->all('SELECT n.*,g.slug AS group_slug,g.label AS group_label,g.position AS group_position FROM navigation_items n LEFT JOIN navigation_groups g ON g.id=n.group_id ORDER BY COALESCE(g.position,n.position),n.position,n.label');
    }

    public function options(): array
    {
        return [
            'employees' => $this->db->all("SELECT id, name FROM employees WHERE status='active' ORDER BY name"),
            'clients' => $this->db->all("SELECT c.id, b.name FROM clients c JOIN businesses b ON b.id=c.business_id WHERE c.status='active' ORDER BY b.name"),
            'packages' => $this->db->all("SELECT p.id, p.name, COALESCE(pp.monthly_fee,pp.base_price,0) AS price FROM packages p LEFT JOIN package_pricing pp ON pp.package_id=p.id AND pp.effective_to IS NULL WHERE p.active=1 ORDER BY p.name"),
            'services' => $this->db->all("SELECT id, name, cost_estimate, estimated_hours FROM services WHERE active=1 ORDER BY name"),
            'categories' => $this->db->all("SELECT id, name FROM service_categories WHERE active=1 ORDER BY name"),
            'sources' => $this->db->all("SELECT id, name FROM lead_sources WHERE active=1 ORDER BY name"),
            'leads' => $this->db->all("SELECT id, company_name AS name FROM leads WHERE converted_client_id IS NULL ORDER BY company_name"),
            'stages' => $this->db->all('SELECT id, name FROM pipeline_stages ORDER BY position'),
            'opportunities' => $this->db->all("SELECT o.id, CONCAT(COALESCE(b.name,l.company_name,o.title), ' — ', ps.name) AS name, o.client_id, o.lead_id FROM opportunities o JOIN pipeline_stages ps ON ps.id=o.stage_id LEFT JOIN leads l ON l.id=o.lead_id LEFT JOIN clients c ON c.id=o.client_id LEFT JOIN businesses b ON b.id=c.business_id WHERE ps.is_closed=0 ORDER BY COALESCE(b.name,l.company_name,o.title)"),
            'projects' => $this->db->all("SELECT id, name, client_id FROM projects WHERE status NOT IN ('completed','cancelled') ORDER BY name"),
            'subscriptions' => $this->db->all("SELECT s.id, s.client_id, CONCAT(b.name, ' — ', p.name) AS name FROM subscriptions s JOIN clients c ON c.id=s.client_id JOIN businesses b ON b.id=c.business_id JOIN packages p ON p.id=s.package_id WHERE s.status='active' ORDER BY b.name"),
            'roles' => $this->db->all("SELECT id, name FROM roles WHERE slug!='super_admin' ORDER BY id"),
        ];
    }

    public function createLead(array $input): int
    {
        $this->required($input, ['first_name','last_name','company_name','email']);
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        if (empty($input['allow_duplicate'])) {
            $duplicate = $this->findLeadDuplicate((string)$input['email'], (string)($input['phone'] ?? ''), (string)$input['company_name']);
            if ($duplicate) {
                throw new InvalidArgumentException('Potential duplicate detected: '.$duplicate['company_name'].' already exists as lead #'.$duplicate['id'].'. Open the existing lead, or select the duplicate override when this is intentionally separate.');
            }
        }
        $qualification = $this->leadQualification($input);
        $now = now()->toDateTimeString();
        return $this->db->transaction(function () use ($input, $now, $qualification): int {
            $id = $this->db->insert('leads', [
                'first_name'=>trim($input['first_name']),'last_name'=>trim($input['last_name']),'company_name'=>trim($input['company_name']),
                'email'=>trim($input['email']),'phone'=>trim($input['phone'] ?? ''),'industry'=>trim($input['industry'] ?? ''),
                'employee_count'=>$qualification['employee_count'],'company_size_range'=>$qualification['company_size_range'],'years_in_business_range'=>$qualification['years_in_business_range'],'business_stage'=>$qualification['business_stage'],
                'source_id'=>$this->nullableInt($input['source_id'] ?? null),'status'=>'new','lead_score'=>$qualification['lead_score'],
                'assigned_employee_id'=>$this->nullableInt($input['assigned_employee_id'] ?? null),'estimated_budget'=>$qualification['estimated_budget'],'budget_range'=>$qualification['budget_range'],
                'services_interested'=>$qualification['service_names'],'notes'=>trim($input['notes'] ?? ''),'next_follow_up_at'=>($input['next_follow_up_at'] ?? null) ?: null,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
            foreach ($qualification['service_ids'] as $serviceId) {
                $this->db->insert('lead_service_interests', ['lead_id'=>$id,'service_id'=>$serviceId]);
            }
            $stageId = (int) $this->db->scalar("SELECT id FROM pipeline_stages WHERE slug='new'");
            $this->db->insert('opportunities', ['lead_id'=>$id,'stage_id'=>$stageId,'owner_id'=>$this->nullableInt($input['assigned_employee_id'] ?? null),'title'=>trim($input['company_name']).' opportunity','estimated_value'=>$qualification['estimated_budget'],'services'=>$qualification['service_names'],'probability'=>10,'expected_close_date'=>date('Y-m-d',strtotime('+30 days')),'next_action'=>'Make first contact','created_at'=>$now,'updated_at'=>$now]);
            $this->audit('created','lead',$id,null,array_merge($input,['lead_score'=>$qualification['lead_score'],'estimated_budget'=>$qualification['estimated_budget'],'service_ids'=>$qualification['service_ids']]));
            return $id;
        });
    }

    public function updateLead(array $input): void
    {
        $this->required($input, ['lead_id','first_name','last_name','company_name','email','status']);
        if (! filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        $leadId = (int) $input['lead_id'];
        $before = $this->db->first('SELECT * FROM leads WHERE id=?', [$leadId]);
        if (! $before) {
            throw new InvalidArgumentException('Select a valid lead.');
        }
        if ($before['converted_client_id']) {
            throw new InvalidArgumentException('Converted leads must be managed from the client profile.');
        }

        $status = (string) $input['status'];
        $allowedStatuses = ['new','contacted','discovery','qualified','proposal','negotiation','lost'];
        if (! in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException('Select a valid lead status.');
        }
        $lostReason = trim((string)($input['lost_reason'] ?? ''));
        if ($status === 'lost' && $lostReason === '') {
            throw new InvalidArgumentException('Explain why this lead was lost before closing it.');
        }

        $qualification = $this->leadQualification($input);
        $after = [
            'first_name'=>trim((string)$input['first_name']),'last_name'=>trim((string)$input['last_name']),'company_name'=>trim((string)$input['company_name']),
            'email'=>trim((string)$input['email']),'phone'=>trim((string)($input['phone'] ?? '')),'website'=>trim((string)($input['website'] ?? '')),
            'industry'=>trim((string)($input['industry'] ?? '')),'source_id'=>$this->nullableInt($input['source_id'] ?? null),'assigned_employee_id'=>$this->nullableInt($input['assigned_employee_id'] ?? null),
            'status'=>$status,'lost_reason'=>$status==='lost'?$lostReason:null,'lost_at'=>$status==='lost'?($before['lost_at'] ?: date('c')):null,'employee_count'=>$qualification['employee_count'],'company_size_range'=>$qualification['company_size_range'],'years_in_business_range'=>$qualification['years_in_business_range'],'business_stage'=>$qualification['business_stage'],
            'lead_score'=>$qualification['lead_score'],'estimated_budget'=>$qualification['estimated_budget'],'budget_range'=>$qualification['budget_range'],'services_interested'=>$qualification['service_names'],
            'notes'=>trim((string)($input['notes'] ?? '')),'next_follow_up_at'=>($input['next_follow_up_at'] ?? null) ?: null,'updated_at'=>date('c'),
        ];

        $this->db->transaction(function () use ($leadId, $before, $after, $qualification, $status): void {
            $this->db->update('leads', $leadId, $after);
            $this->db->execute('DELETE FROM lead_service_interests WHERE lead_id=?', [$leadId]);
            foreach ($qualification['service_ids'] as $serviceId) {
                $this->db->insert('lead_service_interests', ['lead_id'=>$leadId,'service_id'=>$serviceId]);
            }

            $stage = $this->db->first('SELECT id,win_probability FROM pipeline_stages WHERE slug=?', [$status]);
            if ($stage) {
                $this->db->execute('UPDATE opportunities SET stage_id=?, owner_id=?, title=?, estimated_value=?, services=?, probability=?, updated_at=? WHERE lead_id=?', [(int)$stage['id'],$after['assigned_employee_id'],$after['company_name'].' opportunity',$after['estimated_budget'],$after['services_interested'],(int)$stage['win_probability'],date('c'),$leadId]);
            } else {
                $this->db->execute('UPDATE opportunities SET owner_id=?, title=?, estimated_value=?, services=?, updated_at=? WHERE lead_id=?', [$after['assigned_employee_id'],$after['company_name'].' opportunity',$after['estimated_budget'],$after['services_interested'],date('c'),$leadId]);
            }
            $this->audit('updated','lead',$leadId,$before,array_merge($after,['service_ids'=>$qualification['service_ids']]));
        });
    }

    public function addLeadFollowup(array $input): int
    {
        $this->required($input, ['lead_id','type','followed_up_at','notes']);
        $leadId = (int) $input['lead_id'];
        $lead = $this->db->first('SELECT * FROM leads WHERE id=?', [$leadId]);
        if (! $lead) {
            throw new InvalidArgumentException('Select a valid lead.');
        }
        if ($lead['converted_client_id']) {
            throw new InvalidArgumentException('Add future communication from the client profile.');
        }

        $type = (string) $input['type'];
        $outcome = trim((string) ($input['outcome'] ?? ''));
        if (! in_array($type, ['call','email','meeting','message','note','other'], true)) {
            throw new InvalidArgumentException('Select a valid follow-up type.');
        }
        if ($outcome !== '' && ! in_array($outcome, ['connected','no_answer','interested','needs_follow_up','meeting_scheduled','not_interested','completed'], true)) {
            throw new InvalidArgumentException('Select a valid follow-up outcome.');
        }

        $followedUpTimestamp = strtotime((string) $input['followed_up_at']);
        $nextTimestamp = trim((string) ($input['next_follow_up_at'] ?? '')) !== '' ? strtotime((string) $input['next_follow_up_at']) : false;
        if ($followedUpTimestamp === false || (trim((string) ($input['next_follow_up_at'] ?? '')) !== '' && $nextTimestamp === false)) {
            throw new InvalidArgumentException('Enter valid follow-up dates.');
        }
        $followedUpAt = date('Y-m-d H:i:s', $followedUpTimestamp);
        $nextFollowUpAt = $nextTimestamp === false ? null : date('Y-m-d H:i:s', $nextTimestamp);

        return $this->db->transaction(function () use ($input, $lead, $leadId, $type, $outcome, $followedUpAt, $nextFollowUpAt): int {
            $followupId = $this->db->insert('lead_followups', [
                'lead_id'=>$leadId,'user_id'=>(int)$this->auth->user()['id'],'type'=>$type,'outcome'=>$outcome ?: null,
                'subject'=>trim((string)($input['subject'] ?? '')),'notes'=>trim((string)$input['notes']),
                'followed_up_at'=>$followedUpAt,'next_follow_up_at'=>$nextFollowUpAt,'created_at'=>date('c'),
            ]);

            $status = $lead['status'];
            if ($status === 'new' && $type !== 'note') {
                $status = 'contacted';
            }
            $this->db->execute('UPDATE leads SET status=?, last_contact_at=?, next_follow_up_at=?, updated_at=? WHERE id=?', [$status,$followedUpAt,$nextFollowUpAt,date('c'),$leadId]);
            if ($status !== $lead['status']) {
                $stage = $this->db->first("SELECT id,win_probability FROM pipeline_stages WHERE slug='contacted'");
                if ($stage) {
                    $this->db->execute('UPDATE opportunities SET stage_id=?, probability=?, next_action=?, updated_at=? WHERE lead_id=?', [(int)$stage['id'],(int)$stage['win_probability'],$nextFollowUpAt?'Complete scheduled follow-up':'Schedule next follow-up',date('c'),$leadId]);
                }
            } else {
                $this->db->execute('UPDATE opportunities SET next_action=?, updated_at=? WHERE lead_id=?', [$nextFollowUpAt?'Complete scheduled follow-up':'Schedule next follow-up',date('c'),$leadId]);
            }
            $this->audit('follow_up_added','lead',$leadId,$lead,['followup_id'=>$followupId,'type'=>$type,'outcome'=>$outcome,'followed_up_at'=>$followedUpAt,'next_follow_up_at'=>$nextFollowUpAt]);

            return $followupId;
        });
    }

    public function updateLeadFollowup(array $input): void
    {
        $this->required($input, ['lead_id','followup_id','type','followed_up_at','notes']);
        $leadId = (int)$input['lead_id'];
        $lead = $this->db->first('SELECT * FROM leads WHERE id=?', [$leadId]);
        $before = $this->db->first('SELECT * FROM lead_followups WHERE id=? AND lead_id=?', [(int)$input['followup_id'],$leadId]);
        if (! $lead || ! $before) {
            throw new InvalidArgumentException('Select a valid follow-up entry.');
        }
        if ($lead['converted_client_id']) {
            throw new InvalidArgumentException('Converted lead history is read-only.');
        }

        $type = (string)$input['type'];
        $outcome = trim((string)($input['outcome'] ?? ''));
        if (! in_array($type, ['call','email','meeting','message','note','other'], true)) {
            throw new InvalidArgumentException('Select a valid follow-up type.');
        }
        if ($outcome !== '' && ! in_array($outcome, ['connected','no_answer','interested','needs_follow_up','meeting_scheduled','not_interested','completed'], true)) {
            throw new InvalidArgumentException('Select a valid follow-up outcome.');
        }

        $followedUpTimestamp = strtotime((string)$input['followed_up_at']);
        $nextValue = trim((string)($input['next_follow_up_at'] ?? ''));
        $nextTimestamp = $nextValue !== '' ? strtotime($nextValue) : false;
        if ($followedUpTimestamp === false || ($nextValue !== '' && $nextTimestamp === false)) {
            throw new InvalidArgumentException('Enter valid follow-up dates.');
        }
        $after = [
            'type'=>$type,'outcome'=>$outcome ?: null,'subject'=>trim((string)($input['subject'] ?? '')),
            'notes'=>trim((string)$input['notes']),'followed_up_at'=>date('Y-m-d H:i:s',$followedUpTimestamp),
            'next_follow_up_at'=>$nextTimestamp===false?null:date('Y-m-d H:i:s',$nextTimestamp),
        ];

        $this->db->transaction(function () use ($leadId, $before, $after): void {
            $this->db->update('lead_followups', (int)$before['id'], $after);
            $latest = $this->db->first('SELECT followed_up_at,next_follow_up_at FROM lead_followups WHERE lead_id=? ORDER BY followed_up_at DESC,id DESC LIMIT 1', [$leadId]);
            $this->db->execute('UPDATE leads SET last_contact_at=?,next_follow_up_at=?,updated_at=? WHERE id=?', [$latest['followed_up_at']??null,$latest['next_follow_up_at']??null,date('c'),$leadId]);
            $this->db->execute('UPDATE opportunities SET next_action=?,updated_at=? WHERE lead_id=?', [!empty($latest['next_follow_up_at'])?'Complete scheduled follow-up':'Schedule next follow-up',date('c'),$leadId]);
            $this->audit('follow_up_corrected','lead',$leadId,$before,array_merge($after,['followup_id'=>(int)$before['id']]));
        });
    }

    public function createConsultation(array $input): int
    {
        $leadId=$this->nullableInt($input['lead_id']??null);$clientId=$this->nullableInt($input['client_id']??null);
        if(!$leadId && !$clientId){throw new InvalidArgumentException('Select a lead or client for the discovery session.');}
        if($leadId && $clientId){throw new InvalidArgumentException('Select either a lead or a client, not both.');}
        if($leadId){$lead=$this->db->first('SELECT * FROM leads WHERE id=?',[$leadId]);if(!$lead || $lead['converted_client_id']){throw new InvalidArgumentException('Select an active, unconverted lead.');}}
        $goals=['main_goal'=>trim($input['main_goal']??''),'revenue_goal'=>trim($input['revenue_goal']??''),'acquisition_goal'=>trim($input['acquisition_goal']??''),'expansion_plans'=>trim($input['expansion_plans']??''),'challenges'=>trim($input['challenges']??''),'competitors'=>trim($input['competitors']??'')];
        $marketing=['website'=>trim($input['current_website']??''),'social_media'=>trim($input['current_social_media']??''),'google_business'=>trim($input['google_business']??''),'advertising'=>trim($input['current_advertising']??''),'seo_status'=>trim($input['seo_status']??''),'budget'=>trim($input['marketing_budget']??''),'agency_experience'=>trim($input['agency_experience']??'')];
        $customer=['target_customer'=>trim($input['target_customer']??''),'geographic_market'=>trim($input['geographic_market']??''),'demographics'=>trim($input['customer_demographics']??''),'problems'=>trim($input['customer_problems']??''),'why_choose'=>trim($input['why_customers_choose']??'')];
        $employeeId=$this->nullableInt($this->db->scalar('SELECT id FROM employees WHERE user_id=?',[$this->auth->user()['id']]));
        $id=$this->db->insert('consultations',['lead_id'=>$leadId,'client_id'=>$clientId,'business_goals'=>json_encode($goals),'marketing_snapshot'=>json_encode($marketing),'target_customer'=>json_encode($customer),'current_problems'=>json_encode(array_values((array)($input['current_problems']??[]))),'desired_outcomes'=>json_encode(array_values((array)($input['desired_outcomes']??[]))),'notes'=>trim($input['notes']??''),'completed_by'=>$employeeId,'completed_at'=>date('c')]);
        if($leadId){
            $stage=$this->db->first("SELECT id,win_probability FROM pipeline_stages WHERE slug='discovery'");
            $this->db->execute("UPDATE leads SET status='discovery',last_contact_at=?,updated_at=? WHERE id=?",[date('c'),date('c'),$leadId]);
            if($stage){$this->db->execute("UPDATE opportunities SET stage_id=?,probability=?,next_action='Review discovery and qualify opportunity',updated_at=? WHERE lead_id=?",[(int)$stage['id'],(int)$stage['win_probability'],date('c'),$leadId]);}
        }
        if($clientId){$this->activity($clientId,'consultation.completed','Structured discovery consultation completed.','consultation',$id);}
        $this->audit('created','consultation',$id,null,['lead_id'=>$leadId,'client_id'=>$clientId,'goals'=>$goals,'problems'=>$input['current_problems']??[],'outcomes'=>$input['desired_outcomes']??[]]);
        return $id;
    }

    public function convertLead(int $leadId): int
    {
        $lead = $this->db->first('SELECT * FROM leads WHERE id=?', [$leadId]);
        if (!$lead || $lead['converted_client_id']) {
            throw new InvalidArgumentException('This lead cannot be converted.');
        }
        return $this->db->transaction(function () use ($lead): int {
            $employeeCount = $this->nullableInt($lead['employee_count'] ?? null);
            $sizeId = $employeeCount
                ? $this->nullableInt($this->db->scalar('SELECT id FROM business_sizes WHERE min_employees<=? AND (max_employees IS NULL OR max_employees>=?) ORDER BY min_employees DESC LIMIT 1', [$employeeCount, $employeeCount]))
                : null;
            $yearValues = ['under_1'=>0,'1_2'=>2,'3_5'=>4,'6_10'=>8,'10_plus'=>12];
            $yearsInBusiness = $yearValues[$lead['years_in_business_range'] ?? ''] ?? null;
            $businessId = $this->db->insert('businesses', ['business_size_id'=>$sizeId,'name'=>$lead['company_name'],'legal_name'=>$lead['company_name'],'industry'=>$lead['industry'],'website'=>$lead['website'],'employee_count'=>$employeeCount,'years_in_business'=>$yearsInBusiness,'created_at'=>date('c')]);
            $clientId = $this->db->insert('clients', ['business_id'=>$businessId,'account_manager_id'=>$lead['assigned_employee_id'],'name'=>trim($lead['first_name'].' '.$lead['last_name']),'email'=>$lead['email'],'phone'=>$lead['phone'],'status'=>'active','health'=>'healthy','joined_at'=>date('Y-m-d'),'created_at'=>date('c')]);
            $this->db->insert('contacts', ['client_id'=>$clientId,'name'=>trim($lead['first_name'].' '.$lead['last_name']),'title'=>'Primary contact','email'=>$lead['email'],'phone'=>$lead['phone'],'primary_contact'=>1]);
            $this->db->execute("UPDATE leads SET status='converted', converted_client_id=?, updated_at=? WHERE id=?", [$clientId,date('c'),$lead['id']]);
            $wonStage = $this->db->first("SELECT id,win_probability FROM pipeline_stages WHERE slug='won'");
            if ($wonStage) {
                $this->db->execute("UPDATE opportunities SET client_id=?,previous_stage_id=stage_id,stage_id=?,probability=?,stage_entered_at=?,closed_at=?,lost_reason=NULL,next_action='Begin client onboarding',updated_at=? WHERE lead_id=?", [$clientId,(int)$wonStage['id'],(int)$wonStage['win_probability'],date('c'),date('c'),date('c'),$lead['id']]);
            } else {
                $this->db->execute('UPDATE opportunities SET client_id=?, updated_at=? WHERE lead_id=?', [$clientId,date('c'),$lead['id']]);
            }
            $this->activity($clientId,'lead.converted','Lead converted into an active client.','lead',(int)$lead['id']);
            $this->audit('converted','lead',(int)$lead['id'],$lead,['client_id'=>$clientId]);
            return $clientId;
        });
    }

    public function createClient(array $input): int
    {
        $this->required($input, ['business_name','contact_name','email','joined_at']);
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid client email address.');
        }
        return $this->db->transaction(function () use ($input): int {
            $businessId = $this->db->insert('businesses', ['business_size_id'=>$this->nullableInt($input['business_size_id'] ?? null),'name'=>trim($input['business_name']),'legal_name'=>trim($input['business_name']),'industry'=>trim($input['industry'] ?? ''),'website'=>trim($input['website'] ?? ''),'employee_count'=>$this->nullableInt($input['employee_count'] ?? null),'created_at'=>date('c')]);
            $clientId = $this->db->insert('clients', ['business_id'=>$businessId,'account_manager_id'=>$this->nullableInt($input['account_manager_id'] ?? null),'name'=>trim($input['contact_name']),'email'=>trim($input['email']),'phone'=>trim($input['phone'] ?? ''),'status'=>'active','health'=>'healthy','joined_at'=>$input['joined_at'],'created_at'=>date('c')]);
            $this->db->insert('contacts', ['client_id'=>$clientId,'name'=>trim($input['contact_name']),'title'=>'Primary contact','email'=>trim($input['email']),'phone'=>trim($input['phone'] ?? ''),'primary_contact'=>1]);
            if (!empty($input['package_id'])) {
                $package = $this->db->first("SELECT p.*, pp.monthly_fee, pp.base_price FROM packages p LEFT JOIN package_pricing pp ON pp.package_id=p.id AND pp.effective_to IS NULL WHERE p.id=?", [(int)$input['package_id']]);
                $this->db->insert('subscriptions', ['client_id'=>$clientId,'package_id'=>$package['id'],'monthly_price'=>(float)($package['monthly_fee'] ?: $package['base_price']),'start_date'=>$input['joined_at'],'renewal_date'=>date('Y-m-d',strtotime($input['joined_at'].' +1 year')),'contract_end_date'=>date('Y-m-d',strtotime($input['joined_at'].' +1 year')),'billing_frequency'=>$package['package_type']==='monthly'?'monthly':'one_time','status'=>'active','created_at'=>date('c')]);
            }
            $this->activity($clientId,'client.created','Client profile created.','client',$clientId);
            $this->audit('created','client',$clientId,null,$input);
            return $clientId;
        });
    }

    public function createPackage(array $input): int
    {
        $this->required($input, ['name','package_type','price']);
        if (stripos((string)($input['description'] ?? ''), 'unlimited') !== false) {
            throw new InvalidArgumentException('Use a measurable package limit instead of “unlimited”.');
        }
        $price = $this->nonNegative($input['price']);
        $serviceIds = array_values(array_filter(array_map('intval', (array)($input['service_ids'] ?? []))));
        return $this->db->transaction(function () use ($input, $price, $serviceIds): int {
            $id = $this->db->insert('packages', ['name'=>trim($input['name']),'package_type'=>$input['package_type'],'description'=>trim($input['description'] ?? ''),'featured'=>!empty($input['featured'])?1:0,'active'=>1,'created_at'=>date('c'),'updated_at'=>date('c')]);
            $monthly = $input['package_type']==='monthly';
            $this->db->insert('package_pricing', ['package_id'=>$id,'business_size_id'=>null,'base_price'=>$monthly?0:$price,'monthly_fee'=>$monthly?$price:0,'setup_fee'=>$this->nonNegative($input['setup_fee'] ?? 0),'minimum_price'=>$price,'maximum_price'=>null,'discount_percent'=>$this->nonNegative($input['discount_percent'] ?? 0),'tax_percent'=>$this->nonNegative($input['tax_percent'] ?? 0),'deposit_percent'=>$this->nonNegative($input['deposit_percent'] ?? 0),'effective_from'=>$input['effective_from'] ?: date('Y-m-d'),'effective_to'=>null]);
            foreach ($serviceIds as $serviceId) {
                $service = $this->db->first('SELECT * FROM services WHERE id=? AND active=1', [$serviceId]);
                if ($service) {
                    $this->db->insert('package_items', ['package_id'=>$id,'service_id'=>$serviceId,'quantity'=>1,'scope_note'=>'Included','estimated_cost'=>$service['cost_estimate'],'estimated_hours'=>$service['estimated_hours']]);
                }
            }
            if ($monthly && (float)($input['included_visits'] ?? 0) > 0) {
                $this->db->insert('package_limits', ['package_id'=>$id,'limit_key'=>'content_visits','label'=>'On-site content visits','included_quantity'=>$this->nonNegative($input['included_visits']),'unit'=>'visits','overage_price'=>$this->nonNegative($input['additional_visit_price'] ?? 0),'period'=>'month']);
                $this->db->insert('package_limits', ['package_id'=>$id,'limit_key'=>'visit_duration','label'=>'Maximum visit duration','included_quantity'=>$this->nonNegative($input['hours_per_visit'] ?? 0),'unit'=>'hours','overage_price'=>0,'period'=>'visit']);
            }
            $this->audit('created','package',$id,null,$input);
            return $id;
        });
    }

    public function createService(array $input): int
    {
        $this->required($input, ['name','category_id','pricing_type']);
        $id = $this->db->insert('services', ['category_id'=>(int)$input['category_id'],'name'=>trim($input['name']),'description'=>trim($input['description'] ?? ''),'pricing_type'=>$input['pricing_type'],'default_price'=>$this->nonNegative($input['default_price'] ?? 0),'cost_estimate'=>$this->nonNegative($input['cost_estimate'] ?? 0),'estimated_hours'=>$this->nonNegative($input['estimated_hours'] ?? 0),'taxable'=>1,'active'=>1,'created_at'=>date('c')]);
        $this->audit('created','service',$id,null,$input);
        return $id;
    }

    public function updateService(array $input): void
    {
        $this->required($input, ['service_id','name','category_id','pricing_type']);

        $serviceId = (int) $input['service_id'];
        $before = $this->db->first('SELECT * FROM services WHERE id=?', [$serviceId]);
        if (! $before) {
            throw new InvalidArgumentException('Select a valid service.');
        }

        $name = trim((string) $input['name']);
        if (mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Service name cannot exceed 120 characters.');
        }
        if ($this->db->first('SELECT id FROM services WHERE name=? AND id<>?', [$name, $serviceId])) {
            throw new InvalidArgumentException('A service with this name already exists.');
        }

        $categoryId = (int) $input['category_id'];
        if (! $this->db->first('SELECT id FROM service_categories WHERE id=?', [$categoryId])) {
            throw new InvalidArgumentException('Select a valid service category.');
        }

        $pricingTypes = ['one_time','monthly','hourly','per_visit','per_project','custom'];
        $pricingType = (string) $input['pricing_type'];
        if (! in_array($pricingType, $pricingTypes, true)) {
            throw new InvalidArgumentException('Select a valid pricing model.');
        }

        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($description) > 2000) {
            throw new InvalidArgumentException('Description cannot exceed 2,000 characters.');
        }

        $after = [
            'category_id' => $categoryId,
            'name' => $name,
            'description' => $description,
            'pricing_type' => $pricingType,
            'default_price' => $this->nonNegative($input['default_price'] ?? 0),
            'cost_estimate' => $this->nonNegative($input['cost_estimate'] ?? 0),
            'estimated_hours' => $this->nonNegative($input['estimated_hours'] ?? 0),
            'taxable' => (int) (($input['taxable'] ?? '1') === '1'),
            'active' => (int) (($input['active'] ?? '1') === '1'),
        ];

        $this->db->transaction(function () use ($serviceId, $before, $after): void {
            $this->db->update('services', $serviceId, $after);
            $this->audit('updated', 'service', $serviceId, $before, $after);
        });
    }

    public function createProject(array $input): int
    {
        $this->required($input, ['client_id','name','project_type','start_date']);
        $id = $this->db->insert('projects', ['client_id'=>(int)$input['client_id'],'package_id'=>$this->nullableInt($input['package_id'] ?? null),'manager_id'=>$this->nullableInt($input['manager_id'] ?? null),'name'=>trim($input['name']),'project_type'=>$input['project_type'],'start_date'=>$input['start_date'],'deadline'=>$input['deadline'] ?: null,'budget'=>$this->nonNegative($input['budget'] ?? 0),'estimated_hours'=>$this->nonNegative($input['estimated_hours'] ?? 0),'actual_hours'=>0,'status'=>'planning','priority'=>$input['priority'] ?? 'medium','notes'=>trim($input['notes'] ?? ''),'created_at'=>date('c')]);
        $this->activity((int)$input['client_id'],'project.created','Project created: '.trim($input['name']),'project',$id);
        $this->audit('created','project',$id,null,$input);
        return $id;
    }

    public function createTask(array $input): int
    {
        $this->required($input, ['project_id','title']);
        $project = $this->db->first('SELECT client_id FROM projects WHERE id=?', [(int)$input['project_id']]);
        if (!$project) {
            throw new InvalidArgumentException('Select a valid project.');
        }
        $id = $this->db->insert('project_tasks', ['project_id'=>(int)$input['project_id'],'client_id'=>$project['client_id'],'assigned_employee_id'=>$this->nullableInt($input['assigned_employee_id'] ?? null),'title'=>trim($input['title']),'description'=>trim($input['description'] ?? ''),'due_date'=>$input['due_date'] ?: null,'priority'=>$input['priority'] ?? 'medium','status'=>'todo','estimated_hours'=>$this->nonNegative($input['estimated_hours'] ?? 0),'actual_hours'=>0,'created_at'=>date('c')]);
        $this->activity((int)$project['client_id'],'task.created','Task created: '.trim($input['title']),'task',$id);
        $this->audit('created','task',$id,null,$input);
        return $id;
    }

    public function createVisit(array $input): int
    {
        $this->required($input, ['client_id','visit_date','visit_type']);
        $subscriptionId = $this->nullableInt($input['subscription_id'] ?? null);
        if (!$subscriptionId) {
            $subscriptionId = $this->nullableInt($this->db->scalar("SELECT id FROM subscriptions WHERE client_id=? AND status='active' LIMIT 1", [(int)$input['client_id']]));
        }
        $id = $this->db->insert('content_visits', ['client_id'=>(int)$input['client_id'],'subscription_id'=>$subscriptionId,'assigned_employee_id'=>$this->nullableInt($input['assigned_employee_id'] ?? null),'visit_date'=>$input['visit_date'],'start_time'=>$input['start_time'] ?: null,'end_time'=>$input['end_time'] ?: null,'visit_type'=>$input['visit_type'],'status'=>'scheduled','purpose'=>trim($input['purpose'] ?? ''),'equipment'=>trim($input['equipment'] ?? ''),'notes'=>trim($input['notes'] ?? ''),'is_additional'=>0,'additional_charge'=>0,'created_at'=>date('c')]);
        $this->activity((int)$input['client_id'],'visit.scheduled','Content visit scheduled for '.$input['visit_date'].'.','visit',$id);
        $this->audit('created','content_visit',$id,null,$input);
        return $id;
    }

    public function completeVisit(int $visitId): void
    {
        $visit = $this->db->first('SELECT * FROM content_visits WHERE id=?', [$visitId]);
        if (!$visit || in_array($visit['status'], ['completed','cancelled'], true)) {
            throw new InvalidArgumentException('This visit cannot be completed.');
        }
        $additional = 0;
        $charge = 0.0;
        if ($visit['subscription_id']) {
            $usage = $this->visitUsage((int)$visit['subscription_id'], $visit['visit_date']);
            if ($usage['used'] >= $usage['included']) {
                $additional = 1;
                $charge = (float)$usage['overage_price'];
            }
        }
        $before = $visit;
        $this->db->execute("UPDATE content_visits SET status='completed', is_additional=?, additional_charge=? WHERE id=?", [$additional,$charge,$visitId]);
        $this->activity((int)$visit['client_id'],'visit.completed','Content visit completed'.($additional?' as an additional billable visit.':'.'),'visit',$visitId);
        $this->audit('completed','content_visit',$visitId,$before,['status'=>'completed','is_additional'=>$additional,'additional_charge'=>$charge]);
    }

    public function createInvoice(array $input): int
    {
        $this->required($input, ['client_id','description','amount','due_date']);
        $amount = $this->nonNegative($input['amount']);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Invoice amount must be greater than zero.');
        }
        return $this->db->transaction(function () use ($input, $amount): int {
            $number = 'INV-' . date('Y') . '-' . str_pad((string)((int)$this->db->scalar('SELECT COUNT(*) FROM invoices') + 1001), 4, '0', STR_PAD_LEFT);
            $taxPercent = $this->nonNegative($input['tax_percent'] ?? 0);
            $discount = $this->nonNegative($input['discount'] ?? 0);
            $tax = max(0, ($amount-$discount)*$taxPercent/100);
            $total = $amount-$discount+$tax;
            $id = $this->db->insert('invoices', ['invoice_number'=>$number,'client_id'=>(int)$input['client_id'],'project_id'=>$this->nullableInt($input['project_id'] ?? null),'package_id'=>$this->nullableInt($input['package_id'] ?? null),'issue_date'=>date('Y-m-d'),'due_date'=>$input['due_date'],'subtotal'=>$amount,'discount'=>$discount,'tax'=>$tax,'total'=>$total,'amount_paid'=>0,'status'=>'sent','notes'=>trim($input['notes'] ?? ''),'created_at'=>date('c')]);
            $this->db->insert('invoice_items', ['invoice_id'=>$id,'description'=>trim($input['description']),'quantity'=>1,'unit_price'=>$amount,'total'=>$amount]);
            $this->activity((int)$input['client_id'],'invoice.sent','Invoice '.$number.' sent for '.money($total).'.','invoice',$id);
            $this->audit('created','invoice',$id,null,$input);
            return $id;
        });
    }

    public function createProposal(array $input): int
    {
        $this->required($input, ['title','description','amount','valid_until']);
        $amount = $this->nonNegative($input['amount']);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Proposal amount must be greater than zero.');
        }
        $opportunityId = $this->nullableInt($input['opportunity_id'] ?? null);
        $clientId = $this->nullableInt($input['client_id'] ?? null);
        $leadId = null;
        if ($opportunityId) {
            $opportunity = $this->db->first('SELECT o.*,l.converted_client_id FROM opportunities o LEFT JOIN leads l ON l.id=o.lead_id WHERE o.id=?', [$opportunityId]);
            if (!$opportunity) {
                throw new InvalidArgumentException('Select a valid opportunity for this proposal.');
            }
            $clientId = $this->nullableInt($opportunity['client_id'] ?: $opportunity['converted_client_id']);
            $leadId = $this->nullableInt($opportunity['lead_id']);
        }
        if (!$clientId && !$leadId) {
            throw new InvalidArgumentException('Select an opportunity or an existing client for this proposal.');
        }

        return $this->db->transaction(function () use ($input, $amount, $opportunityId, $clientId, $leadId): int {
            $number = 'PROP-' . date('Y') . '-' . str_pad((string)((int)$this->db->scalar('SELECT COUNT(*) FROM proposals') + 1), 3, '0', STR_PAD_LEFT);
            $discount = $this->nonNegative($input['discount'] ?? 0);
            $taxPercent = $this->nonNegative($input['tax_percent'] ?? 0);
            $tax = max(0, ($amount-$discount)*$taxPercent/100);
            $total = $amount-$discount+$tax;
            $id = $this->db->insert('proposals', ['proposal_number'=>$number,'client_id'=>$clientId,'lead_id'=>$leadId,'opportunity_id'=>$opportunityId,'package_id'=>$this->nullableInt($input['package_id'] ?? null),'title'=>trim($input['title']),'summary'=>trim($input['summary'] ?? ''),'subtotal'=>$amount,'discount'=>$discount,'tax'=>$tax,'total'=>$total,'deposit'=>$this->nonNegative($input['deposit'] ?? 0),'status'=>'draft','valid_until'=>$input['valid_until'],'created_at'=>date('c')]);
            $this->db->insert('proposal_items', ['proposal_id'=>$id,'description'=>trim($input['description']),'quantity'=>1,'unit_price'=>$amount,'total'=>$amount]);
            if ($clientId) {
                $this->activity($clientId,'proposal.created','Proposal '.$number.' created for '.money($total).'.','proposal',$id);
            }
            $this->audit('created','proposal',$id,null,$input);
            return $id;
        });
    }

    public function createContract(array $input): int
    {
        $this->required($input,['client_id','start_date','status','scope']);
        if(!empty($input['end_date']) && $input['end_date']<$input['start_date']){throw new InvalidArgumentException('Contract end date cannot be before its start date.');}
        if(!in_array($input['status'],['draft','active','expired','terminated'],true)){throw new InvalidArgumentException('Invalid contract status.');}
        $id=$this->db->insert('contracts',['client_id'=>(int)$input['client_id'],'package_id'=>$this->nullableInt($input['package_id']??null),'project_id'=>$this->nullableInt($input['project_id']??null),'start_date'=>$input['start_date'],'end_date'=>$input['end_date']?:null,'payment_terms'=>trim($input['payment_terms']??''),'renewal_type'=>$input['renewal_type']??'manual','cancellation_terms'=>trim($input['cancellation_terms']??''),'scope'=>trim($input['scope']),'status'=>$input['status']]);
        $this->activity((int)$input['client_id'],'contract.created','Contract created with '.$input['status'].' status.','contract',$id);
        $this->audit('created','contract',$id,null,$input);
        return $id;
    }

    public function assignPackage(array $input): int
    {
        $this->required($input, ['client_id','package_id','start_date']);
        $package = $this->db->first("SELECT p.*, pp.monthly_fee, pp.base_price FROM packages p LEFT JOIN package_pricing pp ON pp.package_id=p.id AND pp.effective_to IS NULL WHERE p.id=? AND p.active=1", [(int)$input['package_id']]);
        if (!$package) {
            throw new InvalidArgumentException('Select a valid active package.');
        }
        $price = isset($input['custom_price']) && $input['custom_price'] !== '' ? $this->nonNegative($input['custom_price']) : (float)($package['monthly_fee'] ?: $package['base_price']);
        $id = $this->db->insert('subscriptions', ['client_id'=>(int)$input['client_id'],'package_id'=>$package['id'],'monthly_price'=>$price,'start_date'=>$input['start_date'],'renewal_date'=>$input['renewal_date'] ?: date('Y-m-d',strtotime($input['start_date'].' +1 year')),'contract_end_date'=>$input['renewal_date'] ?: date('Y-m-d',strtotime($input['start_date'].' +1 year')),'billing_frequency'=>$package['package_type']==='monthly'?'monthly':'one_time','deposit'=>0,'discount_percent'=>0,'tax_percent'=>0,'status'=>'active','created_at'=>date('c')]);
        $this->activity((int)$input['client_id'],'subscription.activated',$package['name'].' package assigned to the client.','subscription',$id);
        $this->audit('created','subscription',$id,null,$input);
        return $id;
    }

    public function updatePackagePricing(array $input): void
    {
        $this->required($input,['package_id','price','effective_from']);
        $package=$this->db->first('SELECT * FROM packages WHERE id=?',[(int)$input['package_id']]);
        $current=$this->db->first('SELECT * FROM package_pricing WHERE package_id=? AND effective_to IS NULL ORDER BY effective_from DESC LIMIT 1',[(int)$input['package_id']]);
        if(!$package || !$current){throw new InvalidArgumentException('Package pricing record not found.');}
        $price=$this->nonNegative($input['price']);
        $effective=$input['effective_from'];
        $this->db->transaction(function() use($input,$package,$current,$price,$effective): void {
            if($effective <= $current['effective_from']){
                $changes=['base_price'=>$package['package_type']==='monthly'?0:$price,'monthly_fee'=>$package['package_type']==='monthly'?$price:0,'setup_fee'=>$this->nonNegative($input['setup_fee']??$current['setup_fee']),'discount_percent'=>$this->nonNegative($input['discount_percent']??$current['discount_percent']),'tax_percent'=>$this->nonNegative($input['tax_percent']??$current['tax_percent']),'deposit_percent'=>$this->nonNegative($input['deposit_percent']??$current['deposit_percent']),'effective_from'=>$effective];
                $this->db->update('package_pricing',(int)$current['id'],$changes);
            }else{
                $this->db->execute('UPDATE package_pricing SET effective_to=? WHERE id=?',[date('Y-m-d',strtotime($effective.' -1 day')),$current['id']]);
                $this->db->insert('package_pricing',['package_id'=>$package['id'],'business_size_id'=>$current['business_size_id'],'base_price'=>$package['package_type']==='monthly'?0:$price,'monthly_fee'=>$package['package_type']==='monthly'?$price:0,'setup_fee'=>$this->nonNegative($input['setup_fee']??$current['setup_fee']),'minimum_price'=>$price,'maximum_price'=>$current['maximum_price'],'discount_percent'=>$this->nonNegative($input['discount_percent']??$current['discount_percent']),'tax_percent'=>$this->nonNegative($input['tax_percent']??$current['tax_percent']),'deposit_percent'=>$this->nonNegative($input['deposit_percent']??$current['deposit_percent']),'effective_from'=>$effective,'effective_to'=>null]);
            }
            $this->audit('pricing_changed','package',(int)$package['id'],$current,['price'=>$price,'effective_from'=>$effective]);
        });
    }

    public function updateRolePermissions(int $roleId, array $permissionIds): void
    {
        $role=$this->db->first("SELECT * FROM roles WHERE id=? AND slug NOT IN ('super_admin','admin')",[$roleId]);
        if(!$role){throw new InvalidArgumentException('That role cannot be modified.');}
        $valid=array_map('intval',array_column($this->permissions(),'id'));
        $permissionIds=array_values(array_unique(array_intersect($valid,array_map('intval',$permissionIds))));
        $before=array_map('intval',array_column($this->db->all('SELECT permission_id FROM role_permissions WHERE role_id=?',[$roleId]),'permission_id'));
        $this->db->transaction(function() use($roleId,$permissionIds,$before,$role): void {
            $this->db->execute('DELETE FROM role_permissions WHERE role_id=?',[$roleId]);
            foreach($permissionIds as $permissionId){$this->db->insert('role_permissions',['role_id'=>$roleId,'permission_id'=>$permissionId]);}
            $this->audit('permissions_changed','role',(int)$role['id'],['permission_ids'=>$before],['permission_ids'=>$permissionIds]);
        });
    }

    public function updateRole(array $input): void
    {
        $this->required($input, ['role_id', 'name', 'slug']);
        $roleId = (int) $input['role_id'];
        $role = $this->db->first("SELECT * FROM roles WHERE id=? AND slug NOT IN ('super_admin','admin')", [$roleId]);
        if (! $role) {
            throw new InvalidArgumentException('That role cannot be modified.');
        }

        $name = trim((string) $input['name']);
        $slug = strtolower(trim((string) $input['slug']));
        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($name) > 80) {
            throw new InvalidArgumentException('Role name cannot be longer than 80 characters.');
        }
        if (! preg_match('/^[a-z][a-z0-9_]{2,49}$/', $slug)) {
            throw new InvalidArgumentException('Role key must start with a letter and use only lowercase letters, numbers, and underscores.');
        }
        if (mb_strlen($description) > 500) {
            throw new InvalidArgumentException('Role description cannot be longer than 500 characters.');
        }
        if ($this->db->scalar('SELECT COUNT(*) FROM roles WHERE id!=? AND (slug=? OR name=?)', [$roleId, $slug, $name])) {
            throw new InvalidArgumentException('Another role already uses that name or key.');
        }

        $valid = array_map('intval', array_column($this->permissions(), 'id'));
        $permissionIds = array_values(array_unique(array_intersect($valid, array_map('intval', (array) ($input['permission_ids'] ?? [])))));
        $beforePermissionIds = array_map('intval', array_column($this->db->all('SELECT permission_id FROM role_permissions WHERE role_id=?', [$roleId]), 'permission_id'));
        sort($permissionIds);
        sort($beforePermissionIds);

        $before = ['name'=>$role['name'], 'slug'=>$role['slug'], 'description'=>$role['description'], 'permission_ids'=>$beforePermissionIds];
        $after = ['name'=>$name, 'slug'=>$slug, 'description'=>$description, 'permission_ids'=>$permissionIds];

        $this->db->transaction(function () use ($roleId, $name, $slug, $description, $permissionIds, $before, $after): void {
            $this->db->update('roles', $roleId, ['name'=>$name, 'slug'=>$slug, 'description'=>$description]);
            $this->db->execute('DELETE FROM role_permissions WHERE role_id=?', [$roleId]);
            foreach ($permissionIds as $permissionId) {
                $this->db->insert('role_permissions', ['role_id'=>$roleId, 'permission_id'=>$permissionId]);
            }
            $this->audit('updated', 'role', $roleId, $before, $after);
        });
    }

    public function createRole(array $input): int
    {
        $this->required($input,['name','slug']);
        $name=trim((string)$input['name']);
        $slug=strtolower(trim((string)$input['slug']));
        if(!preg_match('/^[a-z][a-z0-9_]{2,49}$/',$slug)){
            throw new InvalidArgumentException('Role key must start with a letter and use only lowercase letters, numbers, and underscores.');
        }
        if($this->db->scalar('SELECT COUNT(*) FROM roles WHERE slug=? OR name=?',[$slug,$name])){
            throw new InvalidArgumentException('A role with that name or key already exists.');
        }
        $permissionIds=(array)($input['permission_ids']??[]);
        return $this->db->transaction(function() use($input,$name,$slug,$permissionIds): int {
            $roleId=$this->db->insert('roles',['name'=>$name,'slug'=>$slug,'description'=>trim((string)($input['description']??'')),'created_at'=>date('c')]);
            $valid=array_map('intval',array_column($this->permissions(),'id'));
            foreach(array_unique(array_intersect($valid,array_map('intval',$permissionIds))) as $permissionId){
                $this->db->insert('role_permissions',['role_id'=>$roleId,'permission_id'=>$permissionId]);
            }
            $this->audit('created','role',$roleId,null,['name'=>$name,'slug'=>$slug,'permission_ids'=>$permissionIds]);
            return $roleId;
        });
    }

    public function updateUserAccess(array $input): void
    {
        $this->required($input,['user_id','role_id','status']);
        $user=$this->db->first("SELECT u.*,r.slug AS role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?",[(int)$input['user_id']]);
        if(!$user || $user['role_slug']==='super_admin'){
            throw new InvalidArgumentException('The Super Admin account cannot be modified here.');
        }
        $role=$this->db->first("SELECT * FROM roles WHERE id=? AND slug!='super_admin'",[(int)$input['role_id']]);
        if(!$role || !in_array($input['status'],['active','inactive'],true)){
            throw new InvalidArgumentException('Choose a valid role and account status.');
        }
        $password=(string)($input['password']??'');
        if($password!=='' && strlen($password)<10){
            throw new InvalidArgumentException('Replacement passwords must be at least 10 characters.');
        }
        $valid=array_map('intval',array_column($this->permissions(),'id'));
        $requested=(array)($input['permission_overrides']??[]);
        $overrides=[];
        foreach($requested as $permissionId=>$mode){
            $permissionId=(int)$permissionId;
            if(in_array($permissionId,$valid,true) && in_array($mode,['allow','deny'],true)){$overrides[$permissionId]=$mode==='allow';}
        }
        $before=['role_id'=>$user['role_id'],'status'=>$user['status'],'permission_overrides'=>$this->db->all('SELECT permission_id,allowed FROM user_permissions WHERE user_id=?',[$user['id']])];
        $this->db->transaction(function() use($input,$user,$password,$overrides,$before): void {
            $changes=['role_id'=>(int)$input['role_id'],'status'=>$input['status'],'updated_at'=>date('c')];
            if($password!==''){$changes['password_hash']=password_hash($password,PASSWORD_DEFAULT);}
            $this->db->update('users',(int)$user['id'],$changes);
            $this->db->execute('DELETE FROM user_permissions WHERE user_id=?',[$user['id']]);
            foreach($overrides as $permissionId=>$allowed){$this->db->insert('user_permissions',['user_id'=>$user['id'],'permission_id'=>$permissionId,'allowed'=>$allowed?1:0,'created_at'=>now()->toDateTimeString(),'updated_at'=>now()->toDateTimeString()]);}
            $this->audit('access_changed','user',(int)$user['id'],$before,['role_id'=>$changes['role_id'],'status'=>$changes['status'],'permission_overrides'=>$overrides]);
        });
    }

    public function recordPayment(array $input): void
    {
        $this->required($input, ['invoice_id','amount','payment_date','method']);
        $invoice = $this->db->first('SELECT * FROM invoices WHERE id=?', [(int)$input['invoice_id']]);
        $amount = $this->nonNegative($input['amount']);
        if (!$invoice || $amount <= 0 || $amount > ((float)$invoice['total']-(float)$invoice['amount_paid']) + 0.001) {
            throw new InvalidArgumentException('Payment must be positive and cannot exceed the outstanding balance.');
        }
        $this->db->transaction(function () use ($input, $invoice, $amount): void {
            $this->db->insert('payments', ['invoice_id'=>$invoice['id'],'amount'=>$amount,'payment_date'=>$input['payment_date'],'method'=>$input['method'],'reference'=>trim($input['reference'] ?? ''),'notes'=>trim($input['notes'] ?? ''),'recorded_by'=>$this->auth->user()['id'],'created_at'=>date('c')]);
            $paid = (float)$invoice['amount_paid']+$amount;
            $status = $paid >= (float)$invoice['total'] ? 'paid' : 'partially_paid';
            $this->db->execute('UPDATE invoices SET amount_paid=?, status=? WHERE id=?', [$paid,$status,$invoice['id']]);
            $this->activity((int)$invoice['client_id'],'payment.received','Payment of '.money($amount).' received for '.$invoice['invoice_number'].'.','invoice',(int)$invoice['id']);
            $this->audit('payment_recorded','invoice',(int)$invoice['id'],$invoice,['amount_paid'=>$paid,'status'=>$status]);
        });
    }

    public function createEmployee(array $input): int
    {
        $this->required($input, ['name','email','department','hourly_cost']);
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid employee email address.');
        }
        return $this->db->transaction(function () use ($input): int {
            $userId = null;
            if (!empty($input['create_login'])) {
                if (strlen((string)($input['password'] ?? '')) < 10) {
                    throw new InvalidArgumentException('Login passwords must be at least 10 characters.');
                }
                $userId = $this->db->insert('users', ['role_id'=>(int)$input['role_id'],'name'=>trim($input['name']),'email'=>trim($input['email']),'password_hash'=>password_hash($input['password'],PASSWORD_DEFAULT),'status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')]);
            }
            $id = $this->db->insert('employees', ['user_id'=>$userId,'name'=>trim($input['name']),'email'=>trim($input['email']),'phone'=>trim($input['phone'] ?? ''),'job_title'=>trim($input['job_title'] ?? ''),'department'=>$input['department'],'skills'=>trim($input['skills'] ?? ''),'hourly_cost'=>$this->nonNegative($input['hourly_cost']),'capacity_hours'=>$this->nonNegative($input['capacity_hours'] ?? 40),'status'=>'active','created_at'=>date('c')]);
            $this->audit('created','employee',$id,null,array_diff_key($input,['password'=>true]));
            return $id;
        });
    }

    public function logTime(array $input): int
    {
        $this->required($input, ['employee_id','project_id','entry_date','hours','description']);
        $hours = (float)$input['hours'];
        if ($hours <= 0 || $hours > 24) {
            throw new InvalidArgumentException('Hours must be greater than zero and no more than 24.');
        }
        $project = $this->db->first('SELECT client_id FROM projects WHERE id=?', [(int)$input['project_id']]);
        if (!$project) {
            throw new InvalidArgumentException('Select a valid project.');
        }
        return $this->db->transaction(function () use ($input, $hours, $project): int {
            $id = $this->db->insert('time_entries', ['employee_id'=>(int)$input['employee_id'],'client_id'=>$project['client_id'],'project_id'=>(int)$input['project_id'],'task_id'=>$this->nullableInt($input['task_id'] ?? null),'entry_date'=>$input['entry_date'],'hours'=>$hours,'description'=>trim($input['description']),'billable'=>!empty($input['billable'])?1:0,'created_at'=>date('c')]);
            $this->db->execute('UPDATE projects SET actual_hours=actual_hours+? WHERE id=?', [$hours,(int)$input['project_id']]);
            if (!empty($input['task_id'])) {
                $this->db->execute('UPDATE project_tasks SET actual_hours=actual_hours+? WHERE id=?', [$hours,(int)$input['task_id']]);
            }
            $this->audit('created','time_entry',$id,null,$input);
            return $id;
        });
    }

    public function createContent(array $input): int
    {
        $this->required($input, ['client_id','title','platform','content_type']);
        $id = $this->db->insert('content_items', ['client_id'=>(int)$input['client_id'],'project_id'=>$this->nullableInt($input['project_id'] ?? null),'assigned_employee_id'=>$this->nullableInt($input['assigned_employee_id'] ?? null),'title'=>trim($input['title']),'platform'=>$input['platform'],'content_type'=>$input['content_type'],'caption'=>trim($input['caption'] ?? ''),'hashtags'=>trim($input['hashtags'] ?? ''),'scheduled_at'=>$input['scheduled_at'] ?: null,'status'=>'idea','approval_status'=>'pending','created_at'=>date('c')]);
        $this->activity((int)$input['client_id'],'content.created','Content item created: '.trim($input['title']),'content',$id);
        $this->audit('created','content',$id,null,$input);
        return $id;
    }

    public function uploadMedia(array $input, array $file): int
    {
        $this->required($input, ['client_id','content_type']);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            throw new InvalidArgumentException('Choose a file that can be uploaded.');
        }
        if ((int)$file['size'] > 25 * 1024 * 1024) {
            throw new InvalidArgumentException('Files must be 25 MB or smaller.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($file['tmp_name']);
        $extensions = [
            'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4','video/quicktime'=>'mov',
            'application/pdf'=>'pdf','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
            'application/vnd.ms-excel'=>'xls','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
        ];
        if (!isset($extensions[$mime])) {
            throw new InvalidArgumentException('That file type is not allowed. Use JPG, PNG, WebP, MP4, MOV, PDF, DOCX, or XLSX.');
        }
        $clientId=(int)$input['client_id']; $projectId=$this->nullableInt($input['project_id'] ?? null);
        $relative='client-'.$clientId.DIRECTORY_SEPARATOR.($projectId?'project-'.$projectId:'general').DIRECTORY_SEPARATOR.date('Y').DIRECTORY_SEPARATOR.date('m');
        $base=storage_path('app/private/uploads');
        $directory=$base.DIRECTORY_SEPARATOR.$relative;
        if(!is_dir($directory) && !mkdir($directory,0775,true) && !is_dir($directory)) {
            throw new InvalidArgumentException('The media folder could not be prepared.');
        }
        $storedName=bin2hex(random_bytes(18)).'.'.$extensions[$mime];
        $destination=$directory.DIRECTORY_SEPARATOR.$storedName;
        if(!move_uploaded_file($file['tmp_name'],$destination)) {
            throw new InvalidArgumentException('The file could not be stored. Please try again.');
        }
        $employeeId=$this->nullableInt($this->db->scalar('SELECT id FROM employees WHERE user_id=?',[$this->auth->user()['id']]));
        $id=$this->db->insert('media',['client_id'=>$clientId,'project_id'=>$projectId,'visit_id'=>$this->nullableInt($input['visit_id'] ?? null),'content_type'=>$input['content_type'],'file_path'=>$relative.DIRECTORY_SEPARATOR.$storedName,'original_name'=>basename((string)$file['name']),'mime_type'=>$mime,'file_size'=>(int)$file['size'],'captured_at'=>$input['captured_at'] ?: null,'created_by'=>$employeeId,'tags'=>trim($input['tags'] ?? ''),'usage_rights'=>trim($input['usage_rights'] ?? ''),'status'=>'raw','created_at'=>date('c')]);
        $this->activity($clientId,'media.uploaded','Media uploaded: '.basename((string)$file['name']),'media',$id);
        $this->audit('created','media',$id,null,['client_id'=>$clientId,'project_id'=>$projectId,'original_name'=>basename((string)$file['name']),'mime_type'=>$mime,'file_size'=>(int)$file['size']]);
        return $id;
    }

    public function updateSimpleStatus(string $entity, int $id, string $status): void
    {
        $allowed = [
            'task'=>['table'=>'project_tasks','statuses'=>['todo','in_progress','waiting','review','completed']],
            'content'=>['table'=>'content_items','statuses'=>['idea','draft','internal_review','client_review','approved','scheduled','published','archived']],
        ];
        if (!isset($allowed[$entity]) || !in_array($status,$allowed[$entity]['statuses'],true)) {
            throw new InvalidArgumentException('Invalid status change.');
        }
        $table = $allowed[$entity]['table'];
        $before = $this->db->first('SELECT * FROM `'.$table.'` WHERE id=?', [$id]);
        if (!$before) {
            throw new InvalidArgumentException('Record not found.');
        }
        $extra = $entity==='task' && $status==='completed' ? ', completed_at = ?' : '';
        $params = [$status];
        if ($extra) { $params[] = date('c'); }
        $params[] = $id;
        $this->db->execute('UPDATE `'.$table.'` SET status=?'.$extra.' WHERE id=?', $params);
        $this->audit('status_changed',$entity,$id,$before,['status'=>$status]);
    }

    public function updateContentApproval(int $id, string $status, string $comments): void
    {
        if(!in_array($status,['pending','approved','rejected','changes_requested'],true)){
            throw new InvalidArgumentException('Invalid approval decision.');
        }
        if(in_array($status,['rejected','changes_requested'],true) && trim($comments)===''){
            throw new InvalidArgumentException('Add a comment when rejecting or requesting changes.');
        }
        $before=$this->db->first('SELECT * FROM content_items WHERE id=?',[$id]);
        if(!$before){throw new InvalidArgumentException('Content item not found.');}
        $contentStatus=$status==='approved'?'approved':($status==='changes_requested'?'draft':$before['status']);
        $this->db->execute('UPDATE content_items SET approval_status=?, approval_comments=?, status=? WHERE id=?',[$status,trim($comments),$contentStatus,$id]);
        $this->activity((int)$before['client_id'],'content.approval_changed','Content approval changed to '.str_replace('_',' ',$status).'.','content',$id);
        $this->audit('approval_changed','content',$id,$before,['approval_status'=>$status,'approval_comments'=>$comments,'status'=>$contentStatus]);
    }

    public function updateOpportunity(array $input): void
    {
        $this->required($input, ['opportunity_id','title','estimated_value']);
        $id = (int)$input['opportunity_id'];
        $before = $this->db->first('SELECT * FROM opportunities WHERE id=?', [$id]);
        if (!$before) {
            throw new InvalidArgumentException('Select a valid opportunity.');
        }
        $ownerId = $this->nullableInt($input['owner_id'] ?? null);
        if ($ownerId && !$this->db->scalar("SELECT id FROM employees WHERE id=? AND status='active'", [$ownerId])) {
            throw new InvalidArgumentException('Select a valid active owner.');
        }
        $expectedCloseDate = trim((string)($input['expected_close_date'] ?? ''));
        if ($expectedCloseDate !== '' && strtotime($expectedCloseDate) === false) {
            throw new InvalidArgumentException('Enter a valid expected close date.');
        }
        $serviceIds = array_values(array_unique(array_filter(array_map('intval', (array)($input['service_ids'] ?? [])))));
        $serviceNames = [];
        if ($serviceIds) {
            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $serviceNames = array_column($this->db->all("SELECT name FROM services WHERE active=1 AND id IN ({$placeholders}) ORDER BY name", $serviceIds), 'name');
            if (count($serviceNames) !== count($serviceIds)) {
                throw new InvalidArgumentException('One or more selected services are unavailable.');
            }
        }
        $after = [
            'owner_id'=>$ownerId,
            'title'=>trim((string)$input['title']),
            'estimated_value'=>$this->nonNegative($input['estimated_value']),
            'services'=>implode(', ', $serviceNames),
            'expected_close_date'=>$expectedCloseDate ?: null,
            'next_action'=>trim((string)($input['next_action'] ?? '')) ?: null,
            'notes'=>trim((string)($input['notes'] ?? '')) ?: null,
            'updated_at'=>date('c'),
        ];

        $this->db->transaction(function () use ($id, $before, $after, $serviceIds): void {
            $this->db->update('opportunities', $id, $after);
            if ($before['lead_id']) {
                $this->db->execute('UPDATE leads SET assigned_employee_id=?,services_interested=?,updated_at=? WHERE id=?', [$after['owner_id'],$after['services'],date('c'),$before['lead_id']]);
                $this->db->execute('DELETE FROM lead_service_interests WHERE lead_id=?', [$before['lead_id']]);
                foreach ($serviceIds as $serviceId) {
                    $this->db->insert('lead_service_interests', ['lead_id'=>(int)$before['lead_id'],'service_id'=>$serviceId]);
                }
            }
            $this->audit('updated','opportunity',$id,$before,$after);
        });
    }

    public function moveOpportunity(array $input): ?int
    {
        $this->required($input, ['opportunity_id','stage_id']);
        $id = (int)$input['opportunity_id'];
        $stageId = (int)$input['stage_id'];
        $stage = $this->db->first('SELECT * FROM pipeline_stages WHERE id=?', [$stageId]);
        $before = $this->db->first('SELECT o.*,ps.slug AS current_stage_slug FROM opportunities o JOIN pipeline_stages ps ON ps.id=o.stage_id WHERE o.id=?', [$id]);
        if (!$stage || !$before) {
            throw new InvalidArgumentException('Opportunity or stage not found.');
        }
        if ((int)$before['stage_id'] === $stageId) {
            return null;
        }
        if ($before['current_stage_slug'] === 'won') {
            throw new InvalidArgumentException('A won opportunity cannot be reopened from the pipeline. Manage it from the client record.');
        }
        if ($stage['slug'] === 'discovery' && $before['lead_id']) {
            $hasDiscovery = (int)$this->db->scalar('SELECT COUNT(*) FROM consultations WHERE lead_id=?', [$before['lead_id']]);
            if (!$hasDiscovery) {
                throw new InvalidArgumentException('Complete the Discovery form before moving this opportunity into Discovery.');
            }
        }
        $linkedProposal = null;
        if ($stage['slug'] === 'proposal') {
            $linkedProposal = $this->db->first('SELECT * FROM proposals WHERE opportunity_id=? ORDER BY id DESC LIMIT 1', [$id]);
            if (!$linkedProposal) {
                throw new InvalidArgumentException('Create and link a proposal before moving this opportunity to Proposal Sent.');
            }
        }
        if ($stage['slug'] === 'lost' && trim((string)($input['lost_reason'] ?? '')) === '') {
            throw new InvalidArgumentException('Explain why this opportunity was lost before closing it.');
        }

        if ($stage['slug'] === 'won' && $before['lead_id'] && !$before['client_id']) {
            $lead = $this->db->first('SELECT converted_client_id FROM leads WHERE id=?', [$before['lead_id']]);
            $clientId = $this->nullableInt($lead['converted_client_id'] ?? null) ?: $this->convertLead((int)$before['lead_id']);
            $after = $this->db->first('SELECT * FROM opportunities WHERE id=?', [$id]);
            $this->audit('stage_changed','opportunity',$id,$before,array_merge($after ?: [],['stage_name'=>$stage['name']]));
            return $clientId;
        }

        $clientId = $this->nullableInt($before['client_id']);
        $lostReason = $stage['slug'] === 'lost' ? trim((string)$input['lost_reason']) : null;
        $now = date('c');
        $this->db->transaction(function () use ($id, $stage, $before, $lostReason, $now, $clientId, $linkedProposal): void {
            $values = [
                'previous_stage_id'=>$stage['is_closed'] ? (int)$before['stage_id'] : null,
                'stage_id'=>(int)$stage['id'],
                'probability'=>(int)$stage['win_probability'],
                'lost_reason'=>$lostReason,
                'stage_entered_at'=>$now,
                'closed_at'=>$stage['is_closed'] ? $now : null,
                'next_action'=>$stage['slug'] === 'lost' ? 'Closed — lost' : ($stage['slug'] === 'won' ? 'Begin client onboarding' : ($before['next_action'] ?: 'Set the next sales action')),
                'updated_at'=>$now,
            ];
            $this->db->update('opportunities', $id, $values);

            if ($stage['slug'] === 'proposal' && $linkedProposal && $linkedProposal['status'] !== 'sent') {
                $this->db->execute("UPDATE proposals SET status='sent' WHERE id=?", [$linkedProposal['id']]);
                $this->audit('status_changed','proposal',(int)$linkedProposal['id'],$linkedProposal,array_merge($linkedProposal,['status'=>'sent']));
            }

            if ($before['lead_id']) {
                $leadStatus = in_array($stage['slug'], ['new','contacted','qualified','discovery','proposal','negotiation','lost'], true) ? $stage['slug'] : null;
                if ($leadStatus) {
                    $this->db->execute('UPDATE leads SET status=?,lost_reason=?,lost_at=?,updated_at=? WHERE id=?', [$leadStatus,$lostReason,$stage['slug']==='lost'?$now:null,$now,$before['lead_id']]);
                }
            }
            if ($clientId) {
                $this->activity($clientId,'opportunity.stage_changed','Opportunity moved to '.$stage['name'].'.','opportunity',$id);
            }
            $this->audit('stage_changed','opportunity',$id,$before,array_merge($values,['stage_name'=>$stage['name']]));
        });
        return null;
    }

    public function createPipelineStage(array $input): int
    {
        $this->required($input, ['name','position','win_probability']);
        $name = trim((string)$input['name']);
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $name), '_'));
        if ($slug === '' || $this->db->scalar('SELECT id FROM pipeline_stages WHERE slug=?', [$slug])) {
            throw new InvalidArgumentException('Use a unique pipeline stage name.');
        }
        $probability = (int)$input['win_probability'];
        if ($probability < 0 || $probability > 100) {
            throw new InvalidArgumentException('Win probability must be between 0 and 100.');
        }
        $color = $this->pipelineColor((string)($input['color'] ?? 'info'));
        return $this->db->transaction(function () use ($name, $slug, $probability, $color, $input): int {
            $id = $this->db->insert('pipeline_stages', ['name'=>$name,'slug'=>$slug,'position'=>999,'win_probability'=>$probability,'color'=>$color,'is_closed'=>0]);
            $this->reorderPipelineStage($id, (int)$input['position']);
            $this->audit('created','pipeline_stage',$id,null,['name'=>$name,'slug'=>$slug,'win_probability'=>$probability,'color'=>$color,'position'=>(int)$input['position']]);
            return $id;
        });
    }

    public function updatePipelineStage(array $input): void
    {
        $this->required($input, ['stage_id','name','position','win_probability']);
        $id = (int)$input['stage_id'];
        $before = $this->db->first('SELECT * FROM pipeline_stages WHERE id=?', [$id]);
        if (!$before) {
            throw new InvalidArgumentException('Select a valid pipeline stage.');
        }
        $name = trim((string)$input['name']);
        $probability = (int)$input['win_probability'];
        if ($probability < 0 || $probability > 100) {
            throw new InvalidArgumentException('Win probability must be between 0 and 100.');
        }
        if ($before['slug'] === 'won') {
            $probability = 100;
        } elseif ($before['slug'] === 'lost') {
            $probability = 0;
        }
        $after = ['name'=>$name,'win_probability'=>$probability,'color'=>$this->pipelineColor((string)($input['color'] ?? $before['color']))];
        $this->db->transaction(function () use ($id, $before, $after, $input): void {
            $this->db->update('pipeline_stages', $id, $after);
            $this->db->execute('UPDATE opportunities SET probability=? WHERE stage_id=?', [$after['win_probability'],$id]);
            $this->reorderPipelineStage($id, (int)$input['position']);
            $updated = $this->db->first('SELECT * FROM pipeline_stages WHERE id=?', [$id]);
            $this->audit('updated','pipeline_stage',$id,$before,$updated);
        });
    }

    public function addNote(int $clientId, string $body): void
    {
        if (trim($body)==='') {
            throw new InvalidArgumentException('Note cannot be empty.');
        }
        $id = $this->db->insert('notes', ['client_id'=>$clientId,'user_id'=>$this->auth->user()['id'],'body'=>trim($body),'created_at'=>date('c')]);
        $this->activity($clientId,'note.added','A note was added to the client record.','note',$id);
    }

    public function updateClientHealth(int $clientId, string $health, string $notes): void
    {
        if (!in_array($health,['healthy','attention','at_risk'],true)) {
            throw new InvalidArgumentException('Invalid health status.');
        }
        $before = $this->db->first('SELECT health, health_notes FROM clients WHERE id=?', [$clientId]);
        $this->db->execute('UPDATE clients SET health=?, health_notes=? WHERE id=?', [$health,trim($notes),$clientId]);
        $this->activity($clientId,'client.health_changed','Client health changed to '.str_replace('_',' ',$health).'.','client',$clientId);
        $this->audit('health_changed','client',$clientId,$before,['health'=>$health,'health_notes'=>$notes]);
    }

    public function search(string $query): array
    {
        $like = '%' . trim($query) . '%';
        if (strlen(trim($query)) < 2) { return []; }
        $results = [];
        foreach ($this->db->all("SELECT c.id, b.name AS title, 'Client' AS type, c.name AS subtitle FROM clients c JOIN businesses b ON b.id=c.business_id WHERE b.name LIKE ? OR c.name LIKE ? LIMIT 8",[$like,$like]) as $row) { $row['route']='client'; $results[]=$row; }
        foreach ($this->db->all("SELECT id, company_name AS title, 'Lead' AS type, CONCAT(first_name, ' ', last_name) AS subtitle FROM leads WHERE company_name LIKE ? OR first_name LIKE ? OR last_name LIKE ? LIMIT 8",[$like,$like,$like]) as $row) { $row['route']='leads'; $results[]=$row; }
        foreach ($this->db->all("SELECT id, name AS title, 'Project' AS type, status AS subtitle FROM projects WHERE name LIKE ? LIMIT 8",[$like]) as $row) { $row['route']='projects'; $results[]=$row; }
        foreach ($this->db->all("SELECT id, invoice_number AS title, 'Invoice' AS type, status AS subtitle FROM invoices WHERE invoice_number LIKE ? LIMIT 8",[$like]) as $row) { $row['route']='invoices'; $results[]=$row; }
        return array_slice($results,0,20);
    }

    public function visitUsage(int $subscriptionId, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $month = date('Y-m', strtotime($date));
        $subscription = $this->db->first("SELECT s.*, pl.included_quantity, pl.overage_price FROM subscriptions s LEFT JOIN package_limits pl ON pl.package_id=s.package_id AND pl.limit_key='content_visits' WHERE s.id=?", [$subscriptionId]);
        if (!$subscription) { return ['included'=>0,'used'=>0,'remaining'=>0,'additional'=>0,'charges'=>0,'overage_price'=>0]; }
        $used = (int)$this->db->scalar("SELECT COUNT(*) FROM content_visits WHERE subscription_id=? AND substr(visit_date,1,7)=? AND status='completed' AND is_additional=0",[$subscriptionId,$month]);
        $additional = (int)$this->db->scalar("SELECT COUNT(*) FROM content_visits WHERE subscription_id=? AND substr(visit_date,1,7)=? AND status='completed' AND is_additional=1",[$subscriptionId,$month]);
        $charges = (float)$this->db->scalar("SELECT COALESCE(SUM(additional_charge),0) FROM content_visits WHERE subscription_id=? AND substr(visit_date,1,7)=? AND status='completed'",[$subscriptionId,$month]);
        $included = (int)($subscription['included_quantity'] ?? 0);
        return ['included'=>$included,'used'=>$used,'remaining'=>max(0,$included-$used),'additional'=>$additional,'charges'=>$charges,'overage_price'=>(float)($subscription['overage_price'] ?? 0)];
    }

    private function clientProfitability(int $clientId): array
    {
        $revenue = (float)$this->db->scalar("SELECT COALESCE(SUM(amount_paid),0) FROM invoices WHERE client_id=?",[$clientId]);
        $cost = (float)$this->db->scalar("SELECT COALESCE(SUM(te.hours*e.hourly_cost),0) FROM time_entries te JOIN employees e ON e.id=te.employee_id WHERE te.client_id=?",[$clientId]);
        $hours = (float)$this->db->scalar("SELECT COALESCE(SUM(hours),0) FROM time_entries WHERE client_id=?",[$clientId]);
        return ['revenue'=>$revenue,'cost'=>$cost,'profit'=>$revenue-$cost,'margin'=>$revenue>0?(($revenue-$cost)/$revenue*100):0,'hours'=>$hours];
    }

    private function activity(int $clientId, string $type, string $description, string $entityType, int $entityId): void
    {
        $this->db->insert('activities', ['client_id'=>$clientId,'user_id'=>$this->auth->user()['id'],'type'=>$type,'description'=>$description,'entity_type'=>$entityType,'entity_id'=>$entityId,'created_at'=>date('c')]);
    }

    private function audit(string $action, string $entityType, int $entityId, $before, $after): void
    {
        Audit::log($this->db,(int)$this->auth->user()['id'],$action,$entityType,$entityId,$before,$after);
    }

    private function opportunityHistory(int $opportunityId): array
    {
        $rows = $this->db->all("SELECT a.action,a.new_values,a.created_at,u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.entity_type='opportunity' AND a.entity_id=? ORDER BY a.created_at DESC,a.id DESC LIMIT 8", [$opportunityId]);
        foreach ($rows as &$row) {
            $values = json_decode((string)($row['new_values'] ?? ''), true) ?: [];
            if ($row['action'] === 'stage_changed') {
                $stageName = (string)($values['stage_name'] ?? '');
                if ($stageName === '' && !empty($values['stage_id'])) {
                    $stageName = (string)$this->db->scalar('SELECT name FROM pipeline_stages WHERE id=?', [(int)$values['stage_id']]);
                }
                $row['summary'] = 'Moved to '.($stageName ?: 'another stage');
                if (!empty($values['lost_reason'])) {
                    $row['summary'] .= ' — '.$values['lost_reason'];
                }
            } elseif ($row['action'] === 'updated') {
                $row['summary'] = 'Opportunity details updated';
            } else {
                $row['summary'] = ucwords(str_replace('_', ' ', (string)$row['action']));
            }
        }
        return $rows;
    }

    private function reorderPipelineStage(int $stageId, int $requestedPosition): void
    {
        $rows = $this->db->all('SELECT id,slug,is_closed FROM pipeline_stages ORDER BY position,id');
        $target = null;
        $openIds = [];
        $wonId = null;
        $lostId = null;
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            if ($id === $stageId) {
                $target = $row;
                continue;
            }
            if ($row['slug'] === 'won') {
                $wonId = $id;
            } elseif ($row['slug'] === 'lost') {
                $lostId = $id;
            } elseif (!$row['is_closed']) {
                $openIds[] = $id;
            }
        }
        if (!$target) {
            throw new InvalidArgumentException('Pipeline stage not found.');
        }
        if (!$target['is_closed']) {
            $position = max(1, min(count($openIds) + 1, $requestedPosition));
            array_splice($openIds, $position - 1, 0, [$stageId]);
        } elseif ($target['slug'] === 'won') {
            $wonId = $stageId;
        } elseif ($target['slug'] === 'lost') {
            $lostId = $stageId;
        }
        $ids = array_values(array_filter(array_merge($openIds, [$wonId, $lostId])));
        foreach ($ids as $index => $id) {
            $this->db->execute('UPDATE pipeline_stages SET position=? WHERE id=?', [$index + 1, $id]);
        }
    }

    private function pipelineColor(string $color): string
    {
        return in_array($color, ['info','primary','warning','success','danger','purple','secondary'], true) ? $color : 'info';
    }

    private function required(array $input, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
                throw new InvalidArgumentException(ucwords(str_replace('_',' ',$field)).' is required.');
            }
        }
    }

    private function nonNegative($value): float
    {
        if (!is_numeric($value) || (float)$value < 0) {
            throw new InvalidArgumentException('Numeric values cannot be negative.');
        }
        return (float)$value;
    }

    private function calculateLeadScore(string $companySize, string $businessYears, string $businessStage, string $budgetRange, int $serviceCount): int
    {
        $sizePoints = ['1_10'=>5,'11_50'=>10,'51_100'=>15,'101_250'=>20,'251_plus'=>25];
        $yearPoints = ['under_1'=>2,'1_2'=>6,'3_5'=>10,'6_10'=>14,'10_plus'=>18];
        $stagePoints = ['new_business'=>8,'existing_business'=>15];
        $budgetPoints = ['under_1k'=>3,'1k_5k'=>8,'5k_10k'=>13,'10k_25k'=>18,'25k_50k'=>22,'50k_plus'=>25];

        return min(100,
            5
            + ($sizePoints[$companySize] ?? 0)
            + ($yearPoints[$businessYears] ?? 0)
            + ($stagePoints[$businessStage] ?? 0)
            + ($budgetPoints[$budgetRange] ?? 0)
            + min(12, max(0, $serviceCount) * 3)
        );
    }

    private function leadQualification(array $input): array
    {
        $companySizes = ['1_10'=>5,'11_50'=>30,'51_100'=>75,'101_250'=>175,'251_plus'=>300];
        $businessYears = ['under_1'=>0,'1_2'=>2,'3_5'=>4,'6_10'=>8,'10_plus'=>12];
        $businessStages = ['new_business','existing_business'];
        $budgetValues = ['under_1k'=>750,'1k_5k'=>3000,'5k_10k'=>7500,'10k_25k'=>17500,'25k_50k'=>37500,'50k_plus'=>50000];

        $companySizeRange = trim((string) ($input['company_size_range'] ?? ''));
        $yearsInBusinessRange = trim((string) ($input['years_in_business_range'] ?? ''));
        $businessStage = trim((string) ($input['business_stage'] ?? ''));
        $budgetRange = trim((string) ($input['budget_range'] ?? ''));
        if ($companySizeRange !== '' && ! array_key_exists($companySizeRange, $companySizes)) {
            throw new InvalidArgumentException('Select a valid company size.');
        }
        if ($yearsInBusinessRange !== '' && ! array_key_exists($yearsInBusinessRange, $businessYears)) {
            throw new InvalidArgumentException('Select a valid business age.');
        }
        if ($businessStage !== '' && ! in_array($businessStage, $businessStages, true)) {
            throw new InvalidArgumentException('Select a valid business stage.');
        }
        if ($budgetRange !== '' && ! array_key_exists($budgetRange, $budgetValues)) {
            throw new InvalidArgumentException('Select a valid budget range.');
        }

        $serviceIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['service_ids'] ?? [])))));
        $selectedServices = [];
        if ($serviceIds) {
            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $selectedServices = $this->db->all("SELECT id,name FROM services WHERE active=1 AND id IN ($placeholders) ORDER BY name", $serviceIds);
            if (count($selectedServices) !== count($serviceIds)) {
                throw new InvalidArgumentException('One or more selected services are unavailable.');
            }
        }

        return [
            'company_size_range'=>$companySizeRange ?: null,
            'years_in_business_range'=>$yearsInBusinessRange ?: null,
            'business_stage'=>$businessStage ?: null,
            'budget_range'=>$budgetRange ?: null,
            'employee_count'=>$companySizeRange !== '' ? $companySizes[$companySizeRange] : null,
            'estimated_budget'=>$budgetRange !== '' ? (float)$budgetValues[$budgetRange] : $this->nonNegative($input['estimated_budget'] ?? 0),
            'service_ids'=>$serviceIds,
            'service_names'=>implode(', ', array_column($selectedServices, 'name')),
            'lead_score'=>$this->calculateLeadScore($companySizeRange, $yearsInBusinessRange, $businessStage, $budgetRange, count($serviceIds)),
        ];
    }

    private function findLeadDuplicate(string $email, string $phone, string $companyName): ?array
    {
        $conditions = ['LOWER(email)=LOWER(?)','LOWER(company_name)=LOWER(?)'];
        $parameters = [trim($email),trim($companyName)];
        if (trim($phone) !== '') {
            $conditions[] = 'phone=?';
            $parameters[] = trim($phone);
        }

        return $this->db->first('SELECT id,company_name,email,phone FROM leads WHERE '.implode(' OR ', $conditions).' ORDER BY id DESC LIMIT 1', $parameters);
    }

    private function boundedInt($value, int $min, int $max): int
    {
        return max($min,min($max,(int)$value));
    }

    private function nullableInt($value): ?int
    {
        return $value === null || $value === '' ? null : (int)$value;
    }
}

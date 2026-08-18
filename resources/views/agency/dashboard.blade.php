<div class="page-title-wrap">
    <div><span class="eyebrow"><?= e(strtoupper(date('l · F j, Y'))) ?></span><h1>Good morning, <?= e(explode(' ', $auth->user()['name'])[0]) ?>.</h1><p>Here is the pulse of the agency and what needs attention next.</p></div>
    <div class="d-flex gap-2"><a href="<?= e(url('reports')) ?>" class="btn btn-outline-secondary mr-2"><i class="fal fa-chart-line mr-1"></i> View reports</a><a href="<?= e(url('leads')) ?>#modal-add" class="btn btn-primary"><i class="fal fa-plus mr-1"></i> New lead</a></div>
</div>

<section class="kpi-grid" aria-label="Agency key performance indicators">
    <?php
    $cards = [
        ['Monthly recurring revenue',money($data['kpis']['mrr']),'Active retainers','fa-sync','#ff5a00'],
        ['Collected this month',money($data['kpis']['revenue_month']),'Cash received','fa-wallet','#2ca879'],
        ['Open pipeline',money($data['kpis']['pipeline']),'Unclosed opportunity value','fa-filter','#ff7a1a'],
        ['Outstanding invoices',money($data['kpis']['outstanding']),'Needs collection follow-up','fa-file-invoice-dollar','#e75b6c'],
        ['Active clients',number_format($data['kpis']['active_clients']),'Across all services','fa-building','#ff5a00'],
        ['Active projects',number_format($data['kpis']['open_projects']),'In delivery','fa-briefcase','#161616'],
        ['Overdue tasks',number_format($data['kpis']['overdue_tasks']),'Requires action today','fa-exclamation-triangle','#f0b44d'],
        ['Overdue lead follow-ups',number_format($data['kpis']['overdue_lead_followups']),'Sales conversations requiring action','fa-phone-office','#e75b6c'],
        ['Revenue this year',money($data['kpis']['revenue_year']),'Recorded payments','fa-chart-line','#2ca879'],
    ];
    foreach($cards as $card): ?>
        <article class="kpi-card" style="--kpi-color:<?= e($card[4]) ?>"><div class="kpi-label"><?= e($card[0]) ?></div><div class="kpi-value"><?= e($card[1]) ?></div><div class="kpi-meta"><?= e($card[2]) ?></div><i class="fal <?= e($card[3]) ?> kpi-icon"></i></article>
    <?php endforeach; ?>
</section>

<?php if($data['lead_followup_reminders']): ?>
<section class="panel followup-reminder-panel">
    <div class="panel-hdr"><h2>Lead follow-up reminders</h2><div class="panel-toolbar"><a href="<?= e(url('leads').'?follow_up_state=overdue') ?>" class="btn btn-panel">Review overdue leads <i class="fal fa-arrow-right ml-1"></i></a></div></div>
    <div class="panel-container show"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Lead</th><th>Owner</th><th>Fit score</th><th>Follow-up</th><th class="text-right">Action</th></tr></thead><tbody><?php foreach($data['lead_followup_reminders'] as $reminder): ?><tr><td><strong><?= e($reminder['company_name']) ?></strong></td><td><?= e($reminder['owner_name'] ?: 'Unassigned') ?></td><td><?= (int)$reminder['lead_score'] ?>/100</td><td><span class="badge badge-soft-<?= $reminder['reminder_state']==='overdue'?'danger':'warning' ?>"><?= $reminder['reminder_state']==='overdue'?'Overdue':'Due soon' ?> · <?= e(date('M j, g:i A',strtotime($reminder['next_follow_up_at']))) ?></span></td><td class="text-right"><a href="<?= e(url('lead',['id'=>(int)$reminder['id']])) ?>" class="btn btn-sm btn-outline-primary">Open lead</a></td></tr><?php endforeach; ?></tbody></table></div></div>
</section>
<?php endif; ?>

<div class="row">
    <div class="col-xl-8">
        <section class="panel">
            <div class="panel-hdr"><h2>Sales pipeline <span class="fw-300">velocity</span></h2><div class="panel-toolbar"><a href="<?= e(url('pipeline')) ?>" class="btn btn-panel">Open board <i class="fal fa-arrow-right ml-1"></i></a></div></div>
            <div class="panel-container show"><div class="panel-content">
                <?php $maxPipeline=max(1,max(array_map(static fn($s)=>(float)$s['value'],$data['pipeline_stages']))); ?>
                <?php foreach($data['pipeline_stages'] as $stage): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1"><span class="font-weight-bold fs-sm"><?= e($stage['name']) ?> <small class="text-muted ml-1"><?= (int)$stage['deals'] ?> deals</small></span><strong><?= e(money($stage['value'])) ?></strong></div>
                        <div class="progress-thin" style="height:8px"><span style="width:<?= e((string)round((float)$stage['value']/$maxPipeline*100)) ?>%;background:linear-gradient(90deg,#ff4d00,#ff8a2b)"></span></div>
                    </div>
                <?php endforeach; ?>
            </div></div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel h-100">
            <div class="panel-hdr"><h2>Revenue <span class="fw-300">by client</span></h2></div>
            <div class="panel-container show"><div class="panel-content">
                <div class="revenue-chart-shell">
                    <canvas id="revenueClientChart" data-labels="<?= e(json_encode(array_column($data['revenue_by_client'],'name'))) ?>" data-values="<?= e(json_encode(array_map('floatval',array_column($data['revenue_by_client'],'value')))) ?>" aria-label="Revenue by client chart"></canvas>
                </div>
                <?php $maxRevenue=max(1,max(array_map(static fn($s)=>(float)$s['value'],$data['revenue_by_client']))); ?>
                <div class="mt-3"><?php foreach($data['revenue_by_client'] as $client): ?>
                    <div class="mb-3"><div class="d-flex justify-content-between"><span class="fs-sm text-truncate mr-2"><?= e($client['name']) ?></span><strong class="fs-sm"><?= e(money($client['value'])) ?></strong></div><div class="progress-thin"><span style="width:<?= e((string)round((float)$client['value']/$maxRevenue*100)) ?>%;background:#ff5a00"></span></div></div>
                <?php endforeach; ?></div>
            </div></div>
        </section>
    </div>
</div>

<div class="row mt-3">
    <div class="col-xl-7">
        <section class="panel">
            <div class="panel-hdr"><h2>Priority work</h2><div class="panel-toolbar"><a href="<?= e(url('tasks')) ?>" class="btn btn-panel">All tasks</a></div></div>
            <div class="panel-container show"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Task</th><th>Client</th><th>Owner</th><th>Due</th><th>Priority</th></tr></thead><tbody>
            <?php foreach($data['tasks'] as $task): ?><tr><td><strong><?= e($task['title']) ?></strong></td><td><?= e($task['business_name']) ?></td><td><?= e($task['assignee'] ?: 'Unassigned') ?></td><td class="<?= $task['due_date'] < date('Y-m-d')?'text-danger font-weight-bold':'' ?>"><?= e($task['due_date']?date('M j',strtotime($task['due_date'])):'—') ?></td><td><span class="badge badge-soft-<?= $task['priority']==='urgent'?'danger':($task['priority']==='high'?'warning':'secondary') ?>"><?= e($task['priority']) ?></span></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
        </section>
    </div>
    <div class="col-xl-5">
        <section class="panel">
            <div class="panel-hdr"><h2>Upcoming production</h2><div class="panel-toolbar"><a href="<?= e(url('visits')) ?>" class="btn btn-panel">Calendar</a></div></div>
            <div class="panel-container show"><div class="panel-content p-0"><div class="list-group list-group-flush">
            <?php if(!$data['visits']): ?><div class="p-4 text-muted">No production visits scheduled this week.</div><?php endif; ?>
            <?php foreach($data['visits'] as $visit): ?><div class="list-group-item d-flex align-items-center"><div class="mr-3 text-center"><strong class="d-block fs-xl text-primary"><?= e(date('d',strtotime($visit['visit_date']))) ?></strong><small class="text-uppercase"><?= e(date('M',strtotime($visit['visit_date']))) ?></small></div><div class="flex-1"><strong class="d-block"><?= e($visit['business_name']) ?></strong><small class="text-muted"><?= e($visit['purpose']) ?> · <?= e($visit['start_time'] ?: 'TBD') ?></small></div><span class="badge badge-soft-primary"><?= e($visit['status']) ?></span></div><?php endforeach; ?>
            </div></div></div>
        </section>
    </div>
</div>

<div class="row mt-3">
    <div class="col-lg-7">
        <section class="panel"><div class="panel-hdr"><h2>Renewal watch</h2></div><div class="panel-container show"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Client</th><th>Package</th><th>Renewal</th><th>Window</th><th>MRR</th></tr></thead><tbody>
        <?php if(!$data['renewals']): ?><tr><td colspan="5" class="text-center text-muted py-4">No renewals in the next 45 days.</td></tr><?php endif; ?>
        <?php foreach($data['renewals'] as $renewal): ?><tr><td><strong><?= e($renewal['business_name']) ?></strong></td><td><?= e($renewal['package_name']) ?></td><td><?= e(date('M j, Y',strtotime($renewal['renewal_date']))) ?></td><td><span class="badge badge-soft-<?= $renewal['days_left']<=7?'danger':($renewal['days_left']<=14?'warning':'primary') ?>"><?= (int)$renewal['days_left'] ?> days</span></td><td><?= e(money($renewal['monthly_price'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div></section>
    </div>
    <div class="col-lg-5">
        <section class="panel"><div class="panel-hdr"><h2>Notifications</h2></div><div class="panel-container show"><div class="panel-content">
        <?php foreach($data['notifications'] as $notice): ?><div class="d-flex mb-3"><div class="rounded-circle bg-warning-50 text-warning p-2 mr-3 align-self-start"><i class="fal fa-bell"></i></div><div><strong class="d-block fs-sm"><?= e($notice['title']) ?></strong><small class="text-muted"><?= e($notice['body']) ?></small></div></div><?php endforeach; ?>
        </div></div></section>
    </div>
</div>

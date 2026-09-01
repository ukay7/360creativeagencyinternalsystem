<?php
$firstName = explode(' ', trim((string) $auth->user()['name']))[0];
$journey = [
    ['Quotes', $data['kpis']['saved_quotes'], $data['kpis']['quotes_in_progress'].' in progress', 'fa-file-signature', 'saved_quotes'],
    ['Clients', $data['kpis']['active_clients'], money($data['kpis']['monthly_recurring']).' recurring', 'fa-building', 'clients'],
    ['Projects', $data['kpis']['active_projects'], 'active delivery workspaces', 'fa-briefcase', 'projects'],
    ['Tasks', $data['kpis']['open_tasks'], $data['kpis']['overdue_tasks'].' overdue', 'fa-check-circle', 'tasks'],
];
?>
<div class="page-title-wrap command-title">
    <div><span class="eyebrow">QUOTE TO DELIVERY · <?= e(strtoupper(date('F j, Y'))) ?></span><h1>Good morning, <?= e($firstName) ?>.</h1><p>Your complete operating picture—from proposal to finished client work.</p></div>
    <div class="d-flex flex-wrap"><a href="<?= e(url('reports')) ?>" class="btn btn-outline-secondary mr-2"><i class="fal fa-chart-line mr-1"></i> Reports</a><a href="<?= e(url('quote_studio')) ?>" class="btn btn-primary"><i class="fal fa-plus mr-1"></i> New quote</a></div>
</div>

<section class="workflow-hero">
    <div><span class="workflow-kicker"><i class="fal fa-bolt mr-2"></i>360 WORKFLOW</span><h2>One clear path from accepted scope to delivered work.</h2><p>Quotes become clients, accepted services become projects, and every promised item becomes a trackable task.</p></div>
    <div class="workflow-hero-value"><small>Accepted quote value</small><strong><?= e(money($data['kpis']['accepted_quote_value'])) ?></strong><span><?= e(money($data['kpis']['accepted_this_month'])) ?> accepted this month</span></div>
</section>

<section class="workflow-step-grid" aria-label="Quote to delivery workflow">
    <?php foreach($journey as $index=>$step): ?>
        <a class="workflow-step" href="<?= e(url($step[4])) ?>">
            <span class="workflow-step-number">0<?= $index+1 ?></span><i class="fal <?= e($step[3]) ?>"></i>
            <div><small><?= e($step[0]) ?></small><strong><?= e(number_format((int)$step[1])) ?></strong><span><?= e($step[2]) ?></span></div>
            <i class="fal fa-arrow-right workflow-step-arrow"></i>
        </a>
    <?php endforeach; ?>
</section>

<section class="command-kpi-grid">
    <article><span>Recurring monthly</span><strong><?= e(money($data['kpis']['monthly_recurring'])) ?></strong><small>Membership value</small></article>
    <article><span>Active clients</span><strong><?= (int)$data['kpis']['active_clients'] ?></strong><small>Accepted and onboarded</small></article>
    <article><span>Active projects</span><strong><?= (int)$data['kpis']['active_projects'] ?></strong><small>Currently in delivery</small></article>
    <article class="<?= $data['kpis']['overdue_tasks'] ? 'needs-attention' : '' ?>"><span>Overdue tasks</span><strong><?= (int)$data['kpis']['overdue_tasks'] ?></strong><small>Requires attention</small></article>
    <article><span>Completed this month</span><strong><?= (int)$data['kpis']['completed_tasks_month'] ?></strong><small>Delivery tasks finished</small></article>
</section>

<div class="row mt-4">
    <div class="col-xl-7">
        <section class="panel command-panel h-100">
            <div class="panel-hdr"><div><h2>Recent quotes</h2><small>Latest commercial activity</small></div><div class="panel-toolbar"><a href="<?= e(url('saved_quotes')) ?>" class="btn btn-panel">View all <i class="fal fa-arrow-right ml-1"></i></a></div></div>
            <div class="panel-container show"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Quote</th><th>Client</th><th>Package</th><th>Total</th><th>Status</th></tr></thead><tbody>
            <?php if(!$data['recent_quotes']): ?><tr><td colspan="5" class="text-center text-muted py-5">No quotes yet. Create the first proposal to begin the workflow.</td></tr><?php endif; ?>
            <?php foreach($data['recent_quotes'] as $quote): ?><tr><td><a class="font-weight-bold text-primary" href="<?= e(url('quote_view',['id'=>$quote['id']])) ?>"><?= e($quote['proposal_number']) ?></a><small class="d-block text-muted"><?= e(date('M j, Y',strtotime($quote['updated_at'] ?: $quote['created_at']))) ?></small></td><td><?= e($quote['business_name']) ?></td><td><?= e(ucwords(str_replace('_',' ',$quote['package_name']))) ?></td><td><strong><?= e(money($quote['total'])) ?></strong></td><td><span class="badge badge-soft-<?= $quote['status']==='accepted'?'success':($quote['status']==='sent'?'primary':'secondary') ?>"><?= e(ucfirst($quote['status'])) ?></span></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
        </section>
    </div>
    <div class="col-xl-5 mt-3 mt-xl-0">
        <section class="panel command-panel h-100">
            <div class="panel-hdr"><div><h2>Action queue</h2><small>Items that need a decision</small></div></div>
            <div class="panel-container show"><div class="command-action-list">
                <?php if(!$data['overdue_tasks'] && !$data['awaiting_quotes']): ?><div class="command-empty"><i class="fal fa-check-circle"></i><strong>Everything is moving</strong><span>No overdue tasks or open quote follow-ups.</span></div><?php endif; ?>
                <?php foreach($data['overdue_tasks'] as $task): ?><a href="<?= e(url('tasks')) ?>" class="command-action-item danger"><i class="fal fa-exclamation-circle"></i><div><strong><?= e($task['title']) ?></strong><span><?= e($task['business_name']) ?> · overdue <?= e(date('M j',strtotime($task['due_date']))) ?></span></div><i class="fal fa-arrow-right"></i></a><?php endforeach; ?>
                <?php foreach($data['awaiting_quotes'] as $quote): ?><a href="<?= e(url('quote_view',['id'=>$quote['id']])) ?>" class="command-action-item"><i class="fal fa-file-signature"></i><div><strong><?= e($quote['proposal_number']) ?> · <?= e($quote['business_name']) ?></strong><span><?= e(ucfirst($quote['status'])) ?> · <?= e(money($quote['total'])) ?></span></div><i class="fal fa-arrow-right"></i></a><?php endforeach; ?>
            </div></div>
        </section>
    </div>
</div>

<div class="row mt-3">
    <div class="col-xl-8">
        <section class="panel command-panel">
            <div class="panel-hdr"><div><h2>Project delivery</h2><small>Progress against promised client work</small></div><div class="panel-toolbar"><a href="<?= e(url('projects')) ?>" class="btn btn-panel">Open projects</a></div></div>
            <div class="panel-container show"><div class="panel-content">
                <?php if(!$data['project_delivery']): ?><div class="command-empty compact"><i class="fal fa-briefcase"></i><strong>No projects yet</strong><span>Projects appear automatically when a quote is accepted and onboarded.</span></div><?php endif; ?>
                <?php foreach($data['project_delivery'] as $project): $taskCount=(int)$project['task_count'];$completed=(int)$project['completed_tasks'];$progress=$taskCount?round($completed/$taskCount*100):0; ?>
                    <div class="delivery-row"><div class="delivery-copy"><a href="<?= e(url('tasks',['project_id'=>$project['id']])) ?>"><strong><?= e($project['name']) ?></strong></a><span><?= e($project['business_name']) ?> · <?= $completed ?>/<?= $taskCount ?> tasks complete</span></div><div class="delivery-progress"><div class="progress-thin"><span style="width:<?= $progress ?>%"></span></div><strong><?= $progress ?>%</strong></div><span class="badge badge-soft-<?= $project['status']==='completed'?'success':'primary' ?>"><?= e(ucwords(str_replace('_',' ',$project['status']))) ?></span></div>
                <?php endforeach; ?>
            </div></div>
        </section>
    </div>
    <div class="col-xl-4 mt-3 mt-xl-0">
        <section class="panel command-panel h-100"><div class="panel-hdr"><div><h2>Task flow</h2><small>Current work by status</small></div></div><div class="panel-container show"><div class="panel-content task-flow-list">
            <?php $taskTotal=max(1,array_sum(array_map('intval',array_column($data['task_statuses'],'task_count')))); ?>
            <?php if(!$data['task_statuses']): ?><div class="command-empty compact"><i class="fal fa-check-circle"></i><strong>No tasks yet</strong></div><?php endif; ?>
            <?php foreach($data['task_statuses'] as $status): $percent=round((int)$status['task_count']/$taskTotal*100); ?><div><div class="d-flex justify-content-between"><span><?= e(ucwords(str_replace('_',' ',$status['status']))) ?></span><strong><?= (int)$status['task_count'] ?></strong></div><div class="progress-thin"><span style="width:<?= $percent ?>%"></span></div></div><?php endforeach; ?>
        </div></div></section>
    </div>
</div>

<section class="panel command-panel mt-3">
    <div class="panel-hdr"><div><h2>Recent workflow activity</h2><small>An auditable view of important changes</small></div><div class="panel-toolbar"><a href="<?= e(url('audit')) ?>" class="btn btn-panel">Full audit log</a></div></div>
    <div class="panel-container show"><div class="workflow-activity">
        <?php if(!$data['recent_activity']): ?><div class="p-4 text-muted">No workflow activity recorded yet.</div><?php endif; ?>
        <?php foreach($data['recent_activity'] as $activity): ?><div><span class="activity-dot"></span><strong><?= e(ucwords(str_replace(['.','_'],' ',$activity['action']))) ?></strong><span><?= e(ucfirst($activity['entity_type'])) ?> #<?= (int)$activity['entity_id'] ?> · <?= e($activity['user_name'] ?: 'System') ?></span><time><?= e(date('M j, g:i A',strtotime($activity['created_at']))) ?></time></div><?php endforeach; ?>
    </div></div>
</section>

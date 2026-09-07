<?php $summary=$reports['summary'];$isAdminView=(bool)($reports['is_admin_view']??false); ?>
<div class="page-title-wrap command-title"><div><span class="eyebrow"><?= $isAdminView?'QUOTE & DELIVERY INTELLIGENCE':'MY DELIVERY INTELLIGENCE' ?></span><h1>Workflow Reports</h1><p><?= $isAdminView?'Commercial performance, client value, project progress, and task delivery in one live report.':'Project context and delivery performance calculated only from tasks assigned to you.' ?></p></div><button class="btn btn-outline-secondary" data-print><i class="fal fa-print mr-1"></i> Export report</button></div>

<section class="report-hero">
    <?php if($isAdminView): ?>
    <div><span>Accepted quote value</span><strong><?= e(money($summary['accepted_quote_value'])) ?></strong><small><?= (int)$summary['accepted_quotes'] ?> accepted from <?= (int)$summary['total_quotes'] ?> saved quotes</small></div>
    <div><span>Quote acceptance</span><strong><?= e(number_format($summary['acceptance_rate'],1)) ?>%</strong><small>Accepted proposals / all saved proposals</small></div>
    <div><span>Monthly recurring</span><strong><?= e(money($summary['monthly_recurring'])) ?></strong><small>Active client membership value</small></div>
    <?php else: ?>
    <div><span>Active projects</span><strong><?= (int)$summary['active_projects'] ?></strong><small>Visible delivery workspaces</small></div>
    <div><span>My completed tasks</span><strong><?= (int)$summary['completed_tasks'] ?></strong><small>Assigned work completed</small></div>
    <div><span>My overdue tasks</span><strong><?= (int)$summary['overdue_tasks'] ?></strong><small>Assigned work requiring attention</small></div>
    <?php endif; ?>
    <div><span>Delivery health</span><strong><?= (int)$summary['open_tasks'] ?> open</strong><small class="<?= $summary['overdue_tasks']?'text-warning':'' ?>"><?= (int)$summary['overdue_tasks'] ?> overdue tasks</small></div>
</section>

<?php if($isAdminView): ?>
<div class="row mt-4">
    <div class="col-xl-5">
        <section class="panel report-workflow-panel h-100"><div class="panel-hdr"><div><h2>Quote performance</h2><small>Volume and value by status</small></div></div><div class="panel-container show"><div class="panel-content">
            <?php $maxQuoteValue=max(array_merge([1.0],array_map(static fn($row)=>(float)$row['quote_value'],$reports['quote_statuses']))); ?>
            <?php if(!$reports['quote_statuses']): ?><div class="command-empty compact"><i class="fal fa-file-signature"></i><strong>No quotes yet</strong></div><?php endif; ?>
            <?php foreach($reports['quote_statuses'] as $row): ?><div class="report-bar-row"><div><strong><?= e(ucfirst($row['status'])) ?></strong><span><?= (int)$row['quote_count'] ?> quotes</span></div><div><div class="progress-thin"><span style="width:<?= e((string)round((float)$row['quote_value']/$maxQuoteValue*100)) ?>%"></span></div><strong><?= e(money($row['quote_value'])) ?></strong></div></div><?php endforeach; ?>
        </div></div></section>
    </div>
    <div class="col-xl-7 mt-3 mt-xl-0">
        <section class="panel report-workflow-panel h-100"><div class="panel-hdr"><div><h2>Package performance</h2><small>What clients are choosing</small></div></div><div class="panel-container show"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Package</th><th>Quotes</th><th>Accepted</th><th>Accepted value</th><th>Monthly recurring</th></tr></thead><tbody>
            <?php if(!$reports['packages']): ?><tr><td colspan="5" class="text-center text-muted py-5">No package data yet.</td></tr><?php endif; ?>
            <?php foreach($reports['packages'] as $row): ?><tr><td><strong><?= e(ucwords(str_replace('_',' ',$row['package_name']))) ?></strong></td><td><?= (int)$row['quote_count'] ?></td><td><?= (int)$row['accepted_count'] ?></td><td><?= e(money($row['accepted_value'])) ?></td><td><?= e(money($row['monthly_recurring'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div></section>
    </div>
</div>

<section class="panel report-workflow-panel mt-3"><div class="panel-hdr"><div><h2>Client value</h2><small>The accepted quote and recurring amounts are intentionally separated</small></div><div class="panel-toolbar"><span class="badge badge-soft-primary"><?= (int)$summary['active_clients'] ?> active clients</span></div></div><div class="panel-container show"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Client</th><th>Package</th><th>Accepted quote</th><th>Monthly recurring</th><th>Projects</th><th>Open tasks</th><th>Completed tasks</th></tr></thead><tbody>
    <?php if(!$reports['clients']): ?><tr><td colspan="7" class="text-center text-muted py-5">Clients will appear after a quote is accepted and onboarded.</td></tr><?php endif; ?>
    <?php foreach($reports['clients'] as $row): ?><tr><td><a class="font-weight-bold text-primary" href="<?= e(url('client',['id'=>$row['id']])) ?>"><?= e($row['business_name']) ?></a></td><td><?= e($row['package_name'] ?: '—') ?></td><td><strong><?= e(money($row['accepted_quote_value'])) ?></strong></td><td><?= e(money($row['monthly_price'])) ?></td><td><?= (int)$row['project_count'] ?></td><td><?= (int)$row['open_tasks'] ?></td><td><?= (int)$row['completed_tasks'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div></section>

<?php $finance=$reports['invoice_summary']; ?><section class="panel report-workflow-panel mt-3"><div class="panel-hdr"><div><h2>Invoice reversals & net billing</h2><small>All-time issued invoices, cancellation certificates, and partial-refund credit notes</small></div><div class="panel-toolbar"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('invoices')) ?>">Open invoice report</a></div></div><div class="panel-container show"><div class="report-finance-grid"><div><span>Gross issued</span><strong><?= e(money($finance['gross_issued'])) ?></strong><small><?= (int)$finance['issued_count'] ?> invoices</small></div><div class="negative"><span>Cancelled</span><strong>−<?= e(money($finance['cancelled_total'])) ?></strong><small><?= (int)$finance['cancellation_count'] ?> certificates</small></div><div class="negative"><span>Partial refunds</span><strong>−<?= e(money($finance['refunded_total'])) ?></strong><small><?= (int)$finance['refund_count'] ?> credit notes</small></div><div class="net"><span>Net invoiced</span><strong><?= e(money($finance['net_invoiced'])) ?></strong><small><?= e(money($finance['outstanding'])) ?> outstanding</small></div></div></div></section>
<?php endif; ?>

<div class="row <?= $isAdminView?'mt-3':'mt-4' ?>">
    <div class="col-xl-8">
        <section class="panel report-workflow-panel"><div class="panel-hdr"><div><h2>Project delivery</h2><small><?= $isAdminView?(int)$summary['active_projects'].' active project workspaces':'Only your assigned tasks contribute to project progress below' ?></small></div></div><div class="panel-container show"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Project</th><th>Client</th><th>Status</th><th><?= $isAdminView?'Tasks':'My tasks' ?></th><th>Progress</th></tr></thead><tbody>
        <?php if(!$reports['projects']): ?><tr><td colspan="5" class="text-center text-muted py-5">No projects have been created yet.</td></tr><?php endif; ?>
        <?php foreach($reports['projects'] as $row): $count=(int)$row['task_count'];$done=(int)$row['completed_tasks'];$progress=$count?round($done/$count*100):0; ?><tr><td><a class="font-weight-bold" href="<?= e(url('tasks',['project_id'=>$row['id']])) ?>"><?= e($row['project_name']) ?></a></td><td><?= e($row['business_name']) ?></td><td><span class="badge badge-soft-<?= $row['status']==='completed'?'success':'primary' ?>"><?= e(ucwords(str_replace('_',' ',$row['status']))) ?></span></td><td><?= $done ?>/<?= $count ?></td><td><div class="report-progress-cell"><div class="progress-thin"><span style="width:<?= $progress ?>%"></span></div><strong><?= $progress ?>%</strong></div></td></tr><?php endforeach; ?>
        </tbody></table></div></div></section>
    </div>
    <div class="col-xl-4 mt-3 mt-xl-0">
        <section class="panel report-workflow-panel h-100"><div class="panel-hdr"><div><h2><?= $isAdminView?'Task workload':'My task workload' ?></h2><small><?= $isAdminView?'Assignments and delivery pressure':'Your assignments and delivery pressure' ?></small></div></div><div class="panel-container show"><div class="panel-content p-0"><div class="workload-summary">
            <?php foreach($reports['task_statuses'] as $row): ?><div><span><?= e(ucwords(str_replace('_',' ',$row['status']))) ?></span><strong><?= (int)$row['task_count'] ?></strong></div><?php endforeach; ?>
        </div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Team member</th><th>Open</th><th>Overdue</th><th>Done</th></tr></thead><tbody>
            <?php if(!$reports['assignees']): ?><tr><td colspan="4" class="text-center text-muted py-4">No assigned tasks yet.</td></tr><?php endif; ?>
            <?php foreach($reports['assignees'] as $row): ?><tr><td><strong><?= e($row['name']) ?></strong><small class="d-block text-muted"><?= e($row['department']) ?></small></td><td><?= (int)$row['open_tasks'] ?></td><td class="<?= $row['overdue_tasks']?'text-danger font-weight-bold':'' ?>"><?= (int)$row['overdue_tasks'] ?></td><td><?= (int)$row['completed_tasks'] ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div></div></section>
    </div>
</div>

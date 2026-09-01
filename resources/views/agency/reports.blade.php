<?php $summary=$reports['summary']; ?>
<div class="page-title-wrap command-title"><div><span class="eyebrow">QUOTE & DELIVERY INTELLIGENCE</span><h1>Workflow Reports</h1><p>Commercial performance, client value, project progress, and task delivery in one live report.</p></div><button class="btn btn-outline-secondary" data-print><i class="fal fa-print mr-1"></i> Export report</button></div>

<section class="report-hero">
    <div><span>Accepted quote value</span><strong><?= e(money($summary['accepted_quote_value'])) ?></strong><small><?= (int)$summary['accepted_quotes'] ?> accepted from <?= (int)$summary['total_quotes'] ?> saved quotes</small></div>
    <div><span>Quote acceptance</span><strong><?= e(number_format($summary['acceptance_rate'],1)) ?>%</strong><small>Accepted proposals / all saved proposals</small></div>
    <div><span>Monthly recurring</span><strong><?= e(money($summary['monthly_recurring'])) ?></strong><small>Active client membership value</small></div>
    <div><span>Delivery health</span><strong><?= (int)$summary['open_tasks'] ?> open</strong><small class="<?= $summary['overdue_tasks']?'text-warning':'' ?>"><?= (int)$summary['overdue_tasks'] ?> overdue tasks</small></div>
</section>

<div class="row mt-4">
    <div class="col-xl-5">
        <section class="panel report-workflow-panel h-100"><div class="panel-hdr"><div><h2>Quote performance</h2><small>Volume and value by status</small></div></div><div class="panel-container show"><div class="panel-content">
            <?php $maxQuoteValue=max(1,...array_map(static fn($row)=>(float)$row['quote_value'],$reports['quote_statuses'])); ?>
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

<div class="row mt-3">
    <div class="col-xl-8">
        <section class="panel report-workflow-panel"><div class="panel-hdr"><div><h2>Project delivery</h2><small><?= (int)$summary['active_projects'] ?> active project workspaces</small></div></div><div class="panel-container show"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Project</th><th>Client</th><th>Status</th><th>Tasks</th><th>Progress</th></tr></thead><tbody>
        <?php if(!$reports['projects']): ?><tr><td colspan="5" class="text-center text-muted py-5">No projects have been created yet.</td></tr><?php endif; ?>
        <?php foreach($reports['projects'] as $row): $count=(int)$row['task_count'];$done=(int)$row['completed_tasks'];$progress=$count?round($done/$count*100):0; ?><tr><td><a class="font-weight-bold" href="<?= e(url('tasks',['project_id'=>$row['id']])) ?>"><?= e($row['project_name']) ?></a></td><td><?= e($row['business_name']) ?></td><td><span class="badge badge-soft-<?= $row['status']==='completed'?'success':'primary' ?>"><?= e(ucwords(str_replace('_',' ',$row['status']))) ?></span></td><td><?= $done ?>/<?= $count ?></td><td><div class="report-progress-cell"><div class="progress-thin"><span style="width:<?= $progress ?>%"></span></div><strong><?= $progress ?>%</strong></div></td></tr><?php endforeach; ?>
        </tbody></table></div></div></section>
    </div>
    <div class="col-xl-4 mt-3 mt-xl-0">
        <section class="panel report-workflow-panel h-100"><div class="panel-hdr"><div><h2>Task workload</h2><small>Assignments and delivery pressure</small></div></div><div class="panel-container show"><div class="panel-content p-0"><div class="workload-summary">
            <?php foreach($reports['task_statuses'] as $row): ?><div><span><?= e(ucwords(str_replace('_',' ',$row['status']))) ?></span><strong><?= (int)$row['task_count'] ?></strong></div><?php endforeach; ?>
        </div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Team member</th><th>Open</th><th>Overdue</th><th>Done</th></tr></thead><tbody>
            <?php if(!$reports['assignees']): ?><tr><td colspan="4" class="text-center text-muted py-4">No assigned tasks yet.</td></tr><?php endif; ?>
            <?php foreach($reports['assignees'] as $row): ?><tr><td><strong><?= e($row['name']) ?></strong><small class="d-block text-muted"><?= e($row['department']) ?></small></td><td><?= (int)$row['open_tasks'] ?></td><td class="<?= $row['overdue_tasks']?'text-danger font-weight-bold':'' ?>"><?= (int)$row['overdue_tasks'] ?></td><td><?= (int)$row['completed_tasks'] ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div></div></section>
    </div>
</div>

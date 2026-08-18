<?php
$start = new DateTimeImmutable($month . '-01');
$days = (int) $start->format('t');
$offset = (int) $start->format('N') - 1;
$eventsByDay = [];
foreach ($events as $event) {
    $eventsByDay[(int) date('j', strtotime($event['event_date']))][] = $event;
}
$previous = $start->modify('-1 month')->format('Y-m');
$next = $start->modify('+1 month')->format('Y-m');
$eventColors = ['task'=>'primary','visit'=>'success','content'=>'purple','deadline'=>'danger','renewal'=>'warning'];
?>
<div class="page-title-wrap">
    <div><span class="eyebrow">SHARED SCHEDULE</span><h1>Agency Calendar</h1><p>Tasks, shoots, content, deadlines, and renewals in one operational view.</p></div>
    <div><a class="btn btn-outline-secondary" href="<?= e(agency_url('calendar',['month'=>$previous])) ?>"><i class="fal fa-chevron-left"></i></a><a class="btn btn-outline-secondary mx-1" href="<?= e(agency_url('calendar',['month'=>date('Y-m')])) ?>">Today</a><a class="btn btn-outline-secondary" href="<?= e(agency_url('calendar',['month'=>$next])) ?>"><i class="fal fa-chevron-right"></i></a></div>
</div>
<section class="panel"><div class="panel-hdr"><h2><?= e($start->format('F Y')) ?></h2><div class="panel-toolbar"><div class="d-flex">
    <select class="custom-select custom-select-sm mr-2" data-calendar-filter="event"><option value="">All event types</option><option value="task">Tasks</option><option value="visit">Visits</option><option value="content">Content</option><option value="deadline">Deadlines</option><option value="renewal">Renewals</option></select>
    <select class="custom-select custom-select-sm mr-2" data-calendar-filter="employee"><option value="">All employees</option><?php foreach($options['employees'] as $employee): ?><option value="<?= (int)$employee['id'] ?>"><?= e($employee['name']) ?></option><?php endforeach; ?></select>
    <select class="custom-select custom-select-sm" data-calendar-filter="client"><option value="">All clients</option><?php foreach($options['clients'] as $client): ?><option value="<?= (int)$client['id'] ?>"><?= e($client['name']) ?></option><?php endforeach; ?></select>
</div></div></div><div class="panel-container show"><div class="panel-content p-0"><div class="agency-calendar"><div class="calendar-weekdays"><?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday): ?><div><?= e($weekday) ?></div><?php endforeach; ?></div><div class="calendar-grid">
<?php for($i=0;$i<$offset;$i++): ?><div class="calendar-day muted"></div><?php endfor; ?>
<?php for($day=1;$day<=$days;$day++): $isToday=$month.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT)===date('Y-m-d'); ?>
    <div class="calendar-day <?= $isToday?'today':'' ?>"><div class="calendar-date"><?= $day ?></div><div class="calendar-events">
    <?php foreach($eventsByDay[$day]??[] as $event): ?><article class="calendar-event event-<?= e($eventColors[$event['event_type']]??'primary') ?>" data-calendar-event data-type="<?= e($event['event_type']) ?>" data-employee="<?= e($event['employee_id']??'') ?>" data-client="<?= e($event['client_id']??'') ?>" data-project="<?= e($event['project_id']??'') ?>" title="<?= e($event['client_name'].' · '.$event['status']) ?>"><i class="fal fa-<?= $event['event_type']==='visit'?'camera':($event['event_type']==='content'?'photo-video':($event['event_type']==='deadline'?'flag':($event['event_type']==='renewal'?'sync':'check-square'))) ?> mr-1"></i><?= e($event['title']) ?></article><?php endforeach; ?>
    </div></div>
<?php endfor; ?>
</div></div></div></div></section>
<div class="d-flex flex-wrap mt-3"><?php foreach($eventColors as $type=>$color): ?><span class="mr-3 fs-sm"><i class="fal fa-circle text-<?= e($color==='purple'?'primary':$color) ?> mr-1"></i><?= e(ucfirst($type)) ?></span><?php endforeach; ?></div>

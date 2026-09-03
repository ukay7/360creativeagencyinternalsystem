<?php
$start = new DateTimeImmutable($month . '-01');
$days = (int) $start->format('t');
$offset = (int) $start->format('N') - 1;
$eventsByDay = [];
$previous = $start->modify('-1 month')->format('Y-m');
$next = $start->modify('+1 month')->format('Y-m');
$eventColors = ['task'=>'primary','visit'=>'success','content'=>'purple','deadline'=>'danger','renewal'=>'warning'];
$eventLabels = ['task'=>'Task','visit'=>'Content visit','content'=>'Content item','deadline'=>'Project deadline','renewal'=>'Subscription renewal'];
$eventIcons = ['task'=>'check-square','visit'=>'camera','content'=>'photo-video','deadline'=>'flag','renewal'=>'sync'];
$isAdminView=in_array(($auth->user()['role_slug']??''),['super_admin','admin'],true);
$isClientView=($auth->user()['role_slug']??'')==='client';
$statusClass = static function(string $status): string {
    if(in_array($status,['completed','approved','published','active','confirmed'],true)) return 'success';
    if(in_array($status,['cancelled','rejected','overdue'],true)) return 'danger';
    if(in_array($status,['waiting','review','client_review','internal_review','changes_requested'],true)) return 'warning';
    return 'primary';
};
$humanize = static fn(?string $value): string => ucwords(str_replace('_',' ',(string)$value));
$formatDate = static fn(?string $value): string => $value ? date('F j, Y',strtotime($value)) : 'Not set';
$formatTime = static fn(?string $value): string => $value ? date('g:i A',strtotime($value)) : '';
foreach ($events as &$event) {
    $type=(string)$event['event_type'];
    $event['_modal_id']='calendar-event-'.$type.'-'.(int)$event['id'];
    $event['_type_label']=$eventLabels[$type]??$humanize($type);
    $event['_icon']=$eventIcons[$type]??'calendar-alt';
    $event['_status_label']=$humanize((string)($event['status']??''));
    $event['_status_class']=$statusClass((string)($event['status']??''));
    $event['_date_label']=$formatDate((string)$event['event_date']);
    $event['_time_label']='';
    if($type==='visit'){
        $startTime=$formatTime($event['start_time']??null);$endTime=$formatTime($event['end_time']??null);
        $event['_time_label']=trim($startTime.($endTime?' – '.$endTime:''));
    }elseif($type==='content'&&!empty($event['event_datetime'])){$event['_time_label']=$formatTime((string)$event['event_datetime']);}
    $details=[['Date',$event['_date_label']]];
    if($event['_time_label']!==''){$details[]=['Time',$event['_time_label']];}
    $details[]=['Client',(string)($event['client_name']??'Not linked')];
    if(!empty($event['project_name'])){$details[]=['Project',(string)$event['project_name']];}
    if(!$isClientView&&in_array($type,['task','visit','content','deadline'],true)){$details[]=['Assigned to',(string)($event['employee_name']??'Unassigned')];}
    $details[]=['Status',$event['_status_label']];
    if($type==='task'){
        $details[]=['Priority',$humanize((string)($event['priority']??'medium'))];
        if(!empty($event['occurrence_date'])){$details[]=['Occurrence',$formatDate((string)$event['occurrence_date'])];}
    }elseif($type==='visit'){
        $details[]=['Visit type',(string)($event['visit_type']??'')];
        if(!empty($event['package_name'])){$details[]=['Package',(string)$event['package_name']];}
        $details[]=['Additional visit',!empty($event['is_additional'])?'Yes':'No'];
        if((float)($event['additional_charge']??0)>0){$details[]=['Additional charge',money($event['additional_charge'])];}
    }elseif($type==='content'){
        $details[]=['Platform',(string)($event['platform']??'')];
        $details[]=['Format',(string)($event['content_type']??'')];
        $details[]=['Approval',$humanize((string)($event['approval_status']??'pending'))];
    }elseif($type==='deadline'){
        $details[]=['Project type',(string)($event['project_type']??'')];
        $details[]=['Priority',$humanize((string)($event['priority']??'medium'))];
        $details[]=['Started',$formatDate($event['start_date']??null)];
    }elseif($type==='renewal'){
        $details[]=['Package',(string)($event['package_name']??'')];
        $details[]=['Monthly price',money($event['monthly_price']??0)];
        $details[]=['Billing',$humanize((string)($event['billing_frequency']??'monthly'))];
        $details[]=['Contract started',$formatDate($event['start_date']??null)];
        $details[]=['Contract ends',$formatDate($event['contract_end_date']??null)];
        if((float)($event['deposit']??0)>0){$details[]=['Deposit',money($event['deposit'])];}
        if((float)($event['discount_percent']??0)>0){$details[]=['Discount',number_format((float)$event['discount_percent'],2).'%'];}
        if((float)($event['tax_percent']??0)>0){$details[]=['Tax',number_format((float)$event['tax_percent'],2).'%'];}
    }
    $event['_details']=array_values(array_filter($details,static fn(array $detail): bool => trim((string)$detail[1])!==''));
    $sections=[];
    if($type==='task'&&!empty($event['description'])){$sections[]=['Task description',(string)$event['description']];}
    if($type==='visit'){
        if(!empty($event['purpose'])){$sections[]=['Purpose',(string)$event['purpose']];}
        if(!empty($event['equipment'])){$sections[]=['Equipment',(string)$event['equipment']];}
        if(!empty($event['content_captured'])){$sections[]=['Content captured',(string)$event['content_captured']];}
        if(!empty($event['notes'])){$sections[]=['Notes',(string)$event['notes']];}
    }
    if($type==='content'){
        if(!empty($event['caption'])){$sections[]=['Caption',(string)$event['caption']];}
        if(!empty($event['hashtags'])){$sections[]=['Hashtags',(string)$event['hashtags']];}
        if(!empty($event['approval_comments'])){$sections[]=['Approval comments',(string)$event['approval_comments']];}
    }
    if(!$isClientView&&$type==='deadline'&&!empty($event['description'])){$sections[]=['Project notes',(string)$event['description']];}
    $event['_sections']=$sections;
    $eventsByDay[(int)date('j',strtotime((string)$event['event_date']))][]=$event;
}
unset($event);
?>
<div class="page-title-wrap">
    <div><span class="eyebrow"><?= $isClientView?'CLIENT DELIVERY SCHEDULE':($isAdminView?'SHARED SCHEDULE':'MY DELIVERY SCHEDULE') ?></span><h1><?= $isClientView?'My Project Calendar':'Agency Calendar' ?></h1><p><?= $isClientView?'Task due dates and project milestones for your account, without internal team information.':($isAdminView?'Tasks, shoots, content, deadlines, and renewals in one operational view.':'Your assigned tasks and delivery work, alongside the projects your team is delivering.') ?></p></div>
    <div><a class="btn btn-outline-secondary" href="<?= e(agency_url('calendar',['month'=>$previous])) ?>"><i class="fal fa-chevron-left"></i></a><a class="btn btn-outline-secondary mx-1" href="<?= e(agency_url('calendar',['month'=>date('Y-m')])) ?>">Today</a><a class="btn btn-outline-secondary" href="<?= e(agency_url('calendar',['month'=>$next])) ?>"><i class="fal fa-chevron-right"></i></a></div>
</div>
<section class="panel calendar-workspace"><div class="panel-hdr calendar-toolbar"><div><h2><?= e($start->format('F Y')) ?></h2><small class="text-muted">Filter the schedule without leaving the month.</small></div><div class="panel-toolbar"><div class="d-flex flex-wrap justify-content-end">
    <select class="custom-select custom-select-sm mr-2" data-calendar-filter="event"><option value="">All event types</option><option value="task">Tasks</option><?php if(!$isClientView): ?><option value="visit">Visits</option><option value="content">Content</option><?php endif; ?><option value="deadline">Deadlines</option><?php if(!$isClientView): ?><option value="renewal">Renewals</option><?php endif; ?></select>
    <?php if($isAdminView): ?><select class="custom-select custom-select-sm mr-2" data-calendar-filter="employee"><option value="">All employees</option><?php foreach($options['employees'] as $employee): ?><option value="<?= (int)$employee['id'] ?>"><?= e($employee['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <?php if(!$isClientView): ?><select class="custom-select custom-select-sm mr-2" data-calendar-filter="client"><option value="">All clients</option><?php foreach($options['clients'] as $client): ?><option value="<?= (int)$client['id'] ?>"><?= e($client['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <select class="custom-select custom-select-sm" data-calendar-filter="project"><option value="">All projects</option><?php foreach($options['projects'] as $project): ?><option value="<?= (int)$project['id'] ?>"><?= e($project['name'].(!$isClientView&&!empty($project['client_name'])?' · '.$project['client_name']:'')) ?></option><?php endforeach; ?></select>
</div></div></div><div class="panel-container show"><div class="panel-content p-0"><div class="agency-calendar"><div class="calendar-weekdays"><?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday): ?><div><?= e($weekday) ?></div><?php endforeach; ?></div><div class="calendar-grid">
<?php for($i=0;$i<$offset;$i++): ?><div class="calendar-day muted"></div><?php endfor; ?>
<?php for($day=1;$day<=$days;$day++): $isToday=$month.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT)===date('Y-m-d'); ?>
    <div class="calendar-day <?= $isToday?'today':'' ?>"><div class="calendar-date"><?= $day ?></div><div class="calendar-events">
    <?php foreach($eventsByDay[$day]??[] as $event): ?><button type="button" class="calendar-event event-<?= e($eventColors[$event['event_type']]??'primary') ?>" data-calendar-event data-type="<?= e($event['event_type']) ?>" data-employee="<?= e($event['employee_ids']??$event['employee_id']??'') ?>" data-client="<?= e($event['client_id']??'') ?>" data-project="<?= e($event['project_id']??'') ?>" data-toggle="modal" data-target="#<?= e($event['_modal_id']) ?>" aria-label="Open <?= e($event['title']) ?> details"><span class="calendar-event-line"><i class="fal fa-<?= e($event['_icon']) ?>"></i><strong><?= e($event['title']) ?></strong></span><span class="calendar-event-meta"><?= e($isClientView?($event['project_name']??$event['client_name']):$event['client_name']) ?><?= $event['_time_label']!==''?' · '.e($event['_time_label']):'' ?></span></button><?php endforeach; ?>
    </div></div>
<?php endfor; ?>
</div></div></div></div></section>
<div class="d-flex flex-wrap mt-3"><?php foreach($eventColors as $type=>$color): ?><span class="mr-3 fs-sm"><i class="fal fa-circle text-<?= e($color==='purple'?'primary':$color) ?> mr-1"></i><?= e(ucfirst($type)) ?></span><?php endforeach; ?></div>

<?php foreach($events as $event): ?>
<div class="modal fade calendar-detail-modal" id="<?= e($event['_modal_id']) ?>" tabindex="-1" role="dialog" aria-labelledby="<?= e($event['_modal_id']) ?>-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content">
    <div class="modal-header calendar-detail-header"><div class="calendar-detail-heading"><span class="calendar-detail-icon event-<?= e($eventColors[$event['event_type']]??'primary') ?>"><i class="fal fa-<?= e($event['_icon']) ?>"></i></span><div><span class="eyebrow mb-1"><?= e(strtoupper($event['_type_label'])) ?></span><h2 class="modal-title" id="<?= e($event['_modal_id']) ?>-title"><?= e($event['title']) ?></h2><p class="mb-0"><?= e($event['client_name'].' · '.$event['_date_label'].($event['_time_label']!==''?' · '.$event['_time_label']:'')) ?></p></div></div><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body calendar-detail-body"><div class="calendar-detail-status"><span class="badge badge-soft-<?= e($event['_status_class']) ?>"><?= e($event['_status_label']) ?></span></div><div class="calendar-detail-grid"><?php foreach($event['_details'] as [$label,$value]): ?><div><span><?= e($label) ?></span><strong><?= e($value) ?></strong></div><?php endforeach; ?></div>
    <?php if($event['_sections']): ?><div class="calendar-detail-sections"><?php foreach($event['_sections'] as [$heading,$body]): ?><section><h3><?= e($heading) ?></h3><p><?= nl2br(e($body)) ?></p></section><?php endforeach; ?></div><?php endif; ?></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button><?php if(!$isClientView&&!empty($event['client_id'])): ?><a class="btn btn-outline-primary" href="<?= e(agency_url('client',['id'=>(int)$event['client_id']])) ?>"><i class="fal fa-building mr-1"></i> Open client</a><?php endif; ?><?php if(!empty($event['project_id'])): ?><a class="btn btn-primary" href="<?= e(agency_url('tasks',['project_id'=>(int)$event['project_id'],'q'=>$event['event_type']==='task'?(string)$event['title']:''])) ?>"><i class="fal fa-tasks mr-1"></i> Open project tasks</a><?php endif; ?></div>
</div></div></div>
<?php endforeach; ?>

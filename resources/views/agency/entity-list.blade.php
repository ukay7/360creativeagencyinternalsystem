<?php
$badgeClass = static function(string $value): string {
    $danger=['overdue','at_risk','urgent','rejected','cancelled','inactive'];
    $warning=['attention','waiting','review','partially_paid','sent','confirmed','client_review','internal_review'];
    $success=['active','healthy','paid','completed','approved','published','won'];
    if(in_array($value,$danger,true)) return 'danger';
    if(in_array($value,$warning,true)) return 'warning';
    if(in_array($value,$success,true)) return 'success';
    return 'primary';
};
$renderCell = static function(array $row,array $column) use($badgeClass,$route): string {
    [$field,$label,$format]=$column;
    $value=$row[$field]??null;
    switch($format){
        case 'strong': return '<strong>'.e($value ?: '—').'</strong>';
        case 'lead_link': return '<a class="font-weight-bold" href="'.e(url('lead',['id'=>(int)$row['id']])).'">'.e($value).'</a><small class="d-block text-muted">'.e($row['services_interested'] ?: 'No services selected').'</small>';
        case 'client_link': return '<a class="font-weight-bold" href="'.e(url('client',['id'=>(int)$row['id']])).'">'.e($value).'</a><small class="d-block text-muted">'.e($row['industry']??'').'</small>';
        case 'lead_contact': return '<strong>'.e(trim(($row['first_name']??'').' '.($row['last_name']??''))).'</strong><small class="d-block text-muted">'.e($row['email']??'').'</small>';
        case 'money': return $value===null?'—':'<span class="font-weight-bold">'.e(money($value)).'</span>';
        case 'money_hour': return e(money($value)).'<small class="text-muted">/hr</small>';
        case 'hours': return e(number_format((float)$value,1)).'h';
        case 'number': return e(number_format((float)$value,0));
        case 'percent': return '<span class="font-weight-bold">'.(int)$value.'%</span>';
        case 'followup_due': if(!$value)return '<span class="badge badge-soft-secondary">Unscheduled</span>';$state=$row['follow_up_state']??'upcoming';$class=$state==='overdue'?'danger':($state==='due_today'?'warning':($state==='converted'?'success':'primary'));$label=$state==='overdue'?'Overdue':($state==='due_today'?'Due today':date('M j, Y',strtotime($value)));return '<span class="badge badge-soft-'.$class.'">'.e($label).'</span>';
        case 'days_left': if($value===null)return '—';$days=(int)$value;return '<span class="badge badge-soft-'.($days<=7?'danger':($days<=30?'warning':'primary')).'">'.$days.' days</span>';
        case 'date': return $value?e(date('M j, Y',strtotime($value))):'—';
        case 'datetime': return $value?e(date('M j · g:i A',strtotime($value))):'—';
        case 'human': return e(ucwords(str_replace('_',' ',$value)));
        case 'active': return '<span class="badge badge-soft-'.($value?'success':'secondary').'">'.($value?'Active':'Inactive').'</span>';
        case 'yesno': return $value?'<i class="fal fa-check-circle text-success"></i> Yes':'<span class="text-muted">No</span>';
        case 'badge': return '<span class="badge badge-soft-'.$badgeClass((string)$value).'">'.e(ucwords(str_replace('_',' ',(string)$value))).'</span>';
        case 'health': return '<span><i class="health-dot health-'.e((string)$value).'"></i>'.e(ucwords(str_replace('_',' ',(string)$value))).'</span>';
        case 'priority': return '<span class="badge badge-soft-'.$badgeClass((string)$value).'">'.e(ucfirst((string)$value)).'</span>';
        case 'score': return '<span class="score-ring" style="--score:'.(int)$value.'"><span>'.(int)$value.'</span></span>';
        case 'progress': $count=max(0,(int)($row['task_count']??0));$done=max(0,(int)($row['completed_tasks']??0));$percent=$count?round($done/$count*100):0;return '<span class="fs-xs">'.$done.'/'.$count.'</span><div class="progress-thin"><span style="width:'.$percent.'%"></span></div>';
        case 'visit_time': return e(trim(($row['start_time']??'').' – '.($row['end_time']??''),' –')) ?: 'TBD';
        case 'usage': if(!$value)return '—';$percent=$value['included']?min(100,round($value['used']/$value['included']*100)):0;return '<div class="usage-meter"><strong>'.$value['used'].'/'.$value['included'].'</strong><div class="meter"><span style="width:'.$percent.'%"></span></div><small class="text-muted">'.$value['remaining'].' left</small></div>';
        case 'status_form': $opts=['todo'=>'To Do','in_progress'=>'In Progress','waiting'=>'Waiting','review'=>'Review','completed'=>'Completed'];$html='<form method="post" action="'.e(url('tasks')).'" class="d-flex"><input type="hidden" name="_token" value="'.e(\AgencyOS\Csrf::token()).'"><input type="hidden" name="action" value="task_status"><input type="hidden" name="id" value="'.(int)$row['id'].'"><select name="status" class="custom-select custom-select-sm status-select">';foreach($opts as $id=>$name){$html.='<option value="'.$id.'"'.($value===$id?' selected':'').'>'.$name.'</option>';}$html.='</select></form>';return $html;
        case 'content_status': $opts=['idea'=>'Idea','draft'=>'Draft','internal_review'=>'Internal Review','client_review'=>'Client Review','approved'=>'Approved','scheduled'=>'Scheduled','published'=>'Published','archived'=>'Archived'];$html='<form method="post" action="'.e(url('content')).'"><input type="hidden" name="_token" value="'.e(\AgencyOS\Csrf::token()).'"><input type="hidden" name="action" value="content_status"><input type="hidden" name="id" value="'.(int)$row['id'].'"><select name="status" class="custom-select custom-select-sm status-select">';foreach($opts as $id=>$name){$html.='<option value="'.$id.'"'.($value===$id?' selected':'').'>'.$name.'</option>';}$html.='</select></form>';return $html;
        case 'approval_form': $opts=['pending'=>'Pending','approved'=>'Approve','rejected'=>'Reject','changes_requested'=>'Request changes'];$html='<form method="post" action="'.e(url('content')).'" class="approval-form"><input type="hidden" name="_token" value="'.e(\AgencyOS\Csrf::token()).'"><input type="hidden" name="action" value="content_approval"><input type="hidden" name="id" value="'.(int)$row['id'].'"><select name="approval_status" class="custom-select custom-select-sm mb-1">';foreach($opts as $id=>$name){$html.='<option value="'.$id.'"'.($value===$id?' selected':'').'>'.$name.'</option>';}$html.='</select><div class="d-flex"><input class="form-control form-control-sm mr-1" name="approval_comments" value="'.e($row['approval_comments']??'').'" placeholder="Comment"><button class="btn btn-xs btn-outline-primary">Save</button></div></form>';return $html;
        case 'invoice_status': $html='<span class="badge badge-soft-'.$badgeClass((string)$value).'">'.e(ucwords(str_replace('_',' ',(string)$value))).'</span>';if(!in_array($value,['paid','cancelled'],true)){$due=max(0,(float)$row['amount_due']);$html.='<button class="btn btn-xs btn-outline-success ml-2" data-toggle="modal" data-target="#modal-payment" data-payment data-id="'.(int)$row['id'].'" data-number="'.e($row['invoice_number']).'" data-due="'.e((string)$due).'">Pay</button>';}return $html;
        default: return e($value ?: '—');
    }
};
?>
<div class="page-title-wrap">
    <div><span class="eyebrow">AGENCY OPERATIONS</span><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div>
    <button class="btn btn-primary" data-toggle="modal" data-target="#modal-add"><i class="fal fa-plus mr-1"></i> <?= e($addLabel) ?></button>
</div>

<?php if($route==='leads'): ?>
<section class="panel lead-filter-panel">
    <div class="panel-container show"><div class="panel-content py-3"><form method="get" action="<?= e(url('leads')) ?>" class="lead-filter-form">
        <div><label for="filter_owner">Owner</label><select class="custom-select custom-select-sm" id="filter_owner" name="assigned_employee_id"><option value="">All owners</option><?php foreach($filterOptions['employees'] as $employee): ?><option value="<?= (int)$employee['id'] ?>" <?= (int)($filters['assigned_employee_id']??0)===(int)$employee['id']?'selected':'' ?>><?= e($employee['name']) ?></option><?php endforeach; ?></select></div>
        <div><label for="filter_status">Status</label><select class="custom-select custom-select-sm" id="filter_status" name="status"><option value="">All statuses</option><?php foreach(['new'=>'New','contacted'=>'Contacted','discovery'=>'Discovery','qualified'=>'Qualified','proposal'=>'Proposal sent','negotiation'=>'Negotiation','lost'=>'Lost','converted'=>'Converted'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($filters['status']??'')===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div><label for="filter_source">Source</label><select class="custom-select custom-select-sm" id="filter_source" name="source_id"><option value="">All sources</option><?php foreach($filterOptions['sources'] as $source): ?><option value="<?= (int)$source['id'] ?>" <?= (int)($filters['source_id']??0)===(int)$source['id']?'selected':'' ?>><?= e($source['name']) ?></option><?php endforeach; ?></select></div>
        <div><label for="filter_fit">Minimum fit</label><select class="custom-select custom-select-sm" id="filter_fit" name="min_fit_score"><option value="">Any score</option><?php foreach([40=>'40+',60=>'60+',80=>'80+'] as $value=>$label): ?><option value="<?= $value ?>" <?= (string)($filters['min_fit_score']??'')===(string)$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div><label for="filter_followup">Follow-up</label><select class="custom-select custom-select-sm" id="filter_followup" name="follow_up_state"><option value="">Any schedule</option><?php foreach(['overdue'=>'Overdue','due_today'=>'Due today','upcoming'=>'Upcoming','unscheduled'=>'Unscheduled'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($filters['follow_up_state']??'')===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="lead-filter-actions"><button class="btn btn-sm btn-primary"><i class="fal fa-filter mr-1"></i> Apply</button><a href="<?= e(url('leads')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a></div>
    </form></div></div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-container show"><div class="panel-content">
        <div class="data-toolbar">
            <div class="input-group table-search"><div class="input-group-prepend"><span class="input-group-text bg-white border-right-0"><i class="fal fa-search text-muted"></i></span></div><input type="search" class="form-control border-left-0" placeholder="Search this table…" data-table-search aria-label="Search table"></div>
            <div class="text-muted fs-sm"><strong><?= count($rows) ?></strong> records · <button class="btn btn-sm btn-outline-secondary ml-2" data-export><i class="fal fa-file-csv mr-1"></i> CSV</button> <button class="btn btn-sm btn-outline-secondary" data-print><i class="fal fa-print mr-1"></i> Print</button></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" data-agency-table><thead><tr><?php foreach($columns as $column): ?><th><?= e($column[1]) ?></th><?php endforeach; ?><?php if(in_array($route,['leads','visits'],true)): ?><th class="text-right">Action</th><?php endif; ?></tr></thead><tbody>
            <?php foreach($rows as $row): ?><tr data-table-row><?php foreach($columns as $column): ?><td><?= $renderCell($row,$column) ?></td><?php endforeach; ?>
                <?php if($route==='leads'): ?><td class="text-right"><a href="<?= e(url('lead',['id'=>(int)$row['id']])) ?>" class="btn btn-sm btn-outline-secondary mr-1"><i class="fal fa-eye mr-1"></i> View</a><?php if(!$row['converted_client_id']): ?><form method="post" action="<?= e(url('leads')) ?>" class="d-inline"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="convert_lead"><input type="hidden" name="lead_id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-outline-primary" data-confirm="Convert this lead into a client?">Convert</button></form><?php else: ?><a href="<?= e(url('client',['id'=>(int)$row['converted_client_id']])) ?>" class="btn btn-sm btn-outline-success">Client</a><?php endif; ?></td><?php endif; ?>
                <?php if($route==='visits'): ?><td class="text-right"><?php if(!in_array($row['status'],['completed','cancelled'],true)): ?><form method="post" action="<?= e(url('visits')) ?>"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="complete_visit"><input type="hidden" name="visit_id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-outline-success" data-confirm="Mark this visit complete and update allowance usage?">Complete</button></form><?php else: ?><span class="text-muted fs-xs"><?= $row['is_additional']?'Billable overage':'Allowance used' ?></span><?php endif; ?></td><?php endif; ?>
            </tr><?php endforeach; ?>
            <?php if(!$rows): ?><tr><td colspan="<?= count($columns)+1 ?>"><div class="empty-state"><i class="fal fa-inbox"></i><h3>No records yet</h3><p class="text-muted">Use “<?= e($addLabel) ?>” to create the first one.</p></div></td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </div></div>
</section>

<div class="modal fade" id="modal-add" tabindex="-1" role="dialog" aria-labelledby="modal-add-title" aria-hidden="true"><div class="modal-dialog <?= $route==='leads'?'modal-xl':'modal-lg' ?>" role="document"><div class="modal-content">
    <form method="post" action="<?= e(url($route)) ?>"><div class="modal-header"><div><span class="eyebrow mb-1">QUICK CREATE</span><h2 class="modal-title fs-xl" id="modal-add-title"><?= e($addLabel) ?></h2></div><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="<?= e($action) ?>"><div class="row">
    <?php if($route==='leads'): ?><div class="col-12"><div class="alert alert-info d-flex align-items-start mb-3"><i class="fal fa-sparkles mr-2 mt-1"></i><div><strong>Lead fit score is calculated automatically</strong><span class="d-block fs-sm">Company size, business age, stage, budget, and service interest measure agency fit. Conversion probability is tracked separately through the pipeline.</span></div></div></div><?php endif; ?>
    <?php foreach($fields as $field): [$name,$label,$type,$required]=$field; $fieldOptions=$field[4]??null; $default=$field[5]??''; $wide=$type==='textarea'; ?>
        <?php if($type==='checkbox'): ?><div class="col-12"><div class="custom-control custom-checkbox mb-3"><input type="checkbox" class="custom-control-input" id="field_<?= e($name) ?>" name="<?= e($name) ?>" value="1" <?= $default?'checked':'' ?>><label class="custom-control-label" for="field_<?= e($name) ?>"><?= e($label) ?></label></div></div>
        <?php elseif($type==='multicheck'): ?><div class="col-12"><div class="form-group"><label class="<?= $required?'required':'' ?>"><?= e($label) ?></label><div class="row border rounded p-2 mx-0" style="max-height:220px;overflow:auto"><?php foreach((array)$fieldOptions as $option): ?><div class="col-md-6 col-lg-4"><div class="custom-control custom-checkbox py-1"><input type="checkbox" class="custom-control-input" id="field_<?= e($name) ?>_<?= e($option['id']) ?>" name="<?= e($name) ?>[]" value="<?= e($option['id']) ?>"><label class="custom-control-label" for="field_<?= e($name) ?>_<?= e($option['id']) ?>"><?= e($option['name']) ?></label></div></div><?php endforeach; ?></div><small class="form-text text-muted">Select every service the lead has expressed interest in.</small></div></div>
        <?php else: ?><div class="<?= $wide?'col-12':'col-md-6' ?>"><div class="form-group"><label class="<?= $required?'required':'' ?>" for="field_<?= e($name) ?>"><?= e($label) ?></label>
            <?php if($type==='select'): ?><select class="custom-select" id="field_<?= e($name) ?>" name="<?= e($name) ?>" <?= $required?'required':'' ?> <?= $name==='client_id'?'data-client-filter="field_project_id"':'' ?>><option value="">Select <?= e(strtolower($label)) ?></option><?php foreach((array)$fieldOptions as $option): ?><option value="<?= e($option['id']) ?>" <?= (string)$default===(string)$option['id']?'selected':'' ?> <?= isset($option['client_id'])?'data-client="'.e($option['client_id']).'"':'' ?>><?= e($option['name']) ?><?= isset($option['price']) && (float)$option['price']>0?' · '.e(money($option['price'])):'' ?></option><?php endforeach; ?></select>
            <?php elseif($type==='textarea'): ?><textarea class="form-control" id="field_<?= e($name) ?>" name="<?= e($name) ?>" rows="3" <?= $required?'required':'' ?>><?= e($default) ?></textarea>
            <?php else: ?><input class="form-control" id="field_<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" value="<?= e($default) ?>" <?= $type==='number'?'min="0" step="0.01"':'' ?> <?= $required?'required':'' ?>><?php endif; ?>
        </div></div><?php endif; ?>
    <?php endforeach; ?>
    </div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save <?= e(strtolower($addLabel)) ?></button></div></form>
</div></div></div>

<?php if($route==='invoices'): ?>
<div class="modal fade" id="modal-payment" tabindex="-1" role="dialog" aria-labelledby="payment-title" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content"><form method="post" action="<?= e(url('invoices')) ?>">
<div class="modal-header"><div><span class="eyebrow mb-1">COLLECTIONS</span><h2 id="payment-title" class="modal-title fs-xl">Record payment · <span id="payment_invoice"></span></h2></div><button class="close" type="button" data-dismiss="modal"><span>&times;</span></button></div>
<div class="modal-body"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="record_payment"><input type="hidden" id="payment_invoice_id" name="invoice_id"><div class="form-group"><label class="required" for="payment_amount">Amount</label><input class="form-control" id="payment_amount" type="number" step="0.01" min="0.01" name="amount" required></div><div class="form-group"><label class="required">Payment date</label><input class="form-control" type="date" name="payment_date" value="<?= e(date('Y-m-d')) ?>" required></div><div class="form-group"><label class="required">Method</label><select class="custom-select" name="method" required><option>Bank transfer</option><option>Credit card</option><option>Cheque</option><option>Cash</option><option>Other</option></select></div><div class="form-group"><label>Reference</label><input class="form-control" name="reference"></div></div>
<div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cancel</button><button class="btn btn-success" type="submit">Record payment</button></div></form></div></div></div>
<?php endif; ?>

<?php
$total=0;$weighted=0;$openDeals=0;$overdueDeals=0;$visibleDeals=0;
$stageById=[];
foreach($stages as $stage){
    $stageById[(int)$stage['id']]=$stage;
    $visibleDeals+=count($stage['opportunities']);
    if(!$stage['is_closed']){
        foreach($stage['opportunities'] as $deal){
            $total+=(float)$deal['estimated_value'];
            $weighted+=(float)$deal['estimated_value']*(int)$deal['probability']/100;
            $openDeals++;
            if($deal['expected_close_date'] && $deal['expected_close_date']<date('Y-m-d')){$overdueDeals++;}
        }
    }
}
$filterActive=count(array_filter($filters??[],static fn($value)=>$value!=='' && $value!==null))>0;
$colorClass=static fn(string $color): string=>$color==='purple'?'primary':$color;
?>
<div class="page-title-wrap"><div><span class="eyebrow">SALES</span><h1>Visual Sales Pipeline</h1><p>Manage opportunity value, ownership, next actions, and controlled stage progression.</p></div><a href="<?= e(url('leads')) ?>#modal-add" class="btn btn-primary"><i class="fal fa-user-plus mr-1"></i> New lead</a></div>

<section class="panel pipeline-filter-panel mb-3"><div class="panel-container show"><div class="panel-content py-3">
    <form method="get" action="<?= e(url('pipeline')) ?>" class="pipeline-filter-form">
        <div class="pipeline-search-filter"><label for="pipeline_q">Search</label><input class="form-control form-control-sm" id="pipeline_q" name="q" value="<?= e($filters['q']??'') ?>" placeholder="Company, deal, or owner"></div>
        <div><label for="pipeline_owner">Owner</label><select class="custom-select custom-select-sm" id="pipeline_owner" name="owner_id"><option value="">All owners</option><?php foreach($options['employees'] as $employee): ?><option value="<?= (int)$employee['id'] ?>" <?= (int)($filters['owner_id']??0)===(int)$employee['id']?'selected':'' ?>><?= e($employee['name']) ?></option><?php endforeach; ?></select></div>
        <div><label for="pipeline_stage">Stage</label><select class="custom-select custom-select-sm" id="pipeline_stage" name="stage_id"><option value="">All stages</option><?php foreach($stages as $stage): ?><option value="<?= (int)$stage['id'] ?>" <?= (int)($filters['stage_id']??0)===(int)$stage['id']?'selected':'' ?>><?= e($stage['name']) ?></option><?php endforeach; ?></select></div>
        <div><label for="pipeline_close">Close date</label><select class="custom-select custom-select-sm" id="pipeline_close" name="close_window"><option value="">Any date</option><?php foreach(['overdue'=>'Overdue','next_7'=>'Next 7 days','next_30'=>'Next 30 days','no_date'=>'No close date'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($filters['close_window']??'')===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div><label for="pipeline_service">Service</label><select class="custom-select custom-select-sm" id="pipeline_service" name="service_id"><option value="">All services</option><?php foreach($options['services'] as $service): ?><option value="<?= (int)$service['id'] ?>" <?= (int)($filters['service_id']??0)===(int)$service['id']?'selected':'' ?>><?= e($service['name']) ?></option><?php endforeach; ?></select></div>
        <div class="pipeline-value-filter"><label>Opportunity value</label><div class="d-flex"><input class="form-control form-control-sm mr-1" type="number" min="0" name="min_value" value="<?= e($filters['min_value']??'') ?>" placeholder="Min"><input class="form-control form-control-sm" type="number" min="0" name="max_value" value="<?= e($filters['max_value']??'') ?>" placeholder="Max"></div></div>
        <div class="pipeline-filter-actions"><button class="btn btn-sm btn-primary"><i class="fal fa-filter mr-1"></i> Apply</button><a href="<?= e(url('pipeline')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a></div>
    </form>
</div></div></section>

<section class="panel pipeline-summary mb-3"><div class="panel-container show"><div class="panel-content"><div class="row align-items-center">
    <div class="col-6 col-lg-3"><small class="text-uppercase opacity-60">Open pipeline value</small><div class="fs-xxl font-weight-bold"><?= e(money($total)) ?></div></div>
    <div class="col-6 col-lg-3"><small class="text-uppercase opacity-60">Weighted forecast</small><div class="fs-xxl font-weight-bold"><?= e(money($weighted)) ?></div></div>
    <div class="col-6 col-lg-3 mt-3 mt-lg-0"><small class="text-uppercase opacity-60">Open opportunities</small><div class="fs-xxl font-weight-bold"><?= (int)$openDeals ?></div></div>
    <div class="col-6 col-lg-3 mt-3 mt-lg-0"><small class="text-uppercase opacity-60">Overdue close dates</small><div class="fs-xxl font-weight-bold"><?= (int)$overdueDeals ?></div></div>
</div></div></div></section>

<?php if($filterActive): ?><div class="alert alert-info py-2"><i class="fal fa-filter mr-2"></i>Showing <?= (int)$visibleDeals ?> matching opportunities. Stage totals and forecasts reflect the active filters.</div><?php endif; ?>
<div class="pipeline-scroll-caption"><i class="fal fa-arrows-h mr-1"></i> Scroll horizontally to review every stage. Select a card to edit details or view its history.</div>
<div class="pipeline-board-shell">
<div class="pipeline-board" aria-label="Sales pipeline stages">
<?php foreach($stages as $index=>$stage): ?>
    <section class="pipeline-column" data-stage="<?= e($stage['slug']) ?>">
        <div class="pipeline-column-head"><span><i class="fal fa-circle text-<?= e($colorClass($stage['color'])) ?> mr-1"></i><?= e($stage['name']) ?></span><span class="pipeline-count"><?= count($stage['opportunities']) ?></span></div>
        <div class="pipeline-column-sub"><span><?= e(money(array_sum(array_column($stage['opportunities'],'estimated_value')))) ?></span><span><?= (int)$stage['win_probability'] ?>% probability</span></div>
        <?php foreach($stage['opportunities'] as $deal):
            $isOverdue=$deal['expected_close_date'] && $deal['expected_close_date']<date('Y-m-d') && !$stage['is_closed'];
            $nextStage=$stages[$index+1]??null;
            $previousStage=$stages[$index-1]??null;
            if($stage['slug']==='lost' && $deal['previous_stage_id'] && isset($stageById[(int)$deal['previous_stage_id']])){$previousStage=$stageById[(int)$deal['previous_stage_id']];}
            $currentServices=array_filter(array_map('trim',explode(',',(string)$deal['services'])));
        ?>
            <article class="deal-card <?= $isOverdue?'deal-card-overdue':'' ?>">
                <button type="button" class="deal-card-main" data-toggle="modal" data-target="#opportunity-<?= (int)$deal['id'] ?>" aria-label="Open <?= e($deal['business_name']) ?> opportunity">
                    <div class="d-flex justify-content-between align-items-start"><h4><?= e($deal['business_name']) ?></h4><?php if((int)$deal['stage_age_days']>=14 && !$stage['is_closed']): ?><span class="badge badge-soft-warning" title="Time in current stage"><?= (int)$deal['stage_age_days'] ?>d</span><?php endif; ?></div>
                    <div class="deal-value"><?= e(money($deal['estimated_value'])) ?></div>
                    <div class="deal-meta"><i class="fal fa-user mr-1"></i><?= e($deal['owner_name'] ?: 'Unassigned') ?> · <?= (int)$deal['probability'] ?>%</div>
                    <div class="deal-meta <?= $isOverdue?'text-danger font-weight-bold':'' ?>"><i class="fal fa-calendar mr-1"></i><?= $deal['expected_close_date']?e(date('M j, Y',strtotime($deal['expected_close_date']))):'No close date' ?><?= $isOverdue?' · overdue':'' ?></div>
                    <div class="deal-meta text-truncate" title="<?= e($deal['next_action']) ?>"><i class="fal fa-bolt mr-1"></i><?= e($deal['next_action'] ?: 'No next action') ?></div>
                    <div class="deal-record-flags"><?php if($deal['discovery_count']): ?><span><i class="fal fa-comments-alt"></i> Discovery</span><?php endif; ?><?php if($deal['proposal_count']): ?><span><i class="fal fa-file-signature"></i> Proposal</span><?php endif; ?></div>
                </button>
                <div class="deal-actions">
                    <button type="button" class="btn btn-xs btn-outline-secondary" data-toggle="modal" data-target="#opportunity-<?= (int)$deal['id'] ?>" title="View and edit"><i class="fal fa-pen"></i></button>
                    <?php if(!$stage['is_closed'] && $previousStage): ?><form method="post" action="<?= e(url('pipeline')) ?>"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="move_opportunity"><input type="hidden" name="opportunity_id" value="<?= (int)$deal['id'] ?>"><input type="hidden" name="stage_id" value="<?= (int)$previousStage['id'] ?>"><button class="btn btn-xs btn-outline-secondary" title="Move back to <?= e($previousStage['name']) ?>"><i class="fal fa-arrow-left"></i></button></form><?php endif; ?>
                    <?php if(!$stage['is_closed'] && $nextStage): ?>
                        <?php if($nextStage['slug']==='discovery' && $deal['lead_id']): ?><a class="btn btn-xs btn-outline-primary ml-auto" href="<?= e(url('discovery').'?lead_id='.(int)$deal['lead_id'].'#modal-add') ?>" title="Complete discovery"><i class="fal fa-comments-alt mr-1"></i> Discovery</a>
                        <?php elseif($nextStage['slug']==='proposal' && !(int)$deal['proposal_count']): ?><a class="btn btn-xs btn-outline-primary ml-auto" href="<?= e(url('proposals').'?opportunity_id='.(int)$deal['id'].'#modal-add') ?>" title="Create linked proposal"><i class="fal fa-file-plus mr-1"></i> Proposal</a>
                        <?php elseif($nextStage['slug']==='won'): ?><button type="button" class="btn btn-xs btn-outline-success ml-auto" data-toggle="modal" data-target="#win-<?= (int)$deal['id'] ?>"><i class="fal fa-trophy mr-1"></i> Won</button>
                        <?php else: ?><form method="post" action="<?= e(url('pipeline')) ?>" class="ml-auto"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="move_opportunity"><input type="hidden" name="opportunity_id" value="<?= (int)$deal['id'] ?>"><input type="hidden" name="stage_id" value="<?= (int)$nextStage['id'] ?>"><button class="btn btn-xs btn-outline-primary" title="Advance to <?= e($nextStage['name']) ?>" <?= $nextStage['slug']==='proposal'?'data-confirm="Confirm the linked proposal has been sent to the client?"':'' ?>><i class="fal fa-arrow-right"></i></button></form><?php endif; ?>
                        <button type="button" class="btn btn-xs btn-outline-danger" data-toggle="modal" data-target="#lost-<?= (int)$deal['id'] ?>" title="Close as lost"><i class="fal fa-times"></i></button>
                    <?php elseif($stage['slug']==='lost' && $previousStage): ?><form method="post" action="<?= e(url('pipeline')) ?>" class="ml-auto"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="move_opportunity"><input type="hidden" name="opportunity_id" value="<?= (int)$deal['id'] ?>"><input type="hidden" name="stage_id" value="<?= (int)$previousStage['id'] ?>"><button class="btn btn-xs btn-outline-primary"><i class="fal fa-redo mr-1"></i> Reopen</button></form><?php endif; ?>
                </div>
            </article>

            <div class="modal fade" id="opportunity-<?= (int)$deal['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="opportunity-title-<?= (int)$deal['id'] ?>" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content">
                <div class="modal-header"><div><span class="eyebrow">SALES OPPORTUNITY</span><h2 class="modal-title" id="opportunity-title-<?= (int)$deal['id'] ?>"><?= e($deal['business_name']) ?></h2><div class="text-muted fs-sm mt-1"><?= e($stage['name']) ?> · <?= (int)$deal['probability'] ?>% conversion probability · <?= (int)$deal['stage_age_days'] ?> days in stage</div></div><button class="close" type="button" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body"><div class="row">
                    <div class="col-lg-8"><form method="post" action="<?= e(url('pipeline')) ?>"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="update_opportunity"><input type="hidden" name="opportunity_id" value="<?= (int)$deal['id'] ?>">
                        <div class="row"><div class="col-md-8"><div class="form-group"><label class="required">Opportunity title</label><input class="form-control" name="title" value="<?= e($deal['title']) ?>" required></div></div><div class="col-md-4"><div class="form-group"><label class="required">Estimated value</label><input class="form-control" type="number" min="0" step="0.01" name="estimated_value" value="<?= e($deal['estimated_value']) ?>" required></div></div></div>
                        <div class="row"><div class="col-md-6"><div class="form-group"><label>Owner</label><select class="custom-select" name="owner_id"><option value="">Unassigned</option><?php foreach($options['employees'] as $employee): ?><option value="<?= (int)$employee['id'] ?>" <?= (int)$deal['owner_id']===(int)$employee['id']?'selected':'' ?>><?= e($employee['name']) ?></option><?php endforeach; ?></select></div></div><div class="col-md-6"><div class="form-group"><label>Expected close date</label><input class="form-control" type="date" name="expected_close_date" value="<?= e($deal['expected_close_date']) ?>"></div></div></div>
                        <div class="form-group"><label>Services</label><div class="pipeline-service-grid"><?php foreach($options['services'] as $service): ?><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="opportunity-service-<?= (int)$deal['id'] ?>-<?= (int)$service['id'] ?>" name="service_ids[]" value="<?= (int)$service['id'] ?>" <?= in_array($service['name'],$currentServices,true)?'checked':'' ?>><label class="custom-control-label" for="opportunity-service-<?= (int)$deal['id'] ?>-<?= (int)$service['id'] ?>"><?= e($service['name']) ?></label></div><?php endforeach; ?></div></div>
                        <div class="form-group"><label>Next action</label><input class="form-control" name="next_action" value="<?= e($deal['next_action']) ?>" placeholder="Specific action and owner"></div>
                        <div class="form-group"><label>Opportunity notes</label><textarea class="form-control" name="notes" rows="3"><?= e($deal['notes']) ?></textarea></div>
                        <button class="btn btn-primary"><i class="fal fa-save mr-1"></i> Save opportunity</button>
                    </form></div>
                    <div class="col-lg-4 mt-4 mt-lg-0"><div class="pipeline-related-actions"><h3>Related records</h3><?php if($deal['lead_id']): ?><a href="<?= e(url('lead',['id'=>(int)$deal['lead_id']])) ?>"><i class="fal fa-user-circle"></i> Open lead profile</a><a href="<?= e(url('discovery').'?lead_id='.(int)$deal['lead_id'].'#modal-add') ?>"><i class="fal fa-comments-alt"></i> <?= $deal['discovery_count']?'Add discovery session':'Start discovery' ?></a><?php endif; ?><a href="<?= e($deal['proposal_count']?url('proposals'):url('proposals').'?opportunity_id='.(int)$deal['id'].'#modal-add') ?>"><i class="fal fa-file-signature"></i> <?= $deal['proposal_count']?'View linked proposal':'Create proposal' ?></a><?php if($deal['client_id']): ?><a href="<?= e(url('client',['id'=>(int)$deal['client_id']])) ?>"><i class="fal fa-building"></i> Open client profile</a><?php endif; ?></div>
                        <div class="pipeline-history mt-4"><h3>Activity history</h3><?php foreach($deal['history'] as $history): ?><div class="pipeline-history-item"><span></span><div><strong><?= e($history['summary']) ?></strong><small><?= e($history['user_name'] ?: 'System') ?> · <?= e(date('M j, Y g:i A',strtotime($history['created_at']))) ?></small></div></div><?php endforeach; ?><?php if(!$deal['history']): ?><p class="text-muted fs-sm">No edits or stage changes recorded yet.</p><?php endif; ?></div>
                    </div>
                </div></div>
            </div></div></div>

            <?php if(!$stage['is_closed']): ?><div class="modal fade" id="lost-<?= (int)$deal['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="<?= e(url('pipeline')) ?>"><div class="modal-header"><div><span class="eyebrow">CLOSE OPPORTUNITY</span><h2 class="modal-title">Mark <?= e($deal['business_name']) ?> as lost</h2></div><button class="close" type="button" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="move_opportunity"><input type="hidden" name="opportunity_id" value="<?= (int)$deal['id'] ?>"><input type="hidden" name="stage_id" value="<?= (int)($stageById[array_key_first(array_filter($stageById,static fn($item)=>$item['slug']==='lost'))]['id']??0) ?>"><div class="alert alert-warning"><i class="fal fa-history mr-2"></i>The reason is retained in both the opportunity and lead history.</div><div class="form-group"><label class="required">Lost reason</label><textarea class="form-control" name="lost_reason" rows="4" required placeholder="Budget, timing, competitor, no response, poor fit…"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button class="btn btn-danger">Close as lost</button></div></form></div></div></div>
            <div class="modal fade" id="win-<?= (int)$deal['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="<?= e(url('pipeline')) ?>"><div class="modal-header"><div><span class="eyebrow">CLOSE OPPORTUNITY</span><h2 class="modal-title">Mark <?= e($deal['business_name']) ?> as won</h2></div><button class="close" type="button" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="move_opportunity"><input type="hidden" name="opportunity_id" value="<?= (int)$deal['id'] ?>"><input type="hidden" name="stage_id" value="<?= (int)($stageById[array_key_first(array_filter($stageById,static fn($item)=>$item['slug']==='won'))]['id']??0) ?>"><div class="alert alert-success mb-0"><i class="fal fa-check-circle mr-2"></i>This will convert the lead into a client, transfer the relationship data, close the opportunity, and open the client profile.</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button class="btn btn-success"><i class="fal fa-trophy mr-1"></i> Confirm won</button></div></form></div></div></div><?php endif; ?>
        <?php endforeach; ?>
        <?php if(!$stage['opportunities']): ?><div class="pipeline-empty"><i class="fal fa-inbox"></i><span>No matching opportunities</span></div><?php endif; ?>
    </section>
<?php endforeach; ?>
</div></div>

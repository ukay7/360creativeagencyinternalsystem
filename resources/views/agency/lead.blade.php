<?php
$companySizeLabels=['1_10'=>'1–10 people','11_50'=>'11–50 people','51_100'=>'51–100 people','101_250'=>'101–250 people','251_plus'=>'251+ people'];
$businessYearLabels=['under_1'=>'Less than 1 year','1_2'=>'1–2 years','3_5'=>'3–5 years','6_10'=>'6–10 years','10_plus'=>'10+ years'];
$businessStageLabels=['new_business'=>'New business','existing_business'=>'Existing business'];
$budgetLabels=['under_1k'=>'Under $1,000','1k_5k'=>'$1,000–$5,000','5k_10k'=>'$5,000–$10,000','10k_25k'=>'$10,000–$25,000','25k_50k'=>'$25,000–$50,000','50k_plus'=>'$50,000+'];
$statusLabels=['new'=>'New','contacted'=>'Contacted','discovery'=>'Discovery','qualified'=>'Qualified','proposal'=>'Proposal sent','negotiation'=>'Negotiation','lost'=>'Lost'];
$followupTypes=['call'=>'Phone call','email'=>'Email','meeting'=>'Meeting','message'=>'Message','note'=>'Internal note','other'=>'Other'];
$outcomeLabels=['connected'=>'Connected','no_answer'=>'No answer','interested'=>'Interested','needs_follow_up'=>'Needs follow-up','meeting_scheduled'=>'Meeting scheduled','not_interested'=>'Not interested','completed'=>'Completed'];
$followupIcons=['call'=>'fa-phone','email'=>'fa-envelope','meeting'=>'fa-users','message'=>'fa-comment-alt','note'=>'fa-sticky-note','other'=>'fa-ellipsis-h'];
$isConverted=!empty($lead['converted_client_id']);
$isOverdue=!$isConverted && $lead['status']!=='lost' && $lead['next_follow_up_at'] && strtotime($lead['next_follow_up_at'])<time();
$nextFollowup=$lead['next_follow_up_at']?date('M j, Y · g:i A',strtotime($lead['next_follow_up_at'])):'Not scheduled';
$lastContact=$lead['last_contact_at']?date('M j, Y · g:i A',strtotime($lead['last_contact_at'])):'No contact recorded';
?>

<div class="page-title-wrap mb-3">
    <div class="d-flex align-items-start">
        <a href="<?= e(url('leads')) ?>" class="btn btn-sm btn-outline-secondary mr-3 mt-1" aria-label="Back to leads"><i class="fal fa-arrow-left"></i></a>
        <div><span class="eyebrow mb-1">LEAD RELATIONSHIP</span><h1><?= e($lead['company_name']) ?></h1><p><?= e(trim($lead['first_name'].' '.$lead['last_name'])) ?> · <?= e($lead['email']) ?></p></div>
    </div>
    <div class="d-flex flex-wrap">
        <?php if($isConverted): ?>
            <a href="<?= e(url('client',['id'=>(int)$lead['converted_client_id']])) ?>" class="btn btn-success"><i class="fal fa-building mr-1"></i> View client</a>
        <?php else: ?>
            <button class="btn btn-outline-secondary mr-2 mb-2" data-toggle="modal" data-target="#modal-edit-lead"><i class="fal fa-pen mr-1"></i> Edit lead</button>
            <button class="btn btn-primary mr-2 mb-2" data-toggle="modal" data-target="#modal-followup"><i class="fal fa-plus mr-1"></i> Add follow-up</button>
            <a href="<?= e(url('discovery').'?lead_id='.(int)$lead['id'].'#modal-add') ?>" class="btn btn-outline-primary mr-2 mb-2"><i class="fal fa-comments-alt mr-1"></i> Start discovery</a>
            <form method="post" action="<?= e(url('lead',['id'=>(int)$lead['id']])) ?>" class="mb-2"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="convert_lead"><input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>"><button class="btn btn-outline-success" data-confirm="Convert this lead into an active client?"><i class="fal fa-exchange mr-1"></i> Convert to client</button></form>
        <?php endif; ?>
    </div>
</div>

<?php if($isConverted): ?><div class="alert alert-success"><i class="fal fa-check-circle mr-2"></i>This lead has been converted. Its history remains read-only here; ongoing relationship work continues in the client profile.</div><?php endif; ?>
<?php if($lead['status']==='lost'): ?><div class="alert alert-danger"><i class="fal fa-times-circle mr-2"></i><strong>Lost lead:</strong> <?= e($lead['lost_reason'] ?: 'No reason recorded') ?><?php if(!$isConverted): ?> <button class="btn btn-sm btn-outline-danger ml-2" data-toggle="modal" data-target="#modal-edit-lead">Reopen or edit</button><?php endif; ?></div><?php endif; ?>
<?php if($isOverdue): ?><div class="alert alert-warning"><i class="fal fa-alarm-exclamation mr-2"></i><strong>Follow-up overdue:</strong> this lead was due <?= e(date('M j, Y · g:i A',strtotime($lead['next_follow_up_at']))) ?>. Record the outcome or reschedule it.</div><?php endif; ?>

<section class="client-hero lead-hero">
    <div class="d-flex align-items-center position-relative" style="z-index:1">
        <div class="client-avatar mr-3"><?= e(strtoupper(substr($lead['company_name'],0,2))) ?></div>
        <div class="flex-1"><h2 class="mb-1 text-white fs-xxl"><?= e($lead['company_name']) ?></h2><div class="opacity-70"><?= e($lead['industry'] ?: 'Industry not set') ?> · <?= e($lead['source_name'] ?: 'Source not recorded') ?></div></div>
        <div class="d-flex align-items-center mt-2 mt-lg-0">
            <div class="client-stat"><small>Fit score</small><strong><?= (int)$lead['lead_score'] ?>/100</strong></div>
            <div class="client-stat"><small>Conversion probability</small><strong><?= (int)$lead['conversion_probability'] ?>%</strong></div>
            <div class="client-stat"><small>Pipeline stage</small><strong><?= e($lead['pipeline_stage_name'] ?: ($statusLabels[$lead['status']] ?? ucfirst($lead['status']))) ?></strong></div>
            <div class="client-stat"><small>Owner</small><strong><?= e($lead['owner_name'] ?: 'Unassigned') ?></strong></div>
            <div class="client-stat"><small>Next follow-up</small><strong><?= e($nextFollowup) ?></strong></div>
        </div>
    </div>
</section>

<div class="kpi-grid">
    <article class="kpi-card"><div class="kpi-label">Estimated opportunity</div><div class="kpi-value"><?= e(money($lead['estimated_budget'])) ?></div><div class="kpi-meta"><?= e($budgetLabels[$lead['budget_range']] ?? 'Budget not qualified') ?></div><i class="fal fa-sack-dollar kpi-icon"></i></article>
    <article class="kpi-card"><div class="kpi-label">Follow-ups recorded</div><div class="kpi-value"><?= count($lead['followups']) ?></div><div class="kpi-meta">Permanent communication history</div><i class="fal fa-comments-alt kpi-icon"></i></article>
    <article class="kpi-card"><div class="kpi-label">Last contact</div><div class="kpi-value fs-xl"><?= e($lead['last_contact_at']?date('M j',strtotime($lead['last_contact_at'])):'—') ?></div><div class="kpi-meta"><?= e($lastContact) ?></div><i class="fal fa-history kpi-icon"></i></article>
    <article class="kpi-card"><div class="kpi-label">Discovery sessions</div><div class="kpi-value fs-xl"><?= count($lead['consultations']) ?></div><div class="kpi-meta"><?= count($lead['consultations'])?'Qualification conversations completed':'Discovery not started' ?></div><i class="fal fa-comments-alt kpi-icon"></i></article>
</div>

<div class="row">
    <div class="col-xl-8">
        <section class="panel">
            <div class="panel-hdr"><h2>Follow-up timeline</h2><?php if(!$isConverted): ?><div class="panel-toolbar"><button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#modal-followup"><i class="fal fa-plus mr-1"></i> Add follow-up</button></div><?php endif; ?></div>
            <div class="panel-container show"><div class="panel-content">
                <div class="activity-timeline lead-timeline">
                    <?php foreach($lead['followups'] as $followup): ?>
                        <article class="timeline-item followup-entry">
                            <div class="d-flex align-items-start justify-content-between mb-1">
                                <div><span class="followup-icon"><i class="fal <?= e($followupIcons[$followup['type']] ?? 'fa-comment') ?>"></i></span><strong><?= e($followup['subject'] ?: ($followupTypes[$followup['type']] ?? ucfirst($followup['type']))) ?></strong></div>
                                <div><?php if($followup['outcome']): ?><span class="badge badge-soft-primary mr-1"><?= e($outcomeLabels[$followup['outcome']] ?? ucwords(str_replace('_',' ',$followup['outcome']))) ?></span><?php endif; ?><?php if(!$isConverted): ?><button class="btn btn-xs btn-outline-secondary" data-toggle="modal" data-target="#modal-correct-followup-<?= (int)$followup['id'] ?>"><i class="fal fa-pen mr-1"></i> Correct</button><?php endif; ?></div>
                            </div>
                            <p class="mb-2"><?= nl2br(e($followup['notes'])) ?></p>
                            <?php if($followup['next_follow_up_at']): ?><div class="followup-next"><i class="fal fa-calendar-check mr-1"></i> Next follow-up: <?= e(date('M j, Y · g:i A',strtotime($followup['next_follow_up_at']))) ?></div><?php endif; ?>
                            <small><?= e(date('M j, Y · g:i A',strtotime($followup['followed_up_at']))) ?> · <?= e($followup['user_name']) ?></small>
                        </article>
                    <?php endforeach; ?>
                    <?php if($lead['notes']): ?><article class="timeline-item followup-entry"><div class="mb-1"><span class="followup-icon"><i class="fal fa-sticky-note"></i></span><strong>Initial lead notes</strong></div><p class="mb-2"><?= nl2br(e($lead['notes'])) ?></p><small><?= e($lead['created_at']?date('M j, Y · g:i A',strtotime($lead['created_at'])):'Lead creation') ?></small></article><?php endif; ?>
                    <?php if(!$lead['followups'] && !$lead['notes']): ?><div class="empty-state py-4"><i class="fal fa-comments-alt"></i><h3>No follow-ups yet</h3><p class="text-muted">Record the first call, email, meeting, message, or internal note.</p></div><?php endif; ?>
                </div>
            </div></div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel"><div class="panel-hdr"><h2>Qualification snapshot</h2></div><div class="panel-container show"><div class="panel-content">
            <div class="lead-detail-row"><span>Company size</span><strong><?= e($companySizeLabels[$lead['company_size_range']] ?? 'Not known') ?></strong></div>
            <div class="lead-detail-row"><span>Years in business</span><strong><?= e($businessYearLabels[$lead['years_in_business_range']] ?? 'Not known') ?></strong></div>
            <div class="lead-detail-row"><span>Business stage</span><strong><?= e($businessStageLabels[$lead['business_stage']] ?? 'Not known') ?></strong></div>
            <div class="lead-detail-row"><span>Budget range</span><strong><?= e($budgetLabels[$lead['budget_range']] ?? 'Not known') ?></strong></div>
            <div class="lead-detail-row"><span>Services</span><strong><?= e($lead['services_interested'] ?: 'None selected') ?></strong></div>
        </div></div></section>
        <section class="panel"><div class="panel-hdr"><h2>Discovery</h2><div class="panel-toolbar"><?php if($lead['consultations']): ?><a href="<?= e(url('discovery').'#consultation-'.(int)$lead['consultations'][0]['id']) ?>" class="btn btn-xs btn-outline-secondary mr-1"><i class="fal fa-eye mr-1"></i> View full details</a><?php endif; ?><?php if(!$isConverted): ?><a href="<?= e(url('discovery').'?lead_id='.(int)$lead['id'].'#modal-add') ?>" class="btn btn-xs btn-outline-primary">Start discovery</a><?php endif; ?></div></div><div class="panel-container show"><div class="panel-content">
            <?php if(!$lead['consultations']): ?><p class="text-muted mb-0">No discovery sessions recorded yet.</p><?php endif; ?>
            <?php foreach($lead['consultations'] as $consultation): ?><div class="border-bottom pb-3 mb-3"><strong class="d-block"><?= e($consultation['business_goals_data']['main_goal'] ?: 'Discovery consultation') ?></strong><small class="text-muted"><?= e(date('M j, Y · g:i A',strtotime($consultation['completed_at']))) ?><?= $consultation['completed_by_name']?' · '.e($consultation['completed_by_name']):'' ?></small><?php if($consultation['outcomes_data']): ?><div class="fs-xs mt-2"><?= e(implode(', ',$consultation['outcomes_data'])) ?></div><?php endif; ?></div><?php endforeach; ?>
        </div></div></section>
        <section class="panel"><div class="panel-hdr"><h2>Contact</h2></div><div class="panel-container show"><div class="panel-content">
            <div class="lead-detail-row"><span>Contact</span><strong><?= e(trim($lead['first_name'].' '.$lead['last_name'])) ?></strong></div>
            <div class="lead-detail-row"><span>Email</span><strong><a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a></strong></div>
            <div class="lead-detail-row"><span>Phone</span><strong><?= e($lead['phone'] ?: 'Not set') ?></strong></div>
            <div class="lead-detail-row"><span>Website</span><strong><?php if($lead['website']): ?><a href="<?= e($lead['website']) ?>" target="_blank" rel="noopener">Open website</a><?php else: ?>Not set<?php endif; ?></strong></div>
        </div></div></section>
    </div>
</div>

<?php if(!$isConverted): ?>
<div class="modal fade" id="modal-followup" tabindex="-1" role="dialog" aria-labelledby="followup-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content"><form method="post" action="<?= e(url('lead',['id'=>(int)$lead['id']])) ?>">
    <div class="modal-header"><div><span class="eyebrow mb-1">RELATIONSHIP HISTORY</span><h2 class="modal-title" id="followup-title">Add follow-up</h2></div><button class="close" type="button" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
    <div class="modal-body"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="add_lead_followup"><input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>"><div class="row">
        <div class="col-md-6"><div class="form-group"><label class="required">Follow-up type</label><select class="custom-select" name="type" required><option value="">Select type</option><?php foreach($followupTypes as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-md-6"><div class="form-group"><label>Outcome</label><select class="custom-select" name="outcome"><option value="">Select outcome</option><?php foreach($outcomeLabels as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-md-6"><div class="form-group"><label class="required">Followed up at</label><input class="form-control" type="datetime-local" name="followed_up_at" value="<?= e(date('Y-m-d\TH:i')) ?>" required></div></div>
        <div class="col-md-6"><div class="form-group"><label>Next follow-up</label><input class="form-control" type="datetime-local" name="next_follow_up_at" value="<?= e(date('Y-m-d\TH:i',strtotime('+2 days'))) ?>"></div></div>
        <div class="col-12"><div class="form-group"><label>Subject</label><input class="form-control" name="subject" maxlength="255" placeholder="Short summary of the conversation"></div></div>
        <div class="col-12"><div class="form-group mb-0"><label class="required">Notes</label><textarea class="form-control" name="notes" rows="5" required placeholder="What happened, what did the lead say, and what should happen next?"></textarea><small class="form-text text-muted">Recording the first external follow-up automatically moves a New lead to Contacted.</small></div></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fal fa-save mr-1"></i> Save follow-up</button></div>
</form></div></div></div>

<div class="modal fade" id="modal-edit-lead" tabindex="-1" role="dialog" aria-labelledby="edit-lead-title" aria-hidden="true"><div class="modal-dialog modal-xl" role="document"><div class="modal-content"><form method="post" action="<?= e(url('lead',['id'=>(int)$lead['id']])) ?>">
    <div class="modal-header"><div><span class="eyebrow mb-1">LEAD MANAGEMENT</span><h2 class="modal-title" id="edit-lead-title">Edit <?= e($lead['company_name']) ?></h2></div><button class="close" type="button" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
    <div class="modal-body"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="update_lead"><input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>"><input type="hidden" name="estimated_budget" value="<?= e($lead['estimated_budget']) ?>"><div class="alert alert-info"><i class="fal fa-sparkles mr-2"></i>Saving qualification changes recalculates the fit score. Conversion probability continues to come from the pipeline stage.</div><div class="row">
        <div class="col-md-6"><div class="form-group"><label class="required">First name</label><input class="form-control" name="first_name" value="<?= e($lead['first_name']) ?>" required></div></div>
        <div class="col-md-6"><div class="form-group"><label class="required">Last name</label><input class="form-control" name="last_name" value="<?= e($lead['last_name']) ?>" required></div></div>
        <div class="col-md-6"><div class="form-group"><label class="required">Company</label><input class="form-control" name="company_name" value="<?= e($lead['company_name']) ?>" required></div></div>
        <div class="col-md-6"><div class="form-group"><label class="required">Email</label><input class="form-control" type="email" name="email" value="<?= e($lead['email']) ?>" required></div></div>
        <div class="col-md-6"><div class="form-group"><label>Phone</label><input class="form-control" name="phone" value="<?= e($lead['phone']) ?>"></div></div>
        <div class="col-md-6"><div class="form-group"><label>Website</label><input class="form-control" type="url" name="website" value="<?= e($lead['website']) ?>"></div></div>
        <div class="col-md-6"><div class="form-group"><label>Industry</label><input class="form-control" name="industry" value="<?= e($lead['industry']) ?>"></div></div>
        <div class="col-md-6"><div class="form-group"><label class="required">Status</label><select class="custom-select" name="status" required><?php foreach($statusLabels as $value=>$label): ?><option value="<?= e($value) ?>" <?= $lead['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-12"><div class="form-group"><label>Lost reason</label><textarea class="form-control" name="lost_reason" rows="2" placeholder="Required when status is Lost. Explain the objection or reason clearly."><?= e($lead['lost_reason'] ?? '') ?></textarea></div></div>
        <div class="col-md-6"><div class="form-group"><label>Lead source</label><select class="custom-select" name="source_id"><option value="">Select lead source</option><?php foreach($options['sources'] as $source): ?><option value="<?= (int)$source['id'] ?>" <?= (int)$lead['source_id']===(int)$source['id']?'selected':'' ?>><?= e($source['name']) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-md-6"><div class="form-group"><label>Owner</label><select class="custom-select" name="assigned_employee_id"><option value="">Select owner</option><?php foreach($options['employees'] as $employee): ?><option value="<?= (int)$employee['id'] ?>" <?= (int)$lead['assigned_employee_id']===(int)$employee['id']?'selected':'' ?>><?= e($employee['name']) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-md-6"><div class="form-group"><label>Company size</label><select class="custom-select" name="company_size_range"><option value="">Unknown</option><?php foreach($companySizeLabels as $value=>$label): ?><option value="<?= e($value) ?>" <?= $lead['company_size_range']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-md-6"><div class="form-group"><label>Years in business</label><select class="custom-select" name="years_in_business_range"><option value="">Unknown</option><?php foreach($businessYearLabels as $value=>$label): ?><option value="<?= e($value) ?>" <?= $lead['years_in_business_range']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-md-6"><div class="form-group"><label>Business stage</label><select class="custom-select" name="business_stage"><option value="">Unknown</option><?php foreach($businessStageLabels as $value=>$label): ?><option value="<?= e($value) ?>" <?= $lead['business_stage']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-md-6"><div class="form-group"><label>Estimated budget range</label><select class="custom-select" name="budget_range"><option value="">Unknown</option><?php foreach($budgetLabels as $value=>$label): ?><option value="<?= e($value) ?>" <?= $lead['budget_range']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-md-6"><div class="form-group"><label>Next follow-up</label><input class="form-control" type="datetime-local" name="next_follow_up_at" value="<?= e($lead['next_follow_up_at']?date('Y-m-d\TH:i',strtotime($lead['next_follow_up_at'])):'') ?>"></div></div>
        <div class="col-12"><div class="form-group"><label>Services interested</label><div class="row border rounded p-2 mx-0" style="max-height:220px;overflow:auto"><?php foreach($options['services'] as $service): ?><div class="col-md-4"><div class="custom-control custom-checkbox py-1"><input class="custom-control-input" type="checkbox" id="edit_service_<?= (int)$service['id'] ?>" name="service_ids[]" value="<?= (int)$service['id'] ?>" <?= in_array((int)$service['id'],$lead['service_ids'],true)?'checked':'' ?>><label class="custom-control-label" for="edit_service_<?= (int)$service['id'] ?>"><?= e($service['name']) ?></label></div></div><?php endforeach; ?></div></div></div>
        <div class="col-12"><div class="form-group mb-0"><label>Background notes</label><textarea class="form-control" name="notes" rows="4"><?= e($lead['notes']) ?></textarea></div></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fal fa-save mr-1"></i> Save lead changes</button></div>
</form></div></div></div>

<?php foreach($lead['followups'] as $followup): ?>
<div class="modal fade" id="modal-correct-followup-<?= (int)$followup['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="correct-followup-title-<?= (int)$followup['id'] ?>" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content"><form method="post" action="<?= e(url('lead',['id'=>(int)$lead['id']])) ?>">
    <div class="modal-header"><div><span class="eyebrow mb-1">AUDITABLE CORRECTION</span><h2 class="modal-title" id="correct-followup-title-<?= (int)$followup['id'] ?>">Correct follow-up entry</h2></div><button class="close" type="button" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
    <div class="modal-body"><div class="alert alert-warning"><i class="fal fa-history mr-2"></i>The original values remain preserved in the audit log.</div><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="update_lead_followup"><input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>"><input type="hidden" name="followup_id" value="<?= (int)$followup['id'] ?>"><div class="row">
        <div class="col-md-6"><div class="form-group"><label class="required">Follow-up type</label><select class="custom-select" name="type" required><?php foreach($followupTypes as $value=>$label): ?><option value="<?= e($value) ?>" <?= $followup['type']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-md-6"><div class="form-group"><label>Outcome</label><select class="custom-select" name="outcome"><option value="">No outcome</option><?php foreach($outcomeLabels as $value=>$label): ?><option value="<?= e($value) ?>" <?= $followup['outcome']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
        <div class="col-md-6"><div class="form-group"><label class="required">Followed up at</label><input class="form-control" type="datetime-local" name="followed_up_at" value="<?= e(date('Y-m-d\TH:i',strtotime($followup['followed_up_at']))) ?>" required></div></div>
        <div class="col-md-6"><div class="form-group"><label>Next follow-up</label><input class="form-control" type="datetime-local" name="next_follow_up_at" value="<?= e($followup['next_follow_up_at']?date('Y-m-d\TH:i',strtotime($followup['next_follow_up_at'])):'') ?>"></div></div>
        <div class="col-12"><div class="form-group"><label>Subject</label><input class="form-control" name="subject" value="<?= e($followup['subject']) ?>" maxlength="255"></div></div>
        <div class="col-12"><div class="form-group mb-0"><label class="required">Notes</label><textarea class="form-control" name="notes" rows="5" required><?= e($followup['notes']) ?></textarea></div></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fal fa-save mr-1"></i> Save correction</button></div>
</form></div></div></div>
<?php endforeach; ?>
<?php endif; ?>

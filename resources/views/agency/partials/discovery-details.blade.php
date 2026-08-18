<?php
$goals = $consultation['business_goals_data'] ?? [];
$marketing = $consultation['marketing_snapshot_data'] ?? [];
$customer = $consultation['target_customer_data'] ?? [];
$detailSections = [
    ['Business goals', 'fal fa-bullseye-arrow', [
        'Main business goal'=>$goals['main_goal']??'',
        'Revenue goal'=>$goals['revenue_goal']??'',
        'Customer acquisition goal'=>$goals['acquisition_goal']??'',
        'Expansion plans'=>$goals['expansion_plans']??'',
        'Main challenges'=>$goals['challenges']??'',
        'Main competitors'=>$goals['competitors']??'',
    ]],
    ['Marketing snapshot', 'fal fa-chart-line', [
        'Current website'=>$marketing['website']??'',
        'Current social media'=>$marketing['social_media']??'',
        'Google Business Profile'=>$marketing['google_business']??'',
        'Current advertising'=>$marketing['advertising']??'',
        'SEO status'=>$marketing['seo_status']??'',
        'Marketing budget'=>$marketing['budget']??'',
        'Previous agency experience'=>$marketing['agency_experience']??'',
    ]],
    ['Target customer', 'fal fa-users', [
        'Target customer'=>$customer['target_customer']??'',
        'Geographic market'=>$customer['geographic_market']??'',
        'Demographics'=>$customer['demographics']??'',
        'Customer problems'=>$customer['problems']??'',
        'Why customers choose them'=>$customer['why_choose']??'',
    ]],
];
?>
<div class="discovery-detail-meta">
    <div><i class="fal fa-building"></i><span>Business</span><strong><?= e($discoveryContext['business_name']??'Not recorded') ?></strong></div>
    <div><i class="fal fa-user"></i><span>Contact</span><strong><?= e($discoveryContext['contact_name']??'Not recorded') ?></strong></div>
    <div><i class="fal fa-user-check"></i><span>Completed by</span><strong><?= e($consultation['completed_by_name'] ?: 'System user') ?></strong></div>
    <div><i class="fal fa-calendar-check"></i><span>Completed</span><strong><?= $consultation['completed_at']?e(date('M j, Y · g:i A',strtotime($consultation['completed_at']))):'Not recorded' ?></strong></div>
</div>

<?php foreach($detailSections as [$sectionTitle,$sectionIcon,$fields]): ?>
<section class="discovery-detail-section">
    <h3><i class="<?= e($sectionIcon) ?>"></i><?= e($sectionTitle) ?></h3>
    <div class="discovery-detail-grid">
        <?php foreach($fields as $label=>$value): ?><div class="discovery-detail-field"><span><?= e($label) ?></span><strong><?= $value!==''?nl2br(e((string)$value)):'Not provided' ?></strong></div><?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<div class="row">
    <div class="col-lg-6"><section class="discovery-detail-section h-100"><h3><i class="fal fa-exclamation-circle"></i>Current problems</h3><div class="discovery-detail-tags"><?php foreach($consultation['problems_data']??[] as $problem): ?><span><?= e($problem) ?></span><?php endforeach; ?><?php if(empty($consultation['problems_data'])): ?><em>None selected</em><?php endif; ?></div></section></div>
    <div class="col-lg-6 mt-3 mt-lg-0"><section class="discovery-detail-section h-100"><h3><i class="fal fa-flag-checkered"></i>Desired outcomes</h3><div class="discovery-detail-tags"><?php foreach($consultation['outcomes_data']??[] as $outcome): ?><span><?= e($outcome) ?></span><?php endforeach; ?><?php if(empty($consultation['outcomes_data'])): ?><em>None selected</em><?php endif; ?></div></section></div>
</div>

<section class="discovery-detail-section discovery-notes-section">
    <h3><i class="fal fa-sticky-note"></i>Consultation notes</h3>
    <p><?= $consultation['notes']?nl2br(e($consultation['notes'])):'No consultation notes were recorded.' ?></p>
</section>

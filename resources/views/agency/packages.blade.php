<?php $monthly=array_values(array_filter($packages,static fn($p)=>$p['package_type']==='monthly')); ?>
<div class="page-title-wrap">
    <div><span class="eyebrow">PRICING & SCOPE CONTROL</span><h1>Package Builder</h1><p>Create measurable offers with database-driven pricing, delivery cost, and margin.</p></div>
    <button class="btn btn-primary" data-toggle="modal" data-target="#modal-add"><i class="fal fa-plus mr-1"></i> New package</button>
</div>

<section class="package-grid mb-4">
<?php foreach($monthly as $package): $price=(float)($package['monthly_fee']?:$package['base_price']); ?>
    <article class="package-card <?= $package['featured']?'featured':'' ?>">
        <?php if($package['featured']): ?><span class="popular-flag">MOST POPULAR</span><?php endif; ?>
        <span class="eyebrow mb-1">MONTHLY RETAINER</span><h2 class="fs-xl mb-0"><?= e($package['name']) ?></h2>
        <div class="package-price"><?= e(money($price)) ?><small>/ month</small></div>
        <p class="text-muted fs-sm"><?= e($package['description']) ?></p>
        <div class="package-metrics">
            <div class="d-flex justify-content-between fs-sm mb-2"><span>Estimated internal cost</span><strong><?= e(money($package['estimated_cost'])) ?></strong></div>
            <div class="d-flex justify-content-between fs-sm mb-2"><span>Gross profit</span><strong><?= e(money($package['profit'])) ?></strong></div>
            <div class="d-flex justify-content-between fs-sm mb-1"><span>Estimated margin</span><strong class="<?= $package['margin']<40?'text-danger':'text-success' ?>"><?= e(number_format($package['margin'],1)) ?>%</strong></div>
            <div class="margin-bar"><span style="width:<?= e((string)max(0,min(100,$package['margin']))) ?>%"></span></div>
        </div>
        <ul class="package-services"><?php foreach(array_slice($package['services'],0,6) as $service): ?><li><i class="fal fa-check-circle"></i><?= e($service['name']) ?></li><?php endforeach; ?></ul>
        <div class="package-limits"><?php foreach($package['limits'] as $limit): ?><div class="d-flex justify-content-between mb-1"><span><?= e($limit['label']) ?></span><strong><?= e(number_format($limit['included_quantity'],0).' '.$limit['unit']) ?></strong></div><?php if((float)$limit['overage_price']>0): ?><div class="d-flex justify-content-between"><span>Overage</span><strong><?= e(money($limit['overage_price'])) ?></strong></div><?php endif; ?><?php endforeach; ?></div>
    </article>
<?php endforeach; ?>
</section>

<section class="panel">
    <div class="panel-hdr"><h2>Dynamic package comparison</h2><div class="panel-toolbar"><span class="badge badge-soft-success">Live database values</span></div></div>
    <div class="panel-container show"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Feature</th><?php foreach($monthly as $p): ?><th><?= e($p['name']) ?></th><?php endforeach; ?></tr></thead><tbody>
        <tr><td><strong>Monthly price</strong></td><?php foreach($monthly as $p): ?><td><strong><?= e(money($p['monthly_fee'])) ?></strong></td><?php endforeach; ?></tr>
        <?php
        $featureNames=[]; foreach($monthly as $p){foreach($p['services'] as $s){$featureNames[$s['name']]=true;}}
        foreach(array_keys($featureNames) as $feature): ?><tr><td><?= e($feature) ?></td><?php foreach($monthly as $p): $included=in_array($feature,array_column($p['services'],'name'),true); ?><td><?= $included?'<i class="fal fa-check-circle text-success"></i> Included':'<span class="text-muted">—</span>' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
        <tr><td><strong>On-site visits</strong></td><?php foreach($monthly as $p): $limit=current(array_filter($p['limits'],static fn($l)=>$l['limit_key']==='content_visits')); ?><td><?= $limit?e(number_format($limit['included_quantity'],0).' / month'):'—' ?></td><?php endforeach; ?></tr>
        <tr><td><strong>Hours per visit</strong></td><?php foreach($monthly as $p): $limit=current(array_filter($p['limits'],static fn($l)=>$l['limit_key']==='visit_duration')); ?><td><?= $limit?e(number_format($limit['included_quantity'],0).' hours'):'—' ?></td><?php endforeach; ?></tr>
    </tbody></table></div></div>
</section>

<section class="panel mt-4">
    <div class="panel-hdr"><h2>All package families</h2></div><div class="panel-container show"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Package</th><th>Type</th><th>Price</th><th>Effective</th><th>Est. cost</th><th>Margin</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach($packages as $package): $price=(float)($package['monthly_fee']?:$package['base_price']); ?><tr><td><strong><?= e($package['name']) ?></strong></td><td><?= e(ucwords(str_replace('_',' ',$package['package_type']))) ?></td><td><strong><?= $price?e(money($price)): 'Custom quote' ?></strong><?= $package['monthly_fee']?'<small class="text-muted"> / mo</small>':'' ?></td><td><?= e(date('M j, Y',strtotime($package['effective_from']))) ?></td><td><?= e(money($package['estimated_cost'])) ?></td><td><span class="badge badge-soft-<?= $package['margin']>=40?'success':'warning' ?>"><?= e(number_format($package['margin'],1)) ?>%</span></td><td><span class="badge badge-soft-<?= $package['active']?'success':'secondary' ?>"><?= $package['active']?'Active':'Inactive' ?></span></td><td class="text-right"><button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#modal-price" data-price-edit data-id="<?= (int)$package['id'] ?>" data-name="<?= e($package['name']) ?>" data-price="<?= e((string)$price) ?>" data-setup="<?= e($package['setup_fee']) ?>">Change price</button></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</section>

<div class="modal fade" id="modal-add" tabindex="-1" role="dialog" aria-labelledby="package-modal-title" aria-hidden="true"><div class="modal-dialog modal-xl" role="document"><div class="modal-content"><form method="post" action="<?= e(url('packages')) ?>">
<div class="modal-header"><div><span class="eyebrow mb-1">PACKAGE ECONOMICS</span><h2 class="modal-title fs-xl" id="package-modal-title">Build a package</h2></div><button class="close" type="button" data-dismiss="modal"><span>&times;</span></button></div>
<div class="modal-body"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="create_package"><div class="row">
    <div class="col-lg-8"><div class="row">
        <div class="col-md-6"><div class="form-group"><label class="required">Package name</label><input class="form-control" name="name" required></div></div>
        <div class="col-md-6"><div class="form-group"><label class="required">Package type</label><select class="custom-select" name="package_type" required><option value="monthly">Monthly retainer</option><option value="new_business">New Business Launch</option><option value="business_refresh">Existing Business Refresh</option><option value="website">Website</option><option value="custom">Custom</option></select></div></div>
        <div class="col-md-4"><div class="form-group"><label class="required">Price</label><input class="form-control" name="price" type="number" min="0" step="0.01" data-package-calc required></div></div>
        <div class="col-md-4"><div class="form-group"><label>Setup fee</label><input class="form-control" name="setup_fee" type="number" min="0" step="0.01" value="0"></div></div>
        <div class="col-md-4"><div class="form-group"><label>Deposit %</label><input class="form-control" name="deposit_percent" type="number" min="0" max="100" step="0.1" value="30"></div></div>
        <div class="col-md-4"><div class="form-group"><label>Included visits</label><input class="form-control" name="included_visits" type="number" min="0" value="0"></div></div>
        <div class="col-md-4"><div class="form-group"><label>Hours per visit</label><input class="form-control" name="hours_per_visit" type="number" min="0" step="0.5" value="0"></div></div>
        <div class="col-md-4"><div class="form-group"><label>Additional visit price</label><input class="form-control" name="additional_visit_price" type="number" min="0" value="0"></div></div>
        <div class="col-12"><div class="form-group"><label>Description / scope</label><textarea class="form-control" name="description" rows="3" placeholder="Describe the intended outcome and measurable scope."></textarea><small class="form-text text-warning"><i class="fal fa-ruler-combined mr-1"></i>“Unlimited” is blocked. Use measurable limits for pages, revisions, products, hours, and visits.</small></div></div>
        <div class="col-12"><label>Included services</label><div class="row border rounded p-2 mx-0" style="max-height:240px;overflow:auto"><?php foreach($options['services'] as $service): ?><div class="col-md-6"><div class="custom-control custom-checkbox py-1"><input type="checkbox" class="custom-control-input" id="service_<?= (int)$service['id'] ?>" name="service_ids[]" value="<?= (int)$service['id'] ?>" data-cost="<?= e($service['cost_estimate']) ?>" data-hours="<?= e($service['estimated_hours']) ?>" data-package-calc><label class="custom-control-label" for="service_<?= (int)$service['id'] ?>"><?= e($service['name']) ?> <small class="text-muted">· <?= e(money($service['cost_estimate'])) ?> cost</small></label></div></div><?php endforeach; ?></div></div>
        <input type="hidden" name="effective_from" value="<?= e(date('Y-m-d')) ?>"><input type="hidden" name="discount_percent" value="0"><input type="hidden" name="tax_percent" value="0">
    </div></div>
    <div class="col-lg-4"><div class="package-metrics p-3"><span class="eyebrow">INTERNAL ONLY</span><h3 class="fs-lg">Profitability preview</h3><div class="d-flex justify-content-between py-2 border-bottom"><span>Estimated cost</span><strong id="calc-cost">$0</strong></div><div class="d-flex justify-content-between py-2 border-bottom"><span>Estimated hours</span><strong id="calc-hours">0h</strong></div><div class="d-flex justify-content-between py-2 border-bottom"><span>Gross profit</span><strong id="calc-profit">$0</strong></div><div class="d-flex justify-content-between py-2"><span>Margin</span><strong id="calc-margin">0%</strong></div><p class="fs-xs text-muted mt-3 mb-0">Internal costs never appear in client-facing proposals or invoices.</p></div></div>
</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save package</button></div>
</form></div></div></div>

<div class="modal fade" id="modal-price" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post" action="<?= e(url('packages')) ?>"><div class="modal-header"><div><span class="eyebrow mb-1">VERSIONED PRICING</span><h2 class="modal-title fs-xl">Change price · <span id="price_package_name"></span></h2></div><button class="close" type="button" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="update_package_pricing"><input type="hidden" name="package_id" id="price_package_id"><div class="form-group"><label class="required">New price</label><input class="form-control" id="price_value" name="price" type="number" min="0" step="0.01" required></div><div class="form-group"><label>Setup fee</label><input class="form-control" id="price_setup" name="setup_fee" type="number" min="0" step="0.01" value="0"></div><div class="row"><div class="col-4"><div class="form-group"><label>Discount %</label><input class="form-control" name="discount_percent" type="number" min="0" max="100" value="0"></div></div><div class="col-4"><div class="form-group"><label>Tax %</label><input class="form-control" name="tax_percent" type="number" min="0" value="0"></div></div><div class="col-4"><div class="form-group"><label>Deposit %</label><input class="form-control" name="deposit_percent" type="number" min="0" max="100" value="30"></div></div></div><div class="form-group"><label class="required">Effective date</label><input class="form-control" name="effective_from" type="date" value="<?= e(date('Y-m-d')) ?>" required></div><div class="alert alert-info mb-0"><i class="fal fa-history mr-2"></i>The prior price is preserved for historical reporting. Active client agreements keep their agreed subscription price.</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save new price</button></div></form></div></div></div>

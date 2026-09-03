<?php
$permissionSections = [];
foreach ($permissions as $permission) {
    $sectionKey = $permission['group_slug'] ?: 'overview';
    if (! isset($permissionSections[$sectionKey])) {
        $permissionSections[$sectionKey] = [
            'label' => $permission['group_label'] ?: 'Overview',
            'icon' => $permission['group_icon'] ?: 'fal fa-home',
            'permissions' => [],
        ];
    }
    $permissionSections[$sectionKey]['permissions'][] = $permission;
}
$editableRoles = array_values(array_filter($roles, static fn (array $role): bool => ! $role['protected']));
$firstEditableRoleId = $editableRoles[0]['id'] ?? null;
$openStageCount = count(array_filter($stages, static fn (array $stage): bool => ! $stage['is_closed']));
$invoiceProfile = $invoiceProfile ?? [];
$bankAccounts = $bankAccounts ?? [];
?>
<div class="page-title-wrap">
    <div>
        <span class="eyebrow">ACCESS & CONFIGURATION</span>
        <h1>Settings</h1>
        <p>Create roles, assign individual module access, and manage user-specific exceptions.</p>
    </div>
    <div>
        <a href="<?= e(url('team')) ?>#modal-add" class="btn btn-primary mr-2"><i class="fal fa-user-plus mr-1"></i> Create user</a>
        <a href="<?= e(url('audit')) ?>" class="btn btn-outline-secondary"><i class="fal fa-history mr-1"></i> Audit log</a>
    </div>
</div>

<div class="row">
    <div class="col-md-4"><div class="access-summary-card"><i class="fal fa-user-shield"></i><div><strong><?= count($editableRoles) ?></strong><span>editable roles</span></div></div></div>
    <div class="col-md-4"><div class="access-summary-card"><i class="fal fa-users-cog"></i><div><strong><?= count($systemUsers) ?></strong><span>system users</span></div></div></div>
    <div class="col-md-4"><div class="access-summary-card"><i class="fal fa-list-alt"></i><div><strong><?= count($navigationItems) ?></strong><span>database sidebar items</span></div></div></div>
</div>

<section class="panel mt-3 invoice-settings-panel" id="invoice-settings">
    <div class="panel-hdr"><div><h2>Invoice identity & payment details</h2><p class="mb-0 text-muted fs-sm">These details are printed on every invoice and PDF.</p></div><div class="panel-toolbar"><span class="badge badge-soft-primary"><i class="fal fa-file-invoice-dollar mr-1"></i>Billing configuration</span></div></div>
    <div class="panel-container show"><div class="panel-content">
        <form method="post" action="<?= e(url('settings')) ?>" enctype="multipart/form-data" class="invoice-profile-form">
            <input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="save_invoice_profile">
            <div class="invoice-settings-heading"><i class="fal fa-building"></i><div><strong>Agency profile</strong><span>Brand, contact, tax, address, default terms, and authorized signature.</span></div></div>
            <div class="row">
                <div class="col-md-4"><div class="form-group"><label class="required" for="invoice_agency_name">Agency name</label><input class="form-control" id="invoice_agency_name" name="agency_name" required value="<?= e($invoiceProfile['agency_name']??'360 Creative Agency') ?>"></div></div>
                <div class="col-md-4"><div class="form-group"><label for="invoice_email">Invoice email</label><input class="form-control" id="invoice_email" type="email" name="email" value="<?= e($invoiceProfile['email']??'') ?>"></div></div>
                <div class="col-md-4"><div class="form-group"><label for="invoice_phone">Phone</label><input class="form-control" id="invoice_phone" name="phone" value="<?= e($invoiceProfile['phone']??'') ?>"></div></div>
                <div class="col-md-6"><div class="form-group"><label for="invoice_address_1">Address line 1</label><input class="form-control" id="invoice_address_1" name="address_line_1" value="<?= e($invoiceProfile['address_line_1']??'') ?>"></div></div>
                <div class="col-md-6"><div class="form-group"><label for="invoice_address_2">Address line 2</label><input class="form-control" id="invoice_address_2" name="address_line_2" value="<?= e($invoiceProfile['address_line_2']??'') ?>"></div></div>
                <div class="col-md-3"><div class="form-group"><label for="invoice_city">City</label><input class="form-control" id="invoice_city" name="city" value="<?= e($invoiceProfile['city']??'') ?>"></div></div>
                <div class="col-md-3"><div class="form-group"><label for="invoice_province">Province / state</label><input class="form-control" id="invoice_province" name="province" value="<?= e($invoiceProfile['province']??'') ?>"></div></div>
                <div class="col-md-3"><div class="form-group"><label for="invoice_postal">Postal code</label><input class="form-control" id="invoice_postal" name="postal_code" value="<?= e($invoiceProfile['postal_code']??'') ?>"></div></div>
                <div class="col-md-3"><div class="form-group"><label for="invoice_country">Country</label><input class="form-control" id="invoice_country" name="country" value="<?= e($invoiceProfile['country']??'Canada') ?>"></div></div>
                <div class="col-md-4"><div class="form-group"><label for="invoice_website">Website</label><input class="form-control" id="invoice_website" name="website" value="<?= e($invoiceProfile['website']??'') ?>"></div></div>
                <div class="col-md-4"><div class="form-group"><label for="invoice_tax_number">Tax / HST number</label><input class="form-control" id="invoice_tax_number" name="tax_number" value="<?= e($invoiceProfile['tax_number']??'') ?>"></div></div>
                <div class="col-md-4"><div class="form-group"><label for="invoice_signature_image">Signature image</label><input class="form-control-file" id="invoice_signature_image" type="file" name="signature_image" accept=".jpg,.jpeg,.png,.webp"><small class="form-text text-muted">JPG, PNG, or WebP · max 5 MB<?= !empty($invoiceProfile['signature_path'])?' · Signature saved':'' ?></small></div></div>
                <div class="col-md-6"><div class="form-group"><label for="invoice_signatory">Authorized signatory</label><input class="form-control" id="invoice_signatory" name="authorized_signatory_name" value="<?= e($invoiceProfile['authorized_signatory_name']??'') ?>" placeholder="Full name"></div></div>
                <div class="col-md-6"><div class="form-group"><label for="invoice_signatory_title">Signatory title</label><input class="form-control" id="invoice_signatory_title" name="authorized_signatory_title" value="<?= e($invoiceProfile['authorized_signatory_title']??'') ?>" placeholder="CEO / Authorized representative"></div></div>
                <div class="col-12"><div class="form-group"><label for="invoice_default_terms">Default payment terms</label><textarea class="form-control" id="invoice_default_terms" name="default_payment_terms" rows="3"><?= e($invoiceProfile['default_payment_terms']??'') ?></textarea></div></div>
            </div><div class="text-right"><button class="btn btn-primary"><i class="fal fa-save mr-1"></i>Save invoice profile</button></div>
        </form>
        <hr class="my-4">
        <div class="invoice-settings-heading"><i class="fal fa-university"></i><div><strong>Bank accounts</strong><span>Select any active account while creating an invoice. The default account is preselected.</span></div></div>
        <div class="bank-account-grid">
            <?php foreach($bankAccounts as $bank): ?><form method="post" action="<?= e(url('settings')) ?>" class="bank-account-card"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="save_bank_account"><input type="hidden" name="bank_account_id" value="<?= (int)$bank['id'] ?>"><header><div><span><?= e($bank['account_name']) ?></span><strong><?= e($bank['bank_name']) ?></strong></div><div><?php if($bank['is_default']): ?><span class="badge badge-soft-success">Default</span><?php endif; ?> <span class="badge badge-soft-<?= $bank['active']?'primary':'secondary' ?>"><?= $bank['active']?'Active':'Inactive' ?></span></div></header><div class="row"><div class="col-md-6"><label>Account label<input class="form-control form-control-sm" name="account_name" required value="<?= e($bank['account_name']) ?>"></label></div><div class="col-md-6"><label>Bank name<input class="form-control form-control-sm" name="bank_name" required value="<?= e($bank['bank_name']) ?>"></label></div><div class="col-md-6"><label>Account holder<input class="form-control form-control-sm" name="account_holder" value="<?= e($bank['account_holder']??'') ?>"></label></div><div class="col-md-6"><label>Account number<input class="form-control form-control-sm" name="account_number" value="<?= e($bank['account_number']??'') ?>"></label></div><div class="col-md-4"><label>Transit<input class="form-control form-control-sm" name="transit_number" value="<?= e($bank['transit_number']??'') ?>"></label></div><div class="col-md-4"><label>Institution<input class="form-control form-control-sm" name="institution_number" value="<?= e($bank['institution_number']??'') ?>"></label></div><div class="col-md-4"><label>Currency<input class="form-control form-control-sm" name="currency" value="<?= e($bank['currency']??'CAD') ?>"></label></div><div class="col-md-6"><label>SWIFT<input class="form-control form-control-sm" name="swift_code" value="<?= e($bank['swift_code']??'') ?>"></label></div><div class="col-md-6"><label>IBAN<input class="form-control form-control-sm" name="iban" value="<?= e($bank['iban']??'') ?>"></label></div><div class="col-12"><label>Payment instructions<textarea class="form-control form-control-sm" name="payment_instructions" rows="2"><?= e($bank['payment_instructions']??'') ?></textarea></label></div></div><footer><label><input type="checkbox" name="active" value="1" <?= $bank['active']?'checked':'' ?>> Active</label><label><input type="checkbox" name="is_default" value="1" <?= $bank['is_default']?'checked':'' ?>> Default</label><button class="btn btn-sm btn-outline-primary"><i class="fal fa-save mr-1"></i>Save</button></footer></form><?php endforeach; ?>
            <form method="post" action="<?= e(url('settings')) ?>" class="bank-account-card add-bank-account"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="save_bank_account"><header><div><span>NEW DEPOSIT ACCOUNT</span><strong>Add bank account</strong></div><i class="fal fa-plus-circle"></i></header><div class="row"><div class="col-md-6"><label>Account label<input class="form-control form-control-sm" name="account_name" required placeholder="CAD operating"></label></div><div class="col-md-6"><label>Bank name<input class="form-control form-control-sm" name="bank_name" required></label></div><div class="col-md-6"><label>Account holder<input class="form-control form-control-sm" name="account_holder"></label></div><div class="col-md-6"><label>Account number<input class="form-control form-control-sm" name="account_number"></label></div><div class="col-md-4"><label>Transit<input class="form-control form-control-sm" name="transit_number"></label></div><div class="col-md-4"><label>Institution<input class="form-control form-control-sm" name="institution_number"></label></div><div class="col-md-4"><label>Currency<input class="form-control form-control-sm" name="currency" value="CAD"></label></div><div class="col-md-6"><label>SWIFT<input class="form-control form-control-sm" name="swift_code"></label></div><div class="col-md-6"><label>IBAN<input class="form-control form-control-sm" name="iban"></label></div><div class="col-12"><label>Payment instructions<textarea class="form-control form-control-sm" name="payment_instructions" rows="2"></textarea></label></div></div><footer><label><input type="checkbox" name="active" value="1" checked> Active</label><label><input type="checkbox" name="is_default" value="1"> Default</label><button class="btn btn-sm btn-primary"><i class="fal fa-plus mr-1"></i>Add account</button></footer></form>
        </div>
    </div></div>
</section>

<section class="panel mt-3" id="create-role">
    <div class="panel-hdr"><h2>Create a role</h2><div class="panel-toolbar"><span class="badge badge-soft-success">Available immediately</span></div></div>
    <div class="panel-container show"><div class="panel-content">
        <form method="post" action="<?= e(url('settings')) ?>">
            <input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>">
            <input type="hidden" name="action" value="create_role">
            <div class="row">
                <div class="col-md-4"><div class="form-group"><label class="required">Role name</label><input class="form-control" name="name" required placeholder="Content Manager"></div></div>
                <div class="col-md-4"><div class="form-group"><label class="required">Role key</label><input class="form-control" name="slug" required pattern="[a-z][a-z0-9_]{2,49}" placeholder="content_manager"><small class="form-text text-muted">Lowercase letters, numbers, and underscores.</small></div></div>
                <div class="col-md-4"><div class="form-group"><label>Description</label><input class="form-control" name="description" placeholder="What this role is responsible for"></div></div>
            </div>
            <label class="font-weight-bold">Initial sidebar access</label>
            <div class="permission-sections mb-3">
                <?php foreach($permissionSections as $section): ?>
                    <section class="permission-section">
                        <h3><i class="<?= e($section['icon']) ?>"></i><?= e($section['label']) ?></h3>
                        <div class="permission-section-items">
                            <?php foreach($section['permissions'] as $permission): ?>
                                <div class="custom-control custom-checkbox">
                                    <input class="custom-control-input" type="checkbox" id="new_role_permission_<?= (int)$permission['id'] ?>" name="permission_ids[]" value="<?= (int)$permission['id'] ?>">
                                    <label class="custom-control-label" for="new_role_permission_<?= (int)$permission['id'] ?>"><?= e($permission['name']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-primary"><i class="fal fa-plus mr-1"></i> Create role</button>
        </form>
    </div></div>
</section>

<section class="panel mt-3">
    <div class="panel-hdr"><h2>Role permission matrix</h2><div class="panel-toolbar"><span class="badge badge-soft-primary">One permission per sidebar item</span></div></div>
    <div class="panel-container show"><div class="panel-content">
        <div class="accordion" id="role-permissions">
            <?php foreach($roles as $index=>$role): ?>
                <div class="card border mb-2">
                    <div class="card-header bg-white" id="role-head-<?= (int)$role['id'] ?>">
                        <button class="btn btn-link btn-block text-left d-flex align-items-center" type="button" data-toggle="collapse" data-target="#role-body-<?= (int)$role['id'] ?>">
                            <i class="fal fa-user-shield mr-2"></i><strong><?= e($role['name']) ?></strong>
                            <?php if($role['protected']): ?><span class="badge badge-soft-warning ml-auto mr-2"><i class="fal fa-lock mr-1"></i>Protected</span><?php endif; ?>
                            <span class="badge badge-soft-primary <?= $role['protected']?'':'ml-auto' ?> mr-2"><?= count($role['permission_ids']) ?> modules</span><i class="fal fa-chevron-down"></i>
                        </button>
                    </div>
                    <div id="role-body-<?= (int)$role['id'] ?>" class="collapse <?= (int)$role['id']===(int)$firstEditableRoleId?'show':'' ?>" data-parent="#role-permissions">
                        <div class="card-body">
                            <?php if($role['protected']): ?>
                                <div class="protected-role-notice">
                                    <i class="fal fa-lock"></i>
                                    <div><strong>Protected system role</strong><span>The Admin role remains visible and assignable, but its details and permissions cannot be changed.</span></div>
                                </div>
                            <?php else: ?>
                            <form method="post" action="<?= e(url('settings')) ?>">
                                <input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>">
                                <input type="hidden" name="action" value="update_role">
                                <input type="hidden" name="role_id" value="<?= (int)$role['id'] ?>">
                                <div class="role-editor-fields">
                                    <div class="role-editor-heading">
                                        <i class="fal fa-pen"></i>
                                        <div><strong>Edit role details</strong><span>Update the display name, system key, description, and access together.</span></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-4"><div class="form-group mb-lg-0"><label class="required" for="role_name_<?= (int)$role['id'] ?>">Role name</label><input class="form-control" id="role_name_<?= (int)$role['id'] ?>" name="name" value="<?= e($role['name']) ?>" required maxlength="80"></div></div>
                                        <div class="col-lg-4"><div class="form-group mb-lg-0"><label class="required" for="role_slug_<?= (int)$role['id'] ?>">Role key</label><input class="form-control" id="role_slug_<?= (int)$role['id'] ?>" name="slug" value="<?= e($role['slug']) ?>" required pattern="[a-z][a-z0-9_]{2,49}" maxlength="50"><small class="form-text text-muted">Lowercase letters, numbers, and underscores.</small></div></div>
                                        <div class="col-lg-4"><div class="form-group mb-0"><label for="role_description_<?= (int)$role['id'] ?>">Description</label><textarea class="form-control" id="role_description_<?= (int)$role['id'] ?>" name="description" rows="2" maxlength="500"><?= e($role['description'] ?? '') ?></textarea></div></div>
                                    </div>
                                </div>
                                <label class="font-weight-bold d-block mb-3">Sidebar access</label>
                                <div class="permission-sections">
                                    <?php foreach($permissionSections as $section): ?>
                                        <section class="permission-section">
                                            <h3><i class="<?= e($section['icon']) ?>"></i><?= e($section['label']) ?></h3>
                                            <div class="permission-section-items">
                                                <?php foreach($section['permissions'] as $permission): ?>
                                                    <div class="custom-control custom-checkbox">
                                                        <input class="custom-control-input" type="checkbox" id="rp_<?= (int)$role['id'] ?>_<?= (int)$permission['id'] ?>" name="permission_ids[]" value="<?= (int)$permission['id'] ?>" <?= in_array((int)$permission['id'],$role['permission_ids'],true)?'checked':'' ?>>
                                                        <label class="custom-control-label" for="rp_<?= (int)$role['id'] ?>_<?= (int)$permission['id'] ?>"><?= e($permission['name']) ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </section>
                                    <?php endforeach; ?>
                                </div>
                                <div class="role-editor-actions"><span><i class="fal fa-shield-check mr-1"></i>Changes apply to every user assigned to this role.</span><button class="btn btn-primary"><i class="fal fa-save mr-1"></i> Save role changes</button></div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div></div>
</section>

<?php if(false): ?>
<section class="panel mt-3" id="user-access">
    <div class="panel-hdr"><h2>User-specific access</h2><div class="panel-toolbar"><span class="badge badge-soft-warning">Overrides role defaults</span></div></div>
    <div class="panel-container show"><div class="panel-content">
        <p class="text-muted">Use <strong>Inherit</strong> for the role default, or explicitly allow/deny one module for this user.</p>
        <div class="accordion" id="user-access-accordion">
            <?php foreach($systemUsers as $index=>$systemUser): ?>
                <div class="card border mb-2">
                    <div class="card-header bg-white" id="user-head-<?= (int)$systemUser['id'] ?>">
                        <button class="btn btn-link btn-block text-left d-flex align-items-center" type="button" data-toggle="collapse" data-target="#user-body-<?= (int)$systemUser['id'] ?>">
                            <span class="avatar-sm mr-2"><?= e(strtoupper(substr($systemUser['name'],0,1))) ?></span>
                            <span><strong class="d-block"><?= e($systemUser['name']) ?></strong><small class="text-muted"><?= e($systemUser['email']) ?></small></span>
                            <span class="badge badge-soft-primary ml-auto mr-2"><?= e($systemUser['role_name']) ?></span>
                            <span class="badge badge-soft-<?= $systemUser['status']==='active'?'success':'danger' ?> mr-2"><?= e($systemUser['status']) ?></span>
                            <i class="fal fa-chevron-down"></i>
                        </button>
                    </div>
                    <div id="user-body-<?= (int)$systemUser['id'] ?>" class="collapse" data-parent="#user-access-accordion">
                        <div class="card-body">
                            <?php if($systemUser['role_slug']==='super_admin'): ?>
                                <div class="alert alert-info mb-0"><i class="fal fa-shield-check mr-2"></i>The Super Admin always has complete access and cannot be restricted.</div>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('settings')) ?>">
                                    <input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>">
                                    <input type="hidden" name="action" value="update_user_access">
                                    <input type="hidden" name="user_id" value="<?= (int)$systemUser['id'] ?>">
                                    <div class="row">
                                        <div class="col-md-4"><div class="form-group"><label>Role</label><select class="custom-select" name="role_id"><?php foreach($options['roles'] as $roleOption): ?><option value="<?= (int)$roleOption['id'] ?>" <?= (int)$roleOption['id']===(int)$systemUser['role_id']?'selected':'' ?>><?= e($roleOption['name']) ?></option><?php endforeach; ?></select></div></div>
                                        <div class="col-md-4"><div class="form-group"><label>Status</label><select class="custom-select" name="status"><option value="active" <?= $systemUser['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $systemUser['status']==='inactive'?'selected':'' ?>>Inactive</option></select></div></div>
                                        <div class="col-md-4"><div class="form-group"><label>Reset password</label><input class="form-control" type="password" name="password" minlength="10" placeholder="Leave blank to keep current"></div></div>
                                    </div>
                                    <div class="permission-sections user-permission-sections">
                                        <?php foreach($permissionSections as $section): ?>
                                            <section class="permission-section">
                                                <h3><i class="<?= e($section['icon']) ?>"></i><?= e($section['label']) ?></h3>
                                                <div class="permission-section-items">
                                                    <?php foreach($section['permissions'] as $permission): $override=$systemUser['permission_overrides'][(int)$permission['id']]??null; ?>
                                                        <div class="override-control">
                                                            <label for="up_<?= (int)$systemUser['id'] ?>_<?= (int)$permission['id'] ?>"><?= e(str_replace('Access ','',$permission['name'])) ?></label>
                                                            <select class="custom-select custom-select-sm" id="up_<?= (int)$systemUser['id'] ?>_<?= (int)$permission['id'] ?>" name="permission_overrides[<?= (int)$permission['id'] ?>]">
                                                                <option value="inherit" <?= $override===null?'selected':'' ?>>Inherit</option>
                                                                <option value="allow" <?= $override===true?'selected':'' ?>>Allow</option>
                                                                <option value="deny" <?= $override===false?'selected':'' ?>>Deny</option>
                                                            </select>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </section>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-right mt-3"><button class="btn btn-primary btn-sm">Save user access</button></div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div></div>
</section>

<section class="panel mt-3">
    <div class="panel-hdr"><h2>Dynamic sidebar registry</h2><div class="panel-toolbar"><span class="badge badge-soft-success">Loaded from MySQL</span></div></div>
    <div class="panel-container show"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Order</th><th>Section</th><th>Sidebar item</th><th>Route</th><th>Required permission</th><th>Status</th></tr></thead>
        <tbody><?php foreach($navigationItems as $navItem): ?><tr><td><?= (int)$navItem['position'] ?></td><td><?= e($navItem['group_label'] ?: 'Top level') ?></td><td><i class="<?= e($navItem['icon']) ?> text-primary mr-2"></i><strong><?= e($navItem['label']) ?></strong></td><td><code>/<?= e($navItem['route']) ?></code></td><td><code><?= e($navItem['permission_slug']) ?></code></td><td><span class="badge badge-soft-<?= $navItem['active']?'success':'secondary' ?>"><?= $navItem['active']?'Active':'Hidden' ?></span></td></tr><?php endforeach; ?></tbody>
    </table></div></div>
</section>

<?php endif; ?>

<?php if (false): // Pricing is managed in Quote Settings; legacy pipeline/source controls are intentionally hidden. ?>
<section class="panel mt-3" id="service-pricing">
    <div class="panel-hdr">
        <h2>Service pricing controls</h2>
        <div class="panel-toolbar"><a href="<?= e(url('services')) ?>#modal-add" class="btn btn-sm btn-primary"><i class="fal fa-plus mr-1"></i> Add service</a></div>
    </div>
    <div class="panel-container show">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Service</th><th>Category</th><th>Pricing model</th><th>Client price</th><th>Internal cost</th><th>Est. hours</th><th>Status</th><th class="text-right">Action</th></tr></thead>
                <tbody>
                    <?php foreach($services as $service): ?>
                        <tr>
                            <td><strong><?= e($service['name']) ?></strong></td>
                            <td><?= e($service['category_name']) ?></td>
                            <td><?= e(ucwords(str_replace('_',' ',$service['pricing_type']))) ?></td>
                            <td><?= e(money($service['default_price'])) ?></td>
                            <td><?= e(money($service['cost_estimate'])) ?></td>
                            <td><?= e(number_format($service['estimated_hours'],1)) ?>h</td>
                            <td><span class="badge badge-soft-<?= $service['active']?'success':'secondary' ?>"><?= $service['active']?'Active':'Inactive' ?></span></td>
                            <td class="text-right"><button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#edit-service-<?= (int)$service['id'] ?>" aria-label="Edit <?= e($service['name']) ?>"><i class="fal fa-pen mr-1"></i> Edit</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php foreach($services as $service): ?>
    <div class="modal fade" id="edit-service-<?= (int)$service['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="edit-service-title-<?= (int)$service['id'] ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="post" action="<?= e(url('settings')) ?>">
                    <input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>">
                    <input type="hidden" name="action" value="update_service">
                    <input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>">
                    <div class="modal-header">
                        <div><span class="eyebrow">SERVICE PRICING</span><h2 class="modal-title" id="edit-service-title-<?= (int)$service['id'] ?>">Edit <?= e($service['name']) ?></h2></div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label class="required" for="service_name_<?= (int)$service['id'] ?>">Service name</label><input class="form-control" id="service_name_<?= (int)$service['id'] ?>" name="name" value="<?= e($service['name']) ?>" required maxlength="120"></div></div>
                            <div class="col-md-6"><div class="form-group"><label class="required" for="service_category_<?= (int)$service['id'] ?>">Category</label><select class="custom-select" id="service_category_<?= (int)$service['id'] ?>" name="category_id" required><?php foreach($options['categories'] as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)$category['id']===(int)$service['category_id']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div></div>
                            <div class="col-md-6"><div class="form-group"><label class="required" for="service_pricing_<?= (int)$service['id'] ?>">Pricing model</label><select class="custom-select" id="service_pricing_<?= (int)$service['id'] ?>" name="pricing_type" required><?php foreach(['one_time'=>'One-time','monthly'=>'Monthly','hourly'=>'Hourly','per_visit'=>'Per visit','per_project'=>'Per project','custom'=>'Custom quote'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $service['pricing_type']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
                            <div class="col-md-6"><div class="form-group"><label for="service_price_<?= (int)$service['id'] ?>">Client price</label><input class="form-control" id="service_price_<?= (int)$service['id'] ?>" type="number" name="default_price" value="<?= e($service['default_price']) ?>" min="0" step="0.01" required></div></div>
                            <div class="col-md-6"><div class="form-group"><label for="service_cost_<?= (int)$service['id'] ?>">Internal cost</label><input class="form-control" id="service_cost_<?= (int)$service['id'] ?>" type="number" name="cost_estimate" value="<?= e($service['cost_estimate']) ?>" min="0" step="0.01" required></div></div>
                            <div class="col-md-6"><div class="form-group"><label for="service_hours_<?= (int)$service['id'] ?>">Estimated hours</label><input class="form-control" id="service_hours_<?= (int)$service['id'] ?>" type="number" name="estimated_hours" value="<?= e($service['estimated_hours']) ?>" min="0" step="0.1" required></div></div>
                            <div class="col-md-6"><div class="form-group"><label for="service_status_<?= (int)$service['id'] ?>">Status</label><select class="custom-select" id="service_status_<?= (int)$service['id'] ?>" name="active"><option value="1" <?= $service['active']?'selected':'' ?>>Active</option><option value="0" <?= !$service['active']?'selected':'' ?>>Inactive</option></select></div></div>
                            <div class="col-md-6"><div class="form-group"><label for="service_taxable_<?= (int)$service['id'] ?>">Taxable</label><select class="custom-select" id="service_taxable_<?= (int)$service['id'] ?>" name="taxable"><option value="1" <?= $service['taxable']?'selected':'' ?>>Yes</option><option value="0" <?= !$service['taxable']?'selected':'' ?>>No</option></select></div></div>
                            <div class="col-12"><div class="form-group mb-0"><label for="service_description_<?= (int)$service['id'] ?>">Description</label><textarea class="form-control" id="service_description_<?= (int)$service['id'] ?>" name="description" rows="3" maxlength="2000"><?= e($service['description'] ?? '') ?></textarea></div></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fal fa-save mr-1"></i> Save changes</button></div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="row mt-3">
    <div class="col-lg-7"><section class="panel"><div class="panel-hdr"><div><h2>Pipeline stages</h2><small class="text-muted">Names, order, colors, and conversion probabilities drive the live sales board.</small></div><div class="panel-toolbar"><button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#add-pipeline-stage"><i class="fal fa-plus mr-1"></i> Add stage</button></div></div><div class="panel-container show"><div class="panel-content p-0"><div class="list-group list-group-flush"><?php foreach($stages as $stage): ?><div class="list-group-item pipeline-stage-setting"><div class="pipeline-stage-order"><?= (int)$stage['position'] ?></div><i class="fal fa-circle text-<?= e($stage['color']==='purple'?'primary':$stage['color']) ?> mr-3"></i><div><strong><?= e($stage['name']) ?></strong><small class="d-block text-muted"><?= (int)$stage['win_probability'] ?>% conversion probability<?= $stage['is_closed']?' · closed stage':'' ?></small></div><button type="button" class="btn btn-sm btn-outline-primary ml-auto" data-toggle="modal" data-target="#edit-pipeline-stage-<?= (int)$stage['id'] ?>"><i class="fal fa-pen mr-1"></i> Edit</button></div><?php endforeach; ?></div></div></div></section></div>
    <div class="col-lg-5"><section class="panel"><div class="panel-hdr"><h2>Lead sources</h2></div><div class="panel-container show"><div class="panel-content p-0"><div class="list-group list-group-flush"><?php foreach($options['sources'] as $source): ?><div class="list-group-item d-flex align-items-center"><i class="fal fa-bullhorn text-primary mr-3"></i><span><?= e($source['name']) ?></span><span class="badge badge-soft-success ml-auto">Active</span></div><?php endforeach; ?></div></div></div></section></div>
</div>

<div class="modal fade" id="add-pipeline-stage" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="<?= e(url('settings')) ?>"><div class="modal-header"><div><span class="eyebrow">PIPELINE CONFIGURATION</span><h2 class="modal-title">Add pipeline stage</h2></div><button class="close" type="button" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="create_pipeline_stage"><div class="form-group"><label class="required">Stage name</label><input class="form-control" name="name" required placeholder="Example: Decision pending"></div><div class="row"><div class="col-4"><div class="form-group"><label class="required">Position</label><input class="form-control" type="number" min="1" max="<?= $openStageCount+1 ?>" name="position" value="<?= max(1,$openStageCount) ?>" required></div></div><div class="col-4"><div class="form-group"><label class="required">Probability %</label><input class="form-control" type="number" min="0" max="100" name="win_probability" value="50" required></div></div><div class="col-4"><div class="form-group"><label>Color</label><select class="custom-select" name="color"><?php foreach(['info'=>'Blue','primary'=>'Orange','warning'=>'Amber','success'=>'Green','danger'=>'Red','purple'=>'Purple','secondary'=>'Grey'] as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div></div></div><div class="alert alert-info mb-0"><i class="fal fa-lock mr-2"></i>Custom stages are open stages. Won and Lost remain protected closing stages.</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button class="btn btn-primary">Create stage</button></div></form></div></div></div>

<?php foreach($stages as $stage): ?><div class="modal fade" id="edit-pipeline-stage-<?= (int)$stage['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="<?= e(url('settings')) ?>"><div class="modal-header"><div><span class="eyebrow">PIPELINE CONFIGURATION</span><h2 class="modal-title">Edit <?= e($stage['name']) ?></h2></div><button class="close" type="button" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>"><input type="hidden" name="action" value="update_pipeline_stage"><input type="hidden" name="stage_id" value="<?= (int)$stage['id'] ?>"><div class="form-group"><label class="required">Stage name</label><input class="form-control" name="name" value="<?= e($stage['name']) ?>" required></div><div class="row"><div class="col-4"><div class="form-group"><label class="required">Position</label><input class="form-control" type="number" min="1" max="<?= $openStageCount ?>" name="position" value="<?= (int)$stage['position'] ?>" <?= $stage['is_closed']?'readonly':'' ?> required></div></div><div class="col-4"><div class="form-group"><label class="required">Probability %</label><input class="form-control" type="number" min="0" max="100" name="win_probability" value="<?= (int)$stage['win_probability'] ?>" <?= in_array($stage['slug'],['won','lost'],true)?'readonly':'' ?> required></div></div><div class="col-4"><div class="form-group"><label>Color</label><select class="custom-select" name="color"><?php foreach(['info'=>'Blue','primary'=>'Orange','warning'=>'Amber','success'=>'Green','danger'=>'Red','purple'=>'Purple','secondary'=>'Grey'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $stage['color']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div></div><?php if(in_array($stage['slug'],['won','lost'],true)): ?><div class="alert alert-warning mb-0"><i class="fal fa-shield-check mr-2"></i>This is a protected closing stage. Its probability, position, and closing behavior cannot be changed.</div><?php endif; ?></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button class="btn btn-primary">Save stage</button></div></form></div></div></div><?php endforeach; ?>
<?php endif; ?>

<?php $account = $auth->user(); ?>

<div class="page-title-row">
    <div>
        <span class="eyebrow">ACCOUNT SECURITY</span>
        <h1>Change Password</h1>
        <p>Update the password used to sign in to your 360 Creative Agency account.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-xl-7 col-lg-8">
        <section class="panel security-password-panel">
            <div class="panel-hdr">
                <div>
                    <h2><i class="fal fa-shield-check mr-2 text-primary"></i>Secure your account</h2>
                    <small>Signed in as <?= e($account['email']) ?></small>
                </div>
            </div>
            <div class="panel-container show">
                <div class="panel-content">
                    <div class="alert alert-info border-0 mb-4">
                        <i class="fal fa-info-circle mr-2"></i>
                        Use at least 10 characters. After you save, use the new password the next time you sign in.
                    </div>
                    <form method="post" action="<?= e(url('change_password')) ?>" autocomplete="off">
                        <input type="hidden" name="_token" value="<?= e(\AgencyOS\Csrf::token()) ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label class="required" for="current_password">Current password</label>
                            <div class="input-group password-field-group">
                                <input class="form-control" id="current_password" type="password" name="current_password" required autocomplete="current-password">
                                <div class="input-group-append"><button class="btn btn-outline-secondary password-visibility-toggle" type="button" data-password-toggle="current_password" aria-label="Show password"><i class="fal fa-eye"></i></button></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="required" for="new_password">New password</label>
                            <div class="input-group password-field-group">
                                <input class="form-control" id="new_password" type="password" name="new_password" minlength="10" required autocomplete="new-password">
                                <div class="input-group-append"><button class="btn btn-outline-secondary password-visibility-toggle" type="button" data-password-toggle="new_password" aria-label="Show password"><i class="fal fa-eye"></i></button></div>
                            </div>
                            <small class="form-text text-muted">Choose a password that is different from your current password.</small>
                        </div>
                        <div class="form-group mb-4">
                            <label class="required" for="new_password_confirmation">Confirm new password</label>
                            <div class="input-group password-field-group">
                                <input class="form-control" id="new_password_confirmation" type="password" name="new_password_confirmation" minlength="10" required autocomplete="new-password">
                                <div class="input-group-append"><button class="btn btn-outline-secondary password-visibility-toggle" type="button" data-password-toggle="new_password_confirmation" aria-label="Show password"><i class="fal fa-eye"></i></button></div>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fal fa-key mr-2"></i>Change password</button>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>

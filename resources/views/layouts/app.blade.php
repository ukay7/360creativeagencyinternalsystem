<?php
$currentRoute = (string)($currentRoute ?? request()->route('module') ?? 'dashboard');
$user = $auth->user();
$nav = $auth->navigationItems();
$uiLocales = (array) config('ui_languages.locales');
$uiLocale = (string) session('ui_locale', config('ui_languages.default', 'en'));
$uiLocale = array_key_exists($uiLocale, $uiLocales) ? $uiLocale : 'en';
$uiDirection = (string) ($uiLocales[$uiLocale]['dir'] ?? 'ltr');
$isClientPortal = ($user['role_slug'] ?? '') === 'client';
$profilePhotoUrl = !empty($user['employee_id']) && !empty($user['profile_photo_path'])
    ? agency_url('profile_photo', ['extra'=>(int)$user['employee_id']]).'?v='.rawurlencode((string)$user['profile_photo_path'])
    : null;
?>
<!DOCTYPE html>
<html lang="<?= e($uiLocale) ?>" dir="<?= e($uiDirection) ?>">
<head>
    <meta charset="utf-8">
    <title>360 Creative Agency</title>
    <meta name="description" content="Agency management, sales, clients, projects, content and finance.">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" media="screen, print" href="<?= e(asset('vendor/smartadmin/vendors.bundle.css')) ?>">
    <link rel="stylesheet" media="screen, print" href="<?= e(asset('vendor/smartadmin/app.bundle.css')) ?>">
    <link rel="stylesheet" media="screen, print" href="<?= e(asset('vendor/smartadmin/fa-light.css')) ?>">
    <link rel="stylesheet" media="screen, print" href="<?= e(asset('vendor/smartadmin/fa-solid.css')) ?>">
    <link rel="stylesheet" media="screen, print" href="<?= e(asset('css/agencyos.css').'?v='.filemtime(public_path('assets/css/agencyos.css'))) ?>">
    <link rel="icon" type="image/png" href="<?= e(asset('img/360-logo-final-exact.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('img/360-logo-final-exact.png')) ?>">
</head>
<body class="mod-bg-1 nav-function-fixed ui-locale-<?= e($uiLocale) ?> <?= $uiDirection === 'rtl' ? 'ui-rtl' : 'ui-ltr' ?>">
<div class="page-wrapper">
    <div class="page-inner">
        <aside class="page-sidebar">
            <div class="page-logo">
                <a href="<?= e(url('dashboard')) ?>" class="page-logo-link d-flex align-items-center">
                    <img class="brand-logo" src="<?= e(asset('img/360-logo-final-exact.png')) ?>" alt="360 Creative Agency logo">
                    <span class="page-logo-text ml-2"><?= $isClientPortal?'Client Portal':'360 Creative Agency' ?></span>
                </a>
            </div>
            <nav id="js-primary-nav" class="primary-nav" role="navigation">
                <div class="nav-filter">
                    <div class="position-relative">
                        <input type="text" id="nav_filter_input" placeholder="Filter navigation" class="form-control" tabindex="0">
                        <a href="#" class="btn-primary btn-search-close js-waves-off" data-action="toggle" data-class="list-filter-active" data-target=".page-sidebar"><i class="fal fa-chevron-up"></i></a>
                    </div>
                </div>
                <div class="info-card agency-info-card">
                    <?php if($profilePhotoUrl): ?><img class="profile-image rounded-circle user-profile-photo" src="<?= e($profilePhotoUrl) ?>" alt="<?= e($user['name']) ?> profile photo"><?php else: ?><div class="profile-image rounded-circle avatar-initials"><?= e(strtoupper(substr($user['name'],0,1))) ?></div><?php endif; ?>
                    <div class="info-card-text">
                        <span class="d-flex align-items-center text-white font-weight-bold"><?= e($user['name']) ?></span>
                        <span class="d-inline-block text-truncate text-truncate-sm"><?= e($user['role_name']) ?></span>
                    </div>
                    <div class="cover agency-cover"></div>
                </div>
                <ul id="js-nav-menu" class="nav-menu">
                    <?php foreach ($nav as $node): ?>
                        <?php if ($node['type'] === 'group'): ?>
                            <?php
                            $groupActive = false;
                            foreach ($node['children'] as $child) {
                                if ($currentRoute === $child['module'] || ($currentRoute === 'client' && $child['module'] === 'clients') || ($currentRoute === 'quote_view' && $child['module'] === 'saved_quotes') || ($currentRoute === 'invoice' && $child['module'] === 'invoices')) {
                                    $groupActive = true;
                                    break;
                                }
                            }
                            ?>
                            <li class="nav-group <?= $groupActive ? 'active open' : '' ?>">
                                <a href="#" title="<?= e($node['label']) ?>" data-filter-tags="<?= e(strtolower($node['label'].' '.implode(' ', array_column($node['children'], 'label')))) ?>" aria-expanded="<?= $groupActive ? 'true' : 'false' ?>">
                                    <i class="<?= e($node['icon']) ?>"></i><span class="nav-link-text"><?= e($node['label']) ?></span>
                                </a>
                                <ul>
                                    <?php foreach ($node['children'] as $item): ?>
                                        <?php $itemActive = $currentRoute === $item['module'] || ($currentRoute === 'client' && $item['module'] === 'clients') || ($currentRoute === 'quote_view' && $item['module'] === 'saved_quotes') || ($currentRoute === 'invoice' && $item['module'] === 'invoices'); ?>
                                        <li class="<?= $itemActive ? 'active' : '' ?>">
                                            <a href="<?= e(url($item['route'])) ?>" title="<?= e($item['label']) ?>" data-filter-tags="<?= e(strtolower($item['label'])) ?>">
                                                <i class="<?= e($item['icon']) ?>"></i><span class="nav-link-text"><?= e($item['label']) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php else: ?>
                            <?php $itemActive = $currentRoute === $node['module'] || ($currentRoute === 'client' && $node['module'] === 'clients') || ($currentRoute === 'quote_view' && $node['module'] === 'saved_quotes') || ($currentRoute === 'invoice' && $node['module'] === 'invoices'); ?>
                            <li class="<?= $itemActive ? 'active' : '' ?>">
                                <a href="<?= e(url($node['route'])) ?>" title="<?= e($node['label']) ?>" data-filter-tags="<?= e(strtolower($node['label'])) ?>">
                                    <i class="<?= e($node['icon']) ?>"></i><span class="nav-link-text"><?= e($node['label']) ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <div class="filter-message js-filter-message bg-success-600"></div>
            </nav>
            <div class="nav-footer shadow-top">
                <a href="#" data-action="toggle" data-class="nav-function-minify" class="hidden-md-down"><i class="ni ni-chevron-right"></i><i class="ni ni-chevron-right"></i></a>
                <span class="ml-auto small opacity-60">v1.0</span>
            </div>
        </aside>
        <div class="page-content-wrapper">
            <header class="page-header" role="banner">
                <div class="page-logo mobile-logo">
                    <a href="<?= e(url('dashboard')) ?>" class="page-logo-link"><img class="brand-logo" src="<?= e(asset('img/360-logo-final-exact.png')) ?>" alt="360 Creative Agency logo"><span class="page-logo-text ml-2">360 Creative Agency</span></a>
                </div>
                <div class="hidden-lg-up"><a href="#" class="header-btn btn press-scale-down" data-action="toggle" data-class="mobile-nav-on"><i class="ni ni-menu"></i></a></div>
                <?php if(!$isClientPortal): ?><form class="app-search d-none d-md-flex" action="<?= e(url('search')) ?>" method="get" role="search">
                    <input type="search" name="q" class="form-control" placeholder="Search clients, leads, projects, invoices…" value="<?= e($_GET['q'] ?? '') ?>" required minlength="2" aria-label="Global search">
                    <button type="submit" class="btn-search-close" aria-label="Search"><i class="fal fa-search"></i></button>
                </form><?php else: ?><div class="client-portal-header-title d-none d-md-flex"><i class="fal fa-shield-check mr-2"></i>Secure client workspace</div><?php endif; ?>
                <div class="ml-auto d-flex align-items-center">
                    <form method="post" action="<?= e(route('ui.language')) ?>" class="ui-language-switcher mr-2" data-ui-no-translate>
                        @csrf
                        <i class="fal fa-language" aria-hidden="true"></i>
                        <select name="locale" aria-label="Interface language" onchange="this.form.submit()">
                            <?php foreach ($uiLocales as $code => $language): ?>
                                <option value="<?= e($code) ?>" <?= $uiLocale === $code ? 'selected' : '' ?>><?= e($language['native']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php if(!$isClientPortal): ?><div class="dropdown mr-2">
                        <button class="btn btn-primary btn-sm rounded-pill px-3" data-toggle="dropdown"><i class="fal fa-plus mr-1"></i> Quick add</button>
                        <div class="dropdown-menu dropdown-menu-right p-2 quick-menu">
                            <?php foreach ([['quote_studio','file-invoice-dollar','Quote'],['projects','briefcase','Project'],['tasks','check-square','Task'],['invoices','file-invoice-dollar','Invoice']] as $quick): ?>
                                <a class="dropdown-item" href="<?= e(url($quick[0])) ?>#modal-add"><i class="fal fa-<?= e($quick[1]) ?> mr-2 text-primary"></i><?= e($quick[2]) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <a href="<?= e(url('search')) ?>" class="header-icon d-md-none" aria-label="Search"><i class="fal fa-search"></i></a><?php endif; ?>
                    <div class="dropdown">
                        <a href="#" data-toggle="dropdown" class="header-icon d-flex align-items-center justify-content-center ml-2" aria-label="Account menu"><?php if($profilePhotoUrl): ?><img class="avatar-sm user-avatar-photo" src="<?= e($profilePhotoUrl) ?>" alt=""><?php else: ?><span class="avatar-sm"><?= e(strtoupper(substr($user['name'],0,1))) ?></span><?php endif; ?></a>
                        <div class="dropdown-menu dropdown-menu-right p-2">
                            <div class="px-3 py-2 border-bottom mb-2"><strong class="d-block"><?= e($user['name']) ?></strong><small class="text-muted"><?= e($user['email']) ?></small></div>
                            <a class="dropdown-item" href="<?= e(url('change_password')) ?>"><i class="fal fa-key mr-2"></i>Change password</a>
                            <form action="<?= e(url($currentRoute)) ?>" method="post">
                                <?= \AgencyOS\Csrf::field() ?><input type="hidden" name="action" value="logout">
                                <button class="dropdown-item text-danger" type="submit"><i class="fal fa-sign-out mr-2"></i>Sign out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>
            <main id="js-page-content" role="main" class="page-content">
                <?php foreach ($flashes as $message): ?>
                    <div class="alert alert-<?= e($message['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                        <i class="fal <?= $message['type']==='success'?'fa-check-circle':'fa-exclamation-circle' ?> mr-2"></i><?= e($message['message']) ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php endforeach; ?>
                @include($contentView)
            </main>
            <footer class="page-footer" role="contentinfo">
                <div class="d-flex align-items-center flex-1 text-muted"><span>360 Creative Agency · <?= $isClientPortal?'Secure Client Portal':'Internal Operations' ?></span></div>
                <?php if(!$isClientPortal): ?><div><a href="<?= e(url('audit')) ?>" class="text-muted">Audit trail</a></div><?php endif; ?>
            </footer>
        </div>
    </div>
</div>
<p id="js-color-profile" class="d-none" aria-hidden="true">
    <?php foreach (['primary','info','danger','warning','success','fusion'] as $colorGroup): ?>
        <?php foreach ([50,100,200,300,400,500,600,700,800,900] as $shade): ?><span class="color-<?= e($colorGroup) ?>-<?= (int)$shade ?>"></span><?php endforeach; ?>
    <?php endforeach; ?>
</p>
<script src="<?= e(asset('vendor/smartadmin-js/vendors.bundle.js')) ?>"></script>
<script src="<?= e(asset('vendor/smartadmin-js/app.bundle.js')) ?>"></script>
<?php if(in_array($currentRoute,['dashboard','reports'],true)): ?><script src="<?= e(asset('vendor/smartadmin-js/statistics/chartjs/chartjs.bundle.js')) ?>"></script><?php endif; ?>
<script src="<?= e(asset('js/agencyos.js')) ?>"></script>
<script type="application/json" id="ui-language-data"><?= json_encode(['locale'=>$uiLocale,'dir'=>$uiDirection,'translations'=>(array)config('ui_languages.translations.'.$uiLocale,[])], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset('js/ui-language.js').'?v='.(file_exists(public_path('assets/js/ui-language.js'))?filemtime(public_path('assets/js/ui-language.js')):time())) ?>"></script>
</body>
</html>

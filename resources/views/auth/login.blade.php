<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><title>360 Creative Agency</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= e(asset('vendor/smartadmin/vendors.bundle.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/smartadmin/app.bundle.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/smartadmin/fa-light.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/agencyos.css')) ?>">
    <link rel="icon" type="image/png" href="<?= e(asset('img/360-logo-final-exact.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('img/360-logo-final-exact.png')) ?>">
</head>
<body class="login-page">
<main class="login-shell">
    <section class="login-story">
        <div class="login-brand"><img class="brand-logo login-brand-logo" src="<?= e(asset('img/360-logo-final-exact.png')) ?>" alt="360 Creative Agency logo"><span>360 Creative Agency</span></div>
        <div class="login-copy">
            <span class="eyebrow light">THE AGENCY OPERATING SYSTEM</span>
            <h1>Every client relationship.<br>One clear command center.</h1>
            <p>Move opportunities from first contact to profitable delivery—with packages, projects, content, visits, billing, and reporting connected end to end.</p>
            <div class="login-proof">
                <div><strong>12</strong><span>core workflows</span></div>
                <div><strong>1</strong><span>source of truth</span></div>
                <div><strong>100%</strong><span>auditable</span></div>
            </div>
        </div>
        <div class="login-orbit orbit-one"></div><div class="login-orbit orbit-two"></div>
    </section>
    <section class="login-form-panel">
        <form class="login-card" method="post" action="<?= e(url('login')) ?>">
            <?= \AgencyOS\Csrf::field() ?><input type="hidden" name="action" value="login">
            <span class="eyebrow">SECURE TEAM ACCESS</span>
            <h2>Welcome back</h2>
            <p class="text-muted mb-4">Sign in to manage 360 Creative Agency operations.</p>
            <?php foreach ($flashes as $message): ?><div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div><?php endforeach; ?>
            <div class="form-group"><label for="email">Email address</label><div class="input-group input-group-lg"><div class="input-group-prepend"><span class="input-group-text"><i class="fal fa-envelope"></i></span></div><input id="email" name="email" type="email" value="<?= e(old('email', '')) ?>" class="form-control" autocomplete="username" required></div></div>
            <div class="form-group"><label for="password">Password</label><div class="input-group input-group-lg"><div class="input-group-prepend"><span class="input-group-text"><i class="fal fa-lock"></i></span></div><input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required></div></div>
            <button class="btn btn-primary btn-lg btn-block mt-4" type="submit">Enter command center <i class="fal fa-arrow-right ml-2"></i></button>
        </form>
    </section>
</main>
</body>
</html>

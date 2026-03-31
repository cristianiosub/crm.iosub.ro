<?php
if (($_GET['action'] ?? '') === 'logout') { Auth::logout(); session_start(); flash('info', 'Te-ai deconectat cu succes.'); Router::redirect('login'); }
if (Auth::check()) Router::redirect('dashboard');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRF()) { $error = 'Token de securitate invalid. Reîncarcă pagina.'; }
    else {
        $result = Auth::login(Security::sanitizeEmail($_POST['email'] ?? ''), $_POST['password'] ?? '');
        if ($result['success']) Router::redirect('dashboard');
        else $error = $result['error'];
    }
}
?><!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex, nofollow"><title>Autentificare — <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css"></head><body>
<div class="login-page"><div class="login-box">
<div style="text-align:center;margin-bottom:20px;"><div style="width:52px;height:52px;background:linear-gradient(135deg,#2563eb,#7c3aed);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:22px;">C</div></div>
<h1><?= e(APP_NAME) ?></h1><p class="login-subtitle">Autentifică-te pentru a continua</p>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<?php foreach (getFlash() as $msg): ?><div class="flash flash-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div><?php endforeach; ?>
<form method="POST" autocomplete="off"><?= csrf() ?>
<div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required autofocus value="<?= e($_POST['email'] ?? '') ?>" placeholder="email@exemplu.ro"></div>
<div class="form-group"><label>Parolă</label><input type="password" name="password" class="form-control" required placeholder="••••••••"></div>
<button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:12px;">Conectare</button></form></div></div></body></html>

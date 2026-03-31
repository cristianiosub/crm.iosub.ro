<?php
$profileId = Auth::profileId();
$pageTitle = 'Setari companie';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['_action'] ?? '';
    if ($pa === 'save_profile') {
        $pid = (int)$_POST['profile_id']; $data = [];
        foreach (['name','legal_name','cui','reg_com','address','city','county','country','phone','email','website','bank_name','bank_iban','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from_name','smtp_from_email','default_currency','default_vat_rate'] as $f) { if (isset($_POST[$f])) $data[$f] = trim($_POST[$f]); }
        if (isset($_POST['smtp_port'])) $data['smtp_port'] = (int)$_POST['smtp_port'];
        if (isset($_POST['default_vat_rate'])) $data['default_vat_rate'] = (float)$_POST['default_vat_rate'];
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) { $logoPath = uploadFile($_FILES['logo'], 'logos'); if ($logoPath) $data['logo_path'] = $logoPath; }
        DB::update('profiles', $data, 'id = ?', [$pid]); flash('success', 'Profil actualizat.'); Router::redirect('profiles');
    }
    if ($pa === 'add_profile') { $newId = DB::insert('profiles', ['name'=>trim($_POST['name']??'Profil nou'), 'email'=>trim($_POST['email']??'')]); Auth::setProfile($newId); flash('success', 'Profil creat.'); Router::redirect('profiles'); }
    if ($pa === 'change_password') { $p = $_POST['new_password']??''; if (strlen($p)>=6) { DB::update('users', ['password_hash'=>password_hash($p, PASSWORD_BCRYPT, ['cost'=>BCRYPT_COST])], 'id = ?', [Auth::userId()]); flash('success', 'Parola schimbata.'); } else flash('error', 'Minim 6 caractere.'); Router::redirect('profiles'); }
}

// Handle password section from sidebar link
if (isset($_GET['section']) && $_GET['section'] === 'password') {
    $pageTitle = 'Schimba parola';
?>
<div style="max-width:420px;">
    <div class="card"><div class="card-header"><h2>Schimba parola contului</h2></div>
    <form method="POST" class="card-body"><?= csrf() ?><input type="hidden" name="_action" value="change_password">
    <p class="text-muted mb-2" style="font-size:13px;">Conectat ca: <strong><?= e(Auth::userEmail()) ?></strong></p>
    <div class="form-group"><label>Parola noua</label><input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Minim 6 caractere"></div>
    <button type="submit" class="btn btn-primary">Salveaza parola noua</button></form></div>
</div>
<?php return; }

$profile = Auth::getProfile();
?>

<div class="flex-between mb-2">
<div><span class="text-muted" style="font-size:13px;">Editezi:</span> <strong><?= e($profile['name'] ?? '') ?></strong></div>
<button class="btn btn-primary btn-sm" onclick="openModal('modalNewProfile')">+ Profil nou</button></div>

<?php if ($profile): ?>
<form method="POST" enctype="multipart/form-data"><?= csrf() ?><input type="hidden" name="_action" value="save_profile"><input type="hidden" name="profile_id" value="<?= $profile['id'] ?>">
<div class="card mb-2"><div class="card-header"><h2>Date companie</h2></div><div class="card-body">
<div class="form-row"><div class="form-group"><label>Denumire</label><input type="text" name="name" class="form-control" value="<?= e($profile['name']) ?>"></div><div class="form-group"><label>Denumire juridica</label><input type="text" name="legal_name" class="form-control" value="<?= e($profile['legal_name']??'') ?>"></div></div>
<div class="form-row"><div class="form-group"><label>CUI</label><input type="text" name="cui" class="form-control" value="<?= e($profile['cui']??'') ?>"></div><div class="form-group"><label>Reg. Com.</label><input type="text" name="reg_com" class="form-control" value="<?= e($profile['reg_com']??'') ?>"></div></div>
<div class="form-group"><label>Adresa</label><input type="text" name="address" class="form-control" value="<?= e($profile['address']??'') ?>"></div>
<div class="form-row"><div class="form-group"><label>Oras</label><input type="text" name="city" class="form-control" value="<?= e($profile['city']??'') ?>"></div><div class="form-group"><label>Judet</label><input type="text" name="county" class="form-control" value="<?= e($profile['county']??'') ?>"></div><div class="form-group"><label>Tara</label><input type="text" name="country" class="form-control" value="<?= e($profile['country']??'Romania') ?>"></div></div>
<div class="form-row"><div class="form-group"><label>Telefon</label><input type="text" name="phone" class="form-control" value="<?= e($profile['phone']??'') ?>"></div><div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($profile['email']??'') ?>"></div><div class="form-group"><label>Website</label><input type="url" name="website" class="form-control" value="<?= e($profile['website']??'') ?>"></div></div>
<div class="form-row"><div class="form-group"><label>Banca</label><input type="text" name="bank_name" class="form-control" value="<?= e($profile['bank_name']??'') ?>"></div><div class="form-group"><label>IBAN</label><input type="text" name="bank_iban" class="form-control" value="<?= e($profile['bank_iban']??'') ?>"></div></div>
<div class="form-row"><div class="form-group"><label>Moneda</label><input type="text" name="default_currency" class="form-control" value="<?= e($profile['default_currency']??'RON') ?>"></div><div class="form-group"><label>TVA %</label><input type="number" name="default_vat_rate" class="form-control" step="0.01" value="<?= $profile['default_vat_rate']??19 ?>"></div><div class="form-group"><label>Logo</label><input type="file" name="logo" class="form-control" accept="image/*"><?php if($profile['logo_path']):?><div class="form-hint">Actual: <?= e($profile['logo_path']) ?></div><?php endif;?></div></div>
</div></div>

<div class="card mb-2"><div class="card-header"><h2>Email (SMTP)</h2></div><div class="card-body">
<div class="form-row"><div class="form-group"><label>SMTP Host</label><input type="text" name="smtp_host" class="form-control" value="<?= e($profile['smtp_host']??'smtp.gmail.com') ?>"></div><div class="form-group"><label>Port</label><input type="number" name="smtp_port" class="form-control" value="<?= $profile['smtp_port']??587 ?>"></div></div>
<div class="form-row"><div class="form-group"><label>User</label><input type="text" name="smtp_user" class="form-control" value="<?= e($profile['smtp_user']??'') ?>"></div><div class="form-group"><label>App Password</label><input type="password" name="smtp_pass" class="form-control" value="<?= e($profile['smtp_pass']??'') ?>"></div></div>
<div class="form-row"><div class="form-group"><label>From Name</label><input type="text" name="smtp_from_name" class="form-control" value="<?= e($profile['smtp_from_name']??'') ?>"></div><div class="form-group"><label>From Email</label><input type="email" name="smtp_from_email" class="form-control" value="<?= e($profile['smtp_from_email']??'') ?>"></div></div>
</div></div>
<div style="display:flex;justify-content:flex-end;"><button type="submit" class="btn btn-primary btn-lg">Salveaza profil</button></div>
</form>
<?php endif; ?>

<div class="modal-overlay" id="modalNewProfile"><div class="modal" style="max-width:400px;"><div class="modal-header"><h3>Profil nou</h3><button class="btn btn-icon btn-outline" onclick="closeModal('modalNewProfile')">✕</button></div>
<form method="POST" class="modal-body"><?= csrf() ?><input type="hidden" name="_action" value="add_profile">
<div class="form-group"><label>Denumire *</label><input type="text" name="name" class="form-control" required></div>
<div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
<button type="submit" class="btn btn-primary btn-block">Creeaza</button></form></div></div>

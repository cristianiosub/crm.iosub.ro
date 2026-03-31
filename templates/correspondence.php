<?php
$profileId = Auth::profileId();
$pageTitle = 'Corespondență';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['_action'] ?? '';
    if ($pa === 'save_correspondence') {
        $data = ['profile_id'=>$profileId, 'email_address'=>trim($_POST['email_address']??''), 'company_name'=>trim($_POST['company_name']??''), 'cui'=>trim($_POST['cui']??''), 'status'=>$_POST['status']??'new', 'estimated_value'=>(float)($_POST['estimated_value']??0), 'last_contact_date'=>$_POST['last_contact_date']?:date('Y-m-d'), 'notes'=>trim($_POST['notes']??''), 'client_id'=>(int)($_POST['client_id']??0)?:null];
        $cid = (int)($_POST['corr_id'] ?? 0);
        if ($cid) { DB::update('correspondence', $data, 'id = ? AND profile_id = ?', [$cid, $profileId]); flash('success', 'Actualizată.'); }
        else { DB::insert('correspondence', $data); flash('success', 'Adăugată.'); }
        Router::redirect('correspondence');
    }
    if ($pa === 'delete_correspondence') { DB::delete('correspondence', 'id = ? AND profile_id = ?', [(int)$_POST['corr_id'], $profileId]); flash('success', 'Ștearsă.'); Router::redirect('correspondence'); }
}

$statusFilter = $_GET['status'] ?? ''; $where = "profile_id = ?"; $params = [$profileId];
if ($statusFilter) { $where .= " AND status = ?"; $params[] = $statusFilter; }
$correspondences = DB::fetchAll("SELECT * FROM correspondence WHERE $where ORDER BY last_contact_date DESC", $params);
$clients = DB::fetchAll("SELECT id, company_name FROM clients WHERE profile_id = ? ORDER BY company_name", [$profileId]);
$totalValue = array_sum(array_column($correspondences, 'estimated_value'));
$statuses = array_count_values(array_column($correspondences, 'status'));
?>
<div class="stats-grid">
<div class="stat-card"><div class="stat-label">Total</div><div class="stat-value"><?= count($correspondences) ?></div></div>
<div class="stat-card"><div class="stat-label">Valoare</div><div class="stat-value"><?= formatMoney($totalValue) ?></div></div>
<div class="stat-card"><div class="stat-label">Oferte trimise</div><div class="stat-value"><?= $statuses['offer_sent'] ?? 0 ?></div></div>
<div class="stat-card"><div class="stat-label">Câștigate</div><div class="stat-value text-success"><?= $statuses['won'] ?? 0 ?></div></div></div>

<div class="flex-between mb-2">
<form method="GET" style="display:flex;gap:8px;"><select name="status" class="form-control" style="width:180px" onchange="this.form.submit()"><option value="">Toate</option><?php foreach (['new','replied','offer_sent','negotiation','won','lost','no_response'] as $s): ?><option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?></select></form>
<button class="btn btn-primary" onclick="openModal('modalCorr')">+ Adaugă</button></div>

<div class="card">
<?php if (empty($correspondences)): ?><div class="empty-state"><div class="empty-icon">💬</div><h3>Nicio corespondență</h3></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th>Email</th><th>Companie</th><th>CUI</th><th>Status</th><th>Valoare</th><th>Contact</th><th>Note</th><th></th></tr></thead><tbody>
<?php foreach ($correspondences as $c): ?><tr>
<td><strong><?= e($c['email_address']) ?></strong></td><td><?= e($c['company_name']?:'-') ?></td><td class="text-muted"><?= e($c['cui']?:'-') ?></td>
<td><form method="POST" style="display:inline;"><?= csrf() ?><input type="hidden" name="_action" value="save_correspondence"><input type="hidden" name="corr_id" value="<?= $c['id'] ?>"><input type="hidden" name="email_address" value="<?= e($c['email_address']) ?>"><input type="hidden" name="company_name" value="<?= e($c['company_name']) ?>"><input type="hidden" name="cui" value="<?= e($c['cui']) ?>"><input type="hidden" name="estimated_value" value="<?= $c['estimated_value'] ?>"><input type="hidden" name="last_contact_date" value="<?= $c['last_contact_date'] ?>"><input type="hidden" name="notes" value="<?= e($c['notes']) ?>">
<select name="status" class="form-control" style="width:130px;padding:4px 8px;font-size:12px;" onchange="this.form.submit()"><?php foreach (['new','replied','offer_sent','negotiation','won','lost','no_response'] as $s): ?><option value="<?= $s ?>" <?= $c['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?></select></form></td>
<td><?= $c['estimated_value']>0 ? formatMoney($c['estimated_value']) : '-' ?></td><td class="text-muted"><?= formatDate($c['last_contact_date']) ?></td><td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($c['notes']?:'-') ?></td>
<td><form method="POST" style="display:inline;" onsubmit="return confirm('Ștergi?')"><?= csrf() ?><input type="hidden" name="_action" value="delete_correspondence"><input type="hidden" name="corr_id" value="<?= $c['id'] ?>"><button type="submit" class="btn btn-sm btn-danger btn-icon">✕</button></form></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>

<div class="modal-overlay" id="modalCorr"><div class="modal" style="max-width:600px;"><div class="modal-header"><h3>Adaugă corespondență</h3><button class="btn btn-icon btn-outline" onclick="closeModal('modalCorr')">✕</button></div>
<form method="POST" class="modal-body"><?= csrf() ?><input type="hidden" name="_action" value="save_correspondence">
<div class="form-row"><div class="form-group"><label>Email *</label><input type="email" name="email_address" class="form-control" required></div><div class="form-group"><label>CUI</label><input type="text" name="cui" class="form-control"></div></div>
<div class="form-group"><label>Companie</label><input type="text" name="company_name" class="form-control"></div>
<div class="form-row"><div class="form-group"><label>Status</label><select name="status" class="form-control"><?php foreach (['new','replied','offer_sent','negotiation','won','lost','no_response'] as $s): ?><option value="<?= $s ?>"><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Valoare (RON)</label><input type="number" name="estimated_value" class="form-control" step="0.01" value="0"></div></div>
<div class="form-group"><label>Ultimul contact</label><input type="date" name="last_contact_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
<div class="form-group"><label>Client existent</label><select name="client_id" class="form-control"><option value="0">—</option><?php foreach ($clients as $cl): ?><option value="<?= $cl['id'] ?>"><?= e($cl['company_name']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>Note</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
<div style="display:flex;gap:8px;justify-content:flex-end;"><button type="button" class="btn btn-outline" onclick="closeModal('modalCorr')">Anulează</button><button type="submit" class="btn btn-primary">Salvează</button></div></form></div></div>

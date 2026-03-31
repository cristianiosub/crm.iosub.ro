<?php
$profileId = Auth::profileId();
$action = $_GET['action'] ?? 'list';
$clientId = (int)($_GET['id'] ?? 0);
$pageTitle = 'Clienți';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['_action'] ?? '';
    if ($postAction === 'save_client') {
        $data = [
            'profile_id' => $profileId,
            'company_name' => trim($_POST['company_name'] ?? ''),
            'cui' => trim($_POST['cui'] ?? ''),
            'reg_com' => trim($_POST['reg_com'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'county' => trim($_POST['county'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'website' => trim($_POST['website'] ?? ''),
            'industry' => trim($_POST['industry'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'source' => trim($_POST['source'] ?? 'manual'),
            'pipeline_status' => $_POST['pipeline_status'] ?? 'lead',
            'estimated_value' => (float)($_POST['estimated_value'] ?? 0),
        ];
        $editId = (int)($_POST['client_id'] ?? 0);
        if ($editId) {
            DB::update('clients', $data, 'id = ? AND profile_id = ?', [$editId, $profileId]);
            flash('success', 'Client actualizat cu succes.');
            Router::redirect('clients/' . $editId);
        } else {
            $newId = DB::insert('clients', $data);
            flash('success', 'Client adăugat cu succes.');
            Router::redirect('clients/' . $newId);
        }
    }
    if ($postAction === 'save_contact') {
        $cData = ['client_id'=>(int)$_POST['client_id'], 'full_name'=>trim($_POST['full_name']??''), 'position'=>trim($_POST['position']??''), 'email'=>trim($_POST['email']??''), 'phone'=>trim($_POST['phone']??''), 'is_primary'=>isset($_POST['is_primary'])?1:0, 'notes'=>trim($_POST['notes']??'')];
        $contactId = (int)($_POST['contact_id'] ?? 0);
        if ($contactId) DB::update('client_contacts', $cData, 'id = ?', [$contactId]);
        else DB::insert('client_contacts', $cData);
        flash('success', 'Contact salvat.');
        Router::redirect('clients/' . $cData['client_id']);
    }
    if ($postAction === 'delete_client') {
        DB::delete('clients', 'id = ? AND profile_id = ?', [(int)$_POST['client_id'], $profileId]);
        flash('success', 'Client șters.');
        Router::redirect('clients');
    }
}

if ($action === 'list'):
    $search = trim($_GET['q'] ?? ''); $statusFilter = $_GET['status'] ?? '';
    $where = "profile_id = ?"; $params = [$profileId];
    if ($search) { $where .= " AND (company_name LIKE ? OR cui LIKE ? OR email LIKE ?)"; $s = "%$search%"; $params = array_merge($params, [$s,$s,$s]); }
    if ($statusFilter) { $where .= " AND pipeline_status = ?"; $params[] = $statusFilter; }
    $clients = DB::fetchAll("SELECT * FROM clients WHERE $where ORDER BY created_at DESC", $params);
?>
<div class="flex-between mb-2">
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <input type="text" name="q" class="form-control" style="width:280px" placeholder="Caută client..." value="<?= e($search) ?>">
        <select name="status" class="form-control" style="width:160px" onchange="this.form.submit()">
            <option value="">Toate statusurile</option>
            <?php foreach (['lead','contacted','offer_sent','negotiation','won','lost','dormant'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Caută</button>
    </form>
    <a href="<?= Router::url('clients/new') ?>" class="btn btn-primary">+ Client nou</a>
</div>
<div class="card">
    <?php if (empty($clients)): ?>
        <div class="empty-state"><div class="empty-icon">👥</div><h3>Niciun client</h3><p>Adaugă primul client din butonul de mai sus.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Companie</th><th>CUI</th><th>Email</th><th>Telefon</th><th>Status</th><th>Valoare est.</th><th>Adăugat</th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr onclick="location.href='<?= Router::url("clients/{$c['id']}") ?>'">
                    <td><strong><?= e($c['company_name']) ?></strong></td><td class="text-muted"><?= e($c['cui'] ?: '-') ?></td><td><?= e($c['email'] ?: '-') ?></td><td><?= e($c['phone'] ?: '-') ?></td><td><?= statusBadge($c['pipeline_status']) ?></td><td><?= $c['estimated_value'] > 0 ? formatMoney($c['estimated_value']) : '-' ?></td><td class="text-muted"><?= formatDate($c['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>

<?php elseif ($action === 'new' || $action === 'edit'):
    $client = null;
    if ($action === 'edit' && $clientId) {
        $client = DB::fetchOne("SELECT * FROM clients WHERE id = ? AND profile_id = ?", [$clientId, $profileId]);
        if (!$client) { flash('error', 'Client negăsit.'); Router::redirect('clients'); }
        $pageTitle = 'Editare client: ' . $client['company_name'];
    } else { $pageTitle = 'Client nou'; }
?>
<div class="card" style="max-width:800px;">
    <div class="card-header"><h2><?= $client ? 'Editare client' : 'Adaugă client nou' ?></h2></div>
    <form method="POST" class="card-body" id="clientForm">
        <?= csrf() ?>
        <input type="hidden" name="_action" value="save_client">
        <input type="hidden" name="client_id" value="<?= $client['id'] ?? '' ?>">
        <div class="form-group"><label>CUI / Cod Fiscal</label><div style="display:flex;gap:8px;"><input type="text" name="cui" class="form-control" value="<?= e($client['cui'] ?? '') ?>" placeholder="Ex: 12345678"><button type="button" class="btn btn-outline btn-lookup" onclick="lookupCUI(this.closest('.form-group').querySelector('input'), document.getElementById('clientForm'))">Caută CUI</button></div><div class="form-hint">Introdu CUI-ul și apasă "Caută CUI" pentru completare automată din ANAF</div></div>
        <div class="form-row"><div class="form-group"><label>Denumire companie *</label><input type="text" name="company_name" class="form-control" required value="<?= e($client['company_name'] ?? '') ?>"></div><div class="form-group"><label>Nr. Registrul Comerțului</label><input type="text" name="reg_com" class="form-control" value="<?= e($client['reg_com'] ?? '') ?>"></div></div>
        <div class="form-group"><label>Adresă</label><input type="text" name="address" class="form-control" value="<?= e($client['address'] ?? '') ?>"></div>
        <div class="form-row"><div class="form-group"><label>Oraș</label><input type="text" name="city" class="form-control" value="<?= e($client['city'] ?? '') ?>"></div><div class="form-group"><label>Județ</label><input type="text" name="county" class="form-control" value="<?= e($client['county'] ?? '') ?>"></div></div>
        <div class="form-row"><div class="form-group"><label>Telefon</label><input type="text" name="phone" class="form-control" value="<?= e($client['phone'] ?? '') ?>"></div><div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($client['email'] ?? '') ?>"></div></div>
        <div class="form-row"><div class="form-group"><label>Website</label><input type="url" name="website" class="form-control" value="<?= e($client['website'] ?? '') ?>"></div><div class="form-group"><label>Industrie</label><input type="text" name="industry" class="form-control" value="<?= e($client['industry'] ?? '') ?>" placeholder="IT, Educație, Administrație publică..."></div></div>
        <div class="form-row"><div class="form-group"><label>Status pipeline</label><select name="pipeline_status" class="form-control"><?php foreach (['lead','contacted','offer_sent','negotiation','won','lost','dormant'] as $s): ?><option value="<?= $s ?>" <?= ($client['pipeline_status'] ?? 'lead') === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Valoare estimată (RON)</label><input type="number" name="estimated_value" class="form-control" step="0.01" value="<?= $client['estimated_value'] ?? '0' ?>"></div><div class="form-group"><label>Sursă</label><select name="source" class="form-control"><?php foreach (['manual','email_campaign','referral','website','event','other'] as $s): ?><option value="<?= $s ?>" <?= ($client['source'] ?? 'manual') === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?></select></div></div>
        <div class="form-group"><label>Note</label><textarea name="notes" class="form-control" rows="3"><?= e($client['notes'] ?? '') ?></textarea></div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;"><a href="<?= Router::url('clients') ?>" class="btn btn-outline">Anulează</a><button type="submit" class="btn btn-primary">Salvează client</button></div>
    </form>
</div>

<?php elseif ($action === 'view' && $clientId):
    $client = DB::fetchOne("SELECT * FROM clients WHERE id = ? AND profile_id = ?", [$clientId, $profileId]);
    if (!$client) { flash('error', 'Client negăsit.'); Router::redirect('clients'); }
    $contacts = DB::fetchAll("SELECT * FROM client_contacts WHERE client_id = ? ORDER BY is_primary DESC, full_name ASC", [$clientId]);
    $projects = DB::fetchAll("SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC", [$clientId]);
    $offers = DB::fetchAll("SELECT * FROM offers WHERE client_id = ? ORDER BY created_at DESC", [$clientId]);
    $emails = DB::fetchAll("SELECT * FROM email_log WHERE client_id = ? ORDER BY created_at DESC LIMIT 20", [$clientId]);
    $documents = DB::fetchAll("SELECT * FROM documents WHERE client_id = ? ORDER BY created_at DESC", [$clientId]);
    $pageTitle = $client['company_name'];
?>
<div class="flex-between mb-2">
    <a href="<?= Router::url('clients') ?>" class="text-muted" style="font-size:13px;">← Înapoi la clienți</a>
    <div style="display:flex;gap:8px;">
        <a href="<?= Router::url("projects/new?client_id={$clientId}") ?>" class="btn btn-success btn-sm">+ Proiect nou</a>
        <a href="<?= Router::url("clients/{$clientId}/edit") ?>" class="btn btn-outline btn-sm">Editează</a>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Ești sigur?')"><?= csrf() ?><input type="hidden" name="_action" value="delete_client"><input type="hidden" name="client_id" value="<?= $clientId ?>"><button type="submit" class="btn btn-danger btn-sm">Șterge</button></form>
    </div>
</div>
<div class="card mb-2"><div class="card-body"><div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
    <div><h2 style="font-size:22px;margin-bottom:8px;"><?= e($client['company_name']) ?></h2><div class="mb-1"><?= statusBadge($client['pipeline_status']) ?></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;font-size:13px;">
            <div><span class="text-muted">CUI:</span> <?= e($client['cui'] ?: '-') ?></div><div><span class="text-muted">Reg. Com.:</span> <?= e($client['reg_com'] ?: '-') ?></div>
            <div><span class="text-muted">Adresă:</span> <?= e($client['address'] ?: '-') ?></div><div><span class="text-muted">Oraș:</span> <?= e($client['city'] ?: '-') ?></div>
            <div><span class="text-muted">Telefon:</span> <?= e($client['phone'] ?: '-') ?></div><div><span class="text-muted">Email:</span> <?= $client['email'] ? '<a href="mailto:'.e($client['email']).'">'.e($client['email']).'</a>' : '-' ?></div>
            <div><span class="text-muted">Website:</span> <?= $client['website'] ? '<a href="'.e($client['website']).'" target="_blank">'.e($client['website']).'</a>' : '-' ?></div><div><span class="text-muted">Industrie:</span> <?= e($client['industry'] ?: '-') ?></div>
        </div>
        <?php if ($client['notes']): ?><div style="margin-top:12px;padding:12px;background:var(--bg-subtle);border-radius:var(--radius);font-size:13px;"><strong>Note:</strong> <?= nl2br(e($client['notes'])) ?></div><?php endif; ?>
    </div>
    <div><div class="stat-card"><div class="stat-label">Valoare estimată</div><div class="stat-value"><?= formatMoney($client['estimated_value']) ?></div><div class="stat-sub">Sursă: <?= e($client['source'] ?: '-') ?></div></div></div>
</div></div></div>
<div class="tabs">
    <div class="tab active" data-tab-btn="client" data-tab="contacts" onclick="switchTab('client','contacts')">Contacte (<?= count($contacts) ?>)</div>
    <div class="tab" data-tab-btn="client" data-tab="projects" onclick="switchTab('client','projects')">Proiecte (<?= count($projects) ?>)</div>
    <div class="tab" data-tab-btn="client" data-tab="offers" onclick="switchTab('client','offers')">Oferte (<?= count($offers) ?>)</div>
    <div class="tab" data-tab-btn="client" data-tab="emails" onclick="switchTab('client','emails')">Email-uri (<?= count($emails) ?>)</div>
    <div class="tab" data-tab-btn="client" data-tab="docs" onclick="switchTab('client','docs')">Documente (<?= count($documents) ?>)</div>
</div>
<div id="tab-client-contacts" data-tab-group="client"><div class="card"><div class="card-header"><h2>Persoane de contact</h2><button class="btn btn-primary btn-sm" onclick="openModal('modalContact')">+ Contact nou</button></div>
<?php if (empty($contacts)): ?><div class="empty-state"><p>Nicio persoană de contact.</p></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th>Nume</th><th>Funcție</th><th>Email</th><th>Telefon</th><th>Principal</th></tr></thead><tbody>
<?php foreach ($contacts as $ct): ?><tr><td><strong><?= e($ct['full_name']) ?></strong></td><td><?= e($ct['position'] ?: '-') ?></td><td><?= e($ct['email'] ?: '-') ?></td><td><?= e($ct['phone'] ?: '-') ?></td><td><?= $ct['is_primary'] ? '✅' : '' ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div>
<div id="tab-client-projects" data-tab-group="client" style="display:none"><div class="card"><div class="card-header"><h2>Proiecte</h2></div>
<?php if (empty($projects)): ?><div class="empty-state"><p>Niciun proiect.</p></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th>Proiect</th><th>Status</th><th>Deadline</th><th>Buget</th></tr></thead><tbody>
<?php foreach ($projects as $pr): ?><tr onclick="location.href='<?= Router::url("projects/{$pr['id']}") ?>'"><td><strong><?= e($pr['name']) ?></strong></td><td><?= statusBadge($pr['status']) ?></td><td><?= formatDate($pr['deadline']) ?></td><td><?= $pr['estimated_budget'] ? formatMoney($pr['estimated_budget']) : '-' ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div>
<div id="tab-client-offers" data-tab-group="client" style="display:none"><div class="card"><div class="card-header"><h2>Oferte</h2></div>
<?php if (empty($offers)): ?><div class="empty-state"><p>Nicio ofertă.</p></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th>Nr.</th><th>Data</th><th>Total</th><th>Status</th></tr></thead><tbody>
<?php foreach ($offers as $o): ?><tr onclick="location.href='<?= Router::url("offers/{$o['id']}") ?>'"><td><strong><?= e($o['offer_number'] ?: '#'.$o['id']) ?></strong></td><td><?= formatDate($o['offer_date']) ?></td><td><?= formatMoney($o['total']) ?></td><td><?= statusBadge($o['status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div>
<div id="tab-client-emails" data-tab-group="client" style="display:none"><div class="card"><div class="card-header"><h2>Email-uri trimise</h2></div>
<?php if (empty($emails)): ?><div class="empty-state"><p>Niciun email.</p></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th>Subiect</th><th>Către</th><th>Status</th><th>Deschis</th><th>Trimis</th></tr></thead><tbody>
<?php foreach ($emails as $em): ?><tr><td><?= e($em['subject'] ?: '(fără subiect)') ?></td><td><?= e($em['to_email']) ?></td><td><?= statusBadge($em['status']) ?></td><td><?= $em['opened'] ? '✅' : '—' ?></td><td class="text-muted"><?= formatDate($em['sent_at'] ?: $em['created_at'], 'd.m.Y H:i') ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div>
<div id="tab-client-docs" data-tab-group="client" style="display:none"><div class="card"><div class="card-header"><h2>NDA & Contracte</h2></div>
<?php if (empty($documents)): ?><div class="empty-state"><p>Niciun document.</p></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th>Nr.</th><th>Tip</th><th>Titlu</th><th>Status</th><th>Data</th></tr></thead><tbody>
<?php foreach ($documents as $d): ?><tr onclick="location.href='<?= Router::url("documents/{$d['id']}") ?>'"><td><strong><?= e($d['doc_number'] ?: '#'.$d['id']) ?></strong></td><td><?= strtoupper(e($d['doc_type'])) ?></td><td><?= e($d['title'] ?: '-') ?></td><td><?= statusBadge($d['status']) ?></td><td class="text-muted"><?= formatDate($d['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div>
<div class="modal-overlay" id="modalContact"><div class="modal"><div class="modal-header"><h3>Adaugă persoană de contact</h3><button class="btn btn-icon btn-outline" onclick="closeModal('modalContact')">✕</button></div>
<form method="POST" class="modal-body"><?= csrf() ?><input type="hidden" name="_action" value="save_contact"><input type="hidden" name="client_id" value="<?= $clientId ?>">
<div class="form-row"><div class="form-group"><label>Nume complet *</label><input type="text" name="full_name" class="form-control" required></div><div class="form-group"><label>Funcție</label><input type="text" name="position" class="form-control"></div></div>
<div class="form-row"><div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div><div class="form-group"><label>Telefon</label><input type="text" name="phone" class="form-control"></div></div>
<div class="form-group"><label><input type="checkbox" name="is_primary" value="1"> Contact principal</label></div>
<div class="form-group"><label>Note</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
<div style="display:flex;gap:8px;justify-content:flex-end;"><button type="button" class="btn btn-outline" onclick="closeModal('modalContact')">Anulează</button><button type="submit" class="btn btn-primary">Salvează</button></div>
</form></div></div>
<?php endif; ?>

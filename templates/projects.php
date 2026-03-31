<?php
$profileId = Auth::profileId();
$action    = $_GET['action'] ?? 'list';
$projectId = (int)($_GET['id'] ?? 0);
$pageTitle = 'Proiecte';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['_action'] ?? '';

    if ($pa === 'save_project') {
        $data = [
            'profile_id'       => $profileId,
            'client_id'        => (int)$_POST['client_id'],
            'name'             => trim($_POST['name'] ?? ''),
            'description'      => trim($_POST['description'] ?? ''),
            'status'           => $_POST['status'] ?? 'draft',
            'start_date'       => $_POST['start_date'] ?: null,
            'deadline'         => $_POST['deadline'] ?: null,
            'estimated_budget' => (float)($_POST['estimated_budget'] ?? 0),
            'priority'         => $_POST['priority'] ?? 'medium',
            'category'         => trim($_POST['category'] ?? ''),
            'brand'            => $_POST['brand'] ?? 'cybershield',
            'notes'            => trim($_POST['notes'] ?? ''),
        ];
        $editId = (int)($_POST['project_id'] ?? 0);
        if ($editId) {
            DB::update('projects', $data, 'id = ? AND profile_id = ?', [$editId, $profileId]);
            flash('success', 'Proiect actualizat.');
            Router::redirect('projects/' . $editId);
        } else {
            $newId = DB::insert('projects', $data);
            flash('success', 'Proiect creat.');
            Router::redirect('projects/' . $newId);
        }
    }

    if ($pa === 'save_service') {
        $sData = [
            'project_id'  => (int)$_POST['project_id'],
            'title'       => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'unit'        => trim($_POST['unit'] ?? 'serviciu'),
            'quantity'    => (float)($_POST['quantity'] ?? 1),
            'unit_price'  => (float)($_POST['unit_price'] ?? 0),
            'vat_rate'    => (float)($_POST['vat_rate'] ?? 19),
            'sort_order'  => (int)($_POST['sort_order'] ?? 0),
        ];
        $sid = (int)($_POST['service_id'] ?? 0);
        if ($sid) {
            DB::update('services', $sData, 'id = ?', [$sid]);
        } else {
            DB::insert('services', $sData);
        }
        flash('success', 'Serviciu salvat.');
        Router::redirect('projects/' . $sData['project_id']);
    }

    if ($pa === 'delete_service') {
        $sid  = (int)$_POST['service_id'];
        $proj = DB::fetchOne("SELECT project_id FROM services WHERE id = ?", [$sid]);
        DB::delete('services', 'id = ?', [$sid]);
        flash('success', 'Serviciu șters.');
        Router::redirect('projects/' . ($proj['project_id'] ?? 0));
    }

    if ($pa === 'upload_document') {
        $pid = (int)$_POST['project_id'];
        if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $path = uploadFile($_FILES['doc_file'], 'projects/' . $pid);
            if ($path) {
                DB::insert('project_documents', [
                    'project_id'  => $pid,
                    'file_name'   => $_FILES['doc_file']['name'],
                    'file_path'   => $path,
                    'file_type'   => $_FILES['doc_file']['type'],
                    'file_size'   => $_FILES['doc_file']['size'],
                    'description' => trim($_POST['doc_description'] ?? ''),
                ]);
            }
            flash('success', 'Document încărcat.');
        }
        Router::redirect('projects/' . $pid);
    }

    if ($pa === 'generate_offer') {
        $pid     = (int)$_POST['project_id'];
        $project = DB::fetchOne("SELECT * FROM projects WHERE id = ? AND profile_id = ?", [$pid, $profileId]);
        if ($project) {
            $services = DB::fetchAll("SELECT * FROM services WHERE project_id = ? ORDER BY sort_order", [$pid]);
            $subtotal = 0;
            $profile  = Auth::getProfile();
            $brand    = $project['brand'] ?? 'cybershield';
            $prefix   = $brand === 'wht' ? 'WHT' : 'CYB';
            $cnt      = DB::fetchOne("SELECT COUNT(*)+1 as n FROM offers WHERE profile_id = ?", [$profileId]);
            $offerNum = $prefix . '-' . date('Y') . '-' . str_pad($cnt['n'], 3, '0', STR_PAD_LEFT);
            $offerId  = DB::insert('offers', [
                'profile_id'   => $profileId,
                'project_id'   => $pid,
                'client_id'    => $project['client_id'],
                'offer_number' => $offerNum,
                'offer_date'   => date('Y-m-d'),
                'valid_until'  => date('Y-m-d', strtotime('+30 days')),
                'status'       => 'draft',
                'currency'     => 'EUR',
                'brand'        => $brand,
                'vat_rate'     => $profile['default_vat_rate'] ?? 0,
            ]);
            foreach ($services as $s) {
                $total = $s['quantity'] * $s['unit_price'];
                DB::insert('offer_items', [
                    'offer_id'    => $offerId,
                    'sort_order'  => $s['sort_order'],
                    'title'       => $s['title'],
                    'description' => $s['description'],
                    'unit'        => $s['unit'],
                    'quantity'    => $s['quantity'],
                    'unit_price'  => $s['unit_price'],
                    'total_price' => $total,
                ]);
                $subtotal += $total;
            }
            $vatRate   = $profile['default_vat_rate'] ?? 0;
            $vatAmount = $subtotal * ($vatRate / 100);
            DB::update('offers', ['subtotal' => $subtotal, 'vat_amount' => $vatAmount, 'total' => $subtotal + $vatAmount], 'id = ?', [$offerId]);
            flash('success', 'Ofertă generată.');
            Router::redirect('offers/' . $offerId);
        }
    }

    if ($pa === 'delete_project') {
        DB::delete('projects', 'id = ? AND profile_id = ?', [(int)$_POST['project_id'], $profileId]);
        flash('success', 'Proiect șters.');
        Router::redirect('projects');
    }
}

// ────────────────────────────────────────────
// LIST
// ────────────────────────────────────────────
if ($action === 'list'):
    $projects = DB::fetchAll("
        SELECT p.*, c.company_name
        FROM projects p
        JOIN clients c ON p.client_id = c.id
        WHERE p.profile_id = ?
        ORDER BY p.created_at DESC
    ", [$profileId]);
?>
<div class="flex-between mb-2">
  <div></div>
  <a href="<?= Router::url('projects/new') ?>" class="btn btn-primary">+ Proiect nou</a>
</div>
<div class="card">
<?php if (empty($projects)): ?>
  <div class="empty-state"><div class="empty-icon">📁</div><h3>Niciun proiect</h3></div>
<?php else: ?>
  <div class="table-wrap"><table><thead><tr>
    <th>Proiect</th><th>Brand</th><th>Client</th><th>Categorie</th>
    <th>Status</th><th>Prioritate</th><th>Deadline</th><th>Buget</th>
  </tr></thead><tbody>
  <?php foreach ($projects as $p):
      $brandLabel = ($p['brand'] ?? 'cybershield') === 'wht'
          ? '<span class="badge badge-blue">WHT</span>'
          : '<span class="badge badge-purple">CyberShield</span>';
  ?>
  <tr onclick="location.href='<?= Router::url("projects/{$p['id']}") ?>'">
    <td><strong><?= e($p['name']) ?></strong></td>
    <td><?= $brandLabel ?></td>
    <td><?= e($p['company_name']) ?></td>
    <td><?= e($p['category'] ?: '-') ?></td>
    <td><?= statusBadge($p['status']) ?></td>
    <td><?= statusBadge($p['priority']) ?></td>
    <td><?= formatDate($p['deadline']) ?></td>
    <td><?= $p['estimated_budget'] ? formatMoney($p['estimated_budget']) : '-' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
</div>

<?php
// ────────────────────────────────────────────
// NEW / EDIT FORM
// ────────────────────────────────────────────
elseif ($action === 'new' || $action === 'edit'):
    $project = null;
    if ($action === 'edit' && $projectId) {
        $project = DB::fetchOne("SELECT * FROM projects WHERE id = ? AND profile_id = ?", [$projectId, $profileId]);
    }
    $clients           = DB::fetchAll("SELECT id, company_name FROM clients WHERE profile_id = ? ORDER BY company_name", [$profileId]);
    $preselectedClient = (int)($_GET['client_id'] ?? ($project['client_id'] ?? 0));
?>
<div class="card" style="max-width:800px;"><div class="card-header"><h2><?= $project ? 'Editare proiect' : 'Proiect nou' ?></h2></div>
<form method="POST" class="card-body"><?= csrf() ?>
  <input type="hidden" name="_action" value="save_project">
  <input type="hidden" name="project_id" value="<?= $project['id'] ?? '' ?>">
  <div class="form-row">
    <div class="form-group">
      <label>Client *</label>
      <select name="client_id" class="form-control" required>
        <option value="">— Selectează —</option>
        <?php foreach ($clients as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id'] == $preselectedClient ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Brand emitent</label>
      <select name="brand" class="form-control">
        <option value="cybershield" <?= ($project['brand'] ?? 'cybershield') === 'cybershield' ? 'selected' : '' ?>>CyberShield</option>
        <option value="wht" <?= ($project['brand'] ?? '') === 'wht' ? 'selected' : '' ?>>White Hat Technology (WHT)</option>
      </select>
    </div>
    <div class="form-group">
      <label>Categorie</label>
      <select name="category" class="form-control">
        <option value="">—</option>
        <?php foreach (['audit', 'pentest', 'training', 'workshop', 'campaign', 'consulting', 'development', 'other'] as $cat): ?>
        <option value="<?= $cat ?>" <?= ($project['category'] ?? '') === $cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-group"><label>Denumire proiect *</label><input type="text" name="name" class="form-control" required value="<?= e($project['name'] ?? '') ?>"></div>
  <div class="form-group"><label>Descriere</label><textarea name="description" class="form-control" rows="4"><?= e($project['description'] ?? '') ?></textarea></div>
  <div class="form-row">
    <div class="form-group">
      <label>Status</label>
      <select name="status" class="form-control">
        <?php foreach (['draft', 'active', 'on_hold', 'completed', 'cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= ($project['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Prioritate</label>
      <select name="priority" class="form-control">
        <?php foreach (['low', 'medium', 'high', 'critical'] as $pr): ?>
        <option value="<?= $pr ?>" <?= ($project['priority'] ?? 'medium') === $pr ? 'selected' : '' ?>><?= ucfirst($pr) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Început</label><input type="date" name="start_date" class="form-control" value="<?= $project['start_date'] ?? '' ?>"></div>
    <div class="form-group"><label>Deadline</label><input type="date" name="deadline" class="form-control" value="<?= $project['deadline'] ?? '' ?>"></div>
    <div class="form-group"><label>Buget estimat (RON)</label><input type="number" name="estimated_budget" class="form-control" step="0.01" value="<?= $project['estimated_budget'] ?? '' ?>"></div>
  </div>
  <div class="form-group"><label>Note</label><textarea name="notes" class="form-control" rows="2"><?= e($project['notes'] ?? '') ?></textarea></div>
  <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;">
    <a href="<?= Router::url('projects') ?>" class="btn btn-outline">Anulează</a>
    <button type="submit" class="btn btn-primary">Salvează</button>
  </div>
</form></div>

<?php
// ────────────────────────────────────────────
// VIEW
// ────────────────────────────────────────────
elseif ($action === 'view' && $projectId):
    $project = DB::fetchOne("
        SELECT p.*, c.company_name, c.id as cid
        FROM projects p
        JOIN clients c ON p.client_id = c.id
        WHERE p.id = ? AND p.profile_id = ?
    ", [$projectId, $profileId]);
    if (!$project) { flash('error', 'Proiect negăsit.'); Router::redirect('projects'); }

    $services     = DB::fetchAll("SELECT * FROM services WHERE project_id = ? ORDER BY sort_order", [$projectId]);
    $docs         = DB::fetchAll("SELECT * FROM project_documents WHERE project_id = ? ORDER BY uploaded_at DESC", [$projectId]);
    $offers       = DB::fetchAll("SELECT * FROM offers WHERE project_id = ? ORDER BY created_at DESC", [$projectId]);
    $serviceTotals = array_sum(array_map(fn($s) => $s['quantity'] * $s['unit_price'], $services));
    $pageTitle    = $project['name'];
    $brand        = $project['brand'] ?? 'cybershield';
    $brandLabel   = $brand === 'wht'
        ? '<span class="badge badge-blue">WHT</span>'
        : '<span class="badge badge-purple">CyberShield</span>';
?>
<div class="flex-between mb-2">
  <a href="<?= Router::url('projects') ?>" class="text-muted" style="font-size:13px;">← Înapoi</a>
  <div style="display:flex;gap:8px;">
    <form method="POST" style="display:inline;"><?= csrf() ?>
      <input type="hidden" name="_action" value="generate_offer">
      <input type="hidden" name="project_id" value="<?= $projectId ?>">
      <button type="submit" class="btn btn-success btn-sm" <?= empty($services) ? 'disabled' : '' ?>>Generează ofertă</button>
    </form>
    <a href="<?= Router::url("projects/{$projectId}/edit") ?>" class="btn btn-outline btn-sm">Editează</a>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Sigur?')"><?= csrf() ?>
      <input type="hidden" name="_action" value="delete_project">
      <input type="hidden" name="project_id" value="<?= $projectId ?>">
      <button type="submit" class="btn btn-danger btn-sm">Șterge</button>
    </form>
  </div>
</div>

<div class="card mb-2"><div class="card-body">
  <div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;">
    <h2 style="font-size:20px;"><?= e($project['name']) ?></h2>
    <?= $brandLabel ?>
    <?= statusBadge($project['status']) ?>
    <?= statusBadge($project['priority']) ?>
  </div>
  <div style="font-size:13px;display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
    <div><span class="text-muted">Client:</span> <a href="<?= Router::url("clients/{$project['cid']}") ?>"><?= e($project['company_name']) ?></a></div>
    <div><span class="text-muted">Categorie:</span> <?= e($project['category'] ?: '-') ?></div>
    <div><span class="text-muted">Început:</span> <?= formatDate($project['start_date']) ?></div>
    <div><span class="text-muted">Deadline:</span> <?= formatDate($project['deadline']) ?></div>
  </div>
  <?php if ($project['description']): ?>
  <div style="margin-top:12px;padding:12px;background:var(--bg-subtle);border-radius:var(--radius);font-size:13px;"><?= nl2br(e($project['description'])) ?></div>
  <?php endif; ?>
</div></div>

<!-- Services -->
<div class="card mb-2">
  <div class="card-header">
    <h2>Servicii (<?= count($services) ?>) — Total: <?= formatMoney($serviceTotals) ?></h2>
    <button class="btn btn-primary btn-sm" onclick="openServiceModal()">+ Serviciu</button>
  </div>
  <?php if (!empty($services)): ?>
  <div class="table-wrap"><table><thead><tr><th>#</th><th>Serviciu</th><th>Cant.</th><th>Preț</th><th>Total</th><th></th></tr></thead><tbody>
  <?php foreach ($services as $i => $s): ?>
  <tr>
    <td><?= $i + 1 ?></td>
    <td><strong><?= e($s['title']) ?></strong><?php if ($s['description']): ?><br><span class="text-muted" style="font-size:12px;"><?= e($s['description']) ?></span><?php endif; ?></td>
    <td><?= $s['quantity'] ?> <?= e($s['unit']) ?></td>
    <td><?= formatMoney($s['unit_price']) ?></td>
    <td><strong><?= formatMoney($s['quantity'] * $s['unit_price']) ?></strong></td>
    <td style="display:flex;gap:4px;">
      <button class="btn btn-sm btn-outline btn-icon" onclick="openServiceModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)" title="Editează">✏️</button>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Ștergi?')"><?= csrf() ?>
        <input type="hidden" name="_action" value="delete_service">
        <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
        <button type="submit" class="btn btn-sm btn-danger btn-icon">✕</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php else: ?><div class="empty-state"><p>Adaugă servicii pentru a genera o ofertă.</p></div><?php endif; ?>
</div>

<!-- Documente -->
<div class="card mb-2">
  <div class="card-header"><h2>Documente (<?= count($docs) ?>)</h2><button class="btn btn-outline btn-sm" onclick="openModal('modalDoc')">+ Încarcă</button></div>
  <?php if (!empty($docs)): ?>
  <div class="table-wrap"><table><thead><tr><th>Fișier</th><th>Descriere</th><th>Data</th></tr></thead><tbody>
  <?php foreach ($docs as $d): ?>
  <tr>
    <td><a href="<?= Router::url('storage/uploads/' . e($d['file_path'])) ?>" target="_blank"><?= e($d['file_name']) ?></a></td>
    <td class="text-muted"><?= e($d['description'] ?: '-') ?></td>
    <td class="text-muted"><?= formatDate($d['uploaded_at']) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php else: ?><div class="empty-state"><p>Niciun document.</p></div><?php endif; ?>
</div>

<!-- Oferte asociate -->
<?php if (!empty($offers)): ?>
<div class="card">
  <div class="card-header"><h2>Oferte (<?= count($offers) ?>)</h2></div>
  <div class="table-wrap"><table><thead><tr><th>Nr.</th><th>Brand</th><th>Data</th><th>Total</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($offers as $o):
      $oBrand = ($o['brand'] ?? 'cybershield') === 'wht'
          ? '<span class="badge badge-blue">WHT</span>'
          : '<span class="badge badge-purple">CyberShield</span>';
  ?>
  <tr onclick="location.href='<?= Router::url("offers/{$o['id']}") ?>'">
    <td><strong><?= e($o['offer_number'] ?: '#' . $o['id']) ?></strong></td>
    <td><?= $oBrand ?></td>
    <td><?= formatDate($o['offer_date']) ?></td>
    <td><?= formatMoney($o['total'], $o['currency'] ?? 'EUR') ?></td>
    <td><?= statusBadge($o['status']) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<!-- Modal: Add/Edit Service -->
<div class="modal-overlay" id="modalService"><div class="modal">
  <div class="modal-header"><h3 id="serviceModalTitle">Adaugă serviciu</h3><button class="btn btn-icon btn-outline" onclick="closeModal('modalService')">✕</button></div>
  <form method="POST" class="modal-body"><?= csrf() ?>
    <input type="hidden" name="_action" value="save_service">
    <input type="hidden" name="project_id" value="<?= $projectId ?>">
    <input type="hidden" name="service_id" id="editServiceId" value="">
    <div class="form-group"><label>Titlu *</label><input type="text" name="title" id="svcTitle" class="form-control" required></div>
    <div class="form-group"><label>Descriere</label><textarea name="description" id="svcDesc" class="form-control" rows="3"></textarea></div>
    <div class="form-row">
      <div class="form-group"><label>Cantitate</label><input type="number" name="quantity" id="svcQty" class="form-control" value="1" step="0.01"></div>
      <div class="form-group"><label>Unitate</label><input type="text" name="unit" id="svcUnit" class="form-control" value="serviciu"></div>
      <div class="form-group"><label>Preț unitar (RON)</label><input type="number" name="unit_price" id="svcPrice" class="form-control" step="0.01" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>TVA %</label><input type="number" name="vat_rate" id="svcVat" class="form-control" value="19"></div>
      <div class="form-group"><label>Ordine</label><input type="number" name="sort_order" id="svcOrder" class="form-control" value="<?= count($services) + 1 ?>"></div>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;">
      <button type="button" class="btn btn-outline" onclick="closeModal('modalService')">Anulează</button>
      <button type="submit" class="btn btn-primary" id="svcSubmitBtn">Salvează</button>
    </div>
  </form>
</div></div>

<!-- Modal: Upload Doc -->
<div class="modal-overlay" id="modalDoc"><div class="modal">
  <div class="modal-header"><h3>Încarcă document</h3><button class="btn btn-icon btn-outline" onclick="closeModal('modalDoc')">✕</button></div>
  <form method="POST" enctype="multipart/form-data" class="modal-body"><?= csrf() ?>
    <input type="hidden" name="_action" value="upload_document">
    <input type="hidden" name="project_id" value="<?= $projectId ?>">
    <div class="form-group"><label>Fișier *</label><input type="file" name="doc_file" class="form-control" required></div>
    <div class="form-group"><label>Descriere</label><input type="text" name="doc_description" class="form-control"></div>
    <div style="display:flex;gap:8px;justify-content:flex-end;">
      <button type="button" class="btn btn-outline" onclick="closeModal('modalDoc')">Anulează</button>
      <button type="submit" class="btn btn-primary">Încarcă</button>
    </div>
  </form>
</div></div>

<script>
function openServiceModal(svc) {
  const isEdit = svc && svc.id;
  document.getElementById('serviceModalTitle').textContent = isEdit ? 'Editează serviciu' : 'Adaugă serviciu';
  document.getElementById('editServiceId').value = isEdit ? svc.id : '';
  document.getElementById('svcTitle').value  = isEdit ? svc.title : '';
  document.getElementById('svcDesc').value   = isEdit ? (svc.description || '') : '';
  document.getElementById('svcQty').value    = isEdit ? svc.quantity : 1;
  document.getElementById('svcUnit').value   = isEdit ? (svc.unit || 'serviciu') : 'serviciu';
  document.getElementById('svcPrice').value  = isEdit ? svc.unit_price : '';
  document.getElementById('svcVat').value    = isEdit ? (svc.vat_rate ?? 19) : 19;
  document.getElementById('svcOrder').value  = isEdit ? (svc.sort_order ?? 0) : <?= count($services) + 1 ?>;
  document.getElementById('svcSubmitBtn').textContent = isEdit ? 'Salvează modificările' : 'Salvează';
  openModal('modalService');
}
</script>

<?php endif; ?>

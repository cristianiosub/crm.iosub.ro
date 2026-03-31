<?php
$profileId = Auth::profileId();
$action = $_GET['action'] ?? 'list';
$offerId = (int)($_GET['id'] ?? 0);
$pageTitle = 'Oferte';

// ── EUR → RON exchange rate (fallback static, override via DB setting) ──
$eurRon = 5.00; // Curs EUR/RON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['_action'] ?? '';

    // ── Update offer header ──
    if ($pa === 'update_offer') {
        $oid = (int)$_POST['offer_id'];
        $data = [
            'offer_number'   => trim($_POST['offer_number'] ?? ''),
            'offer_date'     => $_POST['offer_date'] ?: date('Y-m-d'),
            'valid_until'    => $_POST['valid_until'] ?: null,
            'status'         => $_POST['status'] ?? 'draft',
            'currency'       => $_POST['currency'] ?? 'EUR',
            'vat_rate'       => (float)($_POST['vat_rate'] ?? 0),
            'intro_text'     => trim($_POST['intro_text'] ?? ''),
            'outro_text'     => trim($_POST['outro_text'] ?? ''),
            'terms_text'     => trim($_POST['terms_text'] ?? ''),
            'methodology_text' => trim($_POST['methodology_text'] ?? ''),
            'deliverables_text' => trim($_POST['deliverables_text'] ?? ''),
            'brand'          => $_POST['brand'] ?? 'cybershield',
        ];
        DB::update('offers', $data, 'id = ? AND profile_id = ?', [$oid, $profileId]);
        // recalc totals
        $items = DB::fetchAll("SELECT * FROM offer_items WHERE offer_id = ?", [$oid]);
        $subtotal = array_sum(array_map(fn($i) => $i['total_price'], $items));
        $vatAmount = $subtotal * ($data['vat_rate'] / 100);
        DB::update('offers', ['subtotal' => $subtotal, 'vat_amount' => $vatAmount, 'total' => $subtotal + $vatAmount], 'id = ?', [$oid]);
        flash('success', 'Oferta actualizată.');
        Router::redirect('offers/' . $oid);
    }

    // ── Save offer item (add or edit) ──
    if ($pa === 'save_offer_item') {
        $oid  = (int)$_POST['offer_id'];
        $iid  = (int)($_POST['item_id'] ?? 0);
        $qty  = (float)$_POST['quantity'];
        $price = (float)$_POST['unit_price'];
        $iData = [
            'offer_id'    => $oid,
            'title'       => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'unit'        => trim($_POST['unit'] ?? 'serviciu'),
            'quantity'    => $qty,
            'unit_price'  => $price,
            'total_price' => $qty * $price,
            'sort_order'  => (int)($_POST['sort_order'] ?? 0),
        ];
        if ($iid) {
            DB::update('offer_items', $iData, 'id = ?', [$iid]);
        } else {
            DB::insert('offer_items', $iData);
        }
        // recalc
        $offer = DB::fetchOne("SELECT vat_rate FROM offers WHERE id = ?", [$oid]);
        $items = DB::fetchAll("SELECT total_price FROM offer_items WHERE offer_id = ?", [$oid]);
        $subtotal = array_sum(array_column($items, 'total_price'));
        $vat = $subtotal * (($offer['vat_rate'] ?? 0) / 100);
        DB::update('offers', ['subtotal' => $subtotal, 'vat_amount' => $vat, 'total' => $subtotal + $vat], 'id = ?', [$oid]);
        flash('success', 'Serviciu salvat.');
        Router::redirect('offers/' . $oid);
    }

    // ── Delete offer item ──
    if ($pa === 'delete_offer_item') {
        $iid  = (int)$_POST['item_id'];
        $item = DB::fetchOne("SELECT offer_id FROM offer_items WHERE id = ?", [$iid]);
        DB::delete('offer_items', 'id = ?', [$iid]);
        if ($item) {
            $oid   = $item['offer_id'];
            $offer = DB::fetchOne("SELECT vat_rate FROM offers WHERE id = ?", [$oid]);
            $items = DB::fetchAll("SELECT total_price FROM offer_items WHERE offer_id = ?", [$oid]);
            $subtotal = array_sum(array_column($items, 'total_price'));
            $vat = $subtotal * (($offer['vat_rate'] ?? 0) / 100);
            DB::update('offers', ['subtotal' => $subtotal, 'vat_amount' => $vat, 'total' => $subtotal + $vat], 'id = ?', [$oid]);
            Router::redirect('offers/' . $oid);
        }
        Router::redirect('offers');
    }

    // ── Delete offer ──
    if ($pa === 'delete_offer') {
        DB::delete('offers', 'id = ? AND profile_id = ?', [(int)$_POST['offer_id'], $profileId]);
        flash('success', 'Ofertă ștearsă.');
        Router::redirect('offers');
    }

    // ── Create offer ──
    if ($pa === 'create_offer') {
        $cid      = (int)$_POST['client_id'];
        $pid      = (int)($_POST['project_id'] ?? 0) ?: null;
        $vatRate  = (float)($_POST['vat_rate'] ?? 0);
        $currency = $_POST['currency'] ?? 'EUR';
        $brand    = $_POST['brand'] ?? 'cybershield';
        $offerNum = trim($_POST['offer_number'] ?? '');
        if (empty($offerNum)) {
            $cnt      = DB::fetchOne("SELECT COUNT(*)+1 as n FROM offers WHERE profile_id = ?", [$profileId]);
            $prefix   = ($brand === 'wht') ? 'WHT' : 'CYB';
            $offerNum = $prefix . '-' . date('Y') . '-' . str_pad($cnt['n'], 3, '0', STR_PAD_LEFT);
        }
        $newId = DB::insert('offers', [
            'profile_id'  => $profileId,
            'client_id'   => $cid,
            'project_id'  => $pid,
            'offer_number' => $offerNum,
            'offer_date'  => date('Y-m-d'),
            'valid_until' => date('Y-m-d', strtotime('+30 days')),
            'status'      => 'draft',
            'currency'    => $currency,
            'vat_rate'    => $vatRate,
            'subtotal'    => 0,
            'vat_amount'  => 0,
            'total'       => 0,
            'brand'       => $brand,
            'intro_text'  => trim($_POST['intro_text'] ?? ''),
        ]);
        flash('success', 'Ofertă creată. Adaugă servicii.');
        Router::redirect('offers/' . $newId);
    }

    // ── Upload attachment to offer ──
    if ($pa === 'upload_offer_attachment') {
        $oid = (int)$_POST['offer_id'];
        if (isset($_FILES['att_file']) && $_FILES['att_file']['error'] === UPLOAD_ERR_OK) {
            // Use security helper if available, otherwise raw move
            $uploadDir = UPLOAD_PATH . '/offers/' . $oid;
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $origName = basename($_FILES['att_file']['name']);
            $safeName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $origName);
            $safeName = time() . '_' . $safeName;
            $dest = $uploadDir . '/' . $safeName;
            if (move_uploaded_file($_FILES['att_file']['tmp_name'], $dest)) {
                DB::insert('offer_attachments', [
                    'offer_id'    => $oid,
                    'file_name'   => $origName,
                    'file_path'   => 'offers/' . $oid . '/' . $safeName,
                    'file_size'   => $_FILES['att_file']['size'],
                    'file_type'   => $_FILES['att_file']['type'],
                    'description' => trim($_POST['att_description'] ?? ''),
                ]);
                flash('success', 'Atașament încărcat.');
            } else {
                flash('error', 'Eroare la încărcare.');
            }
        }
        Router::redirect('offers/' . $oid);
    }

    // ── Delete attachment ──
    if ($pa === 'delete_offer_attachment') {
        $aid = (int)$_POST['att_id'];
        $att = DB::fetchOne("SELECT oa.*, o.profile_id FROM offer_attachments oa JOIN offers o ON oa.offer_id = o.id WHERE oa.id = ?", [$aid]);
        if ($att && $att['profile_id'] == $profileId) {
            $fullPath = UPLOAD_PATH . '/' . $att['file_path'];
            if (file_exists($fullPath)) @unlink($fullPath);
            DB::delete('offer_attachments', 'id = ?', [$aid]);
            flash('success', 'Atașament șters.');
            Router::redirect('offers/' . $att['offer_id']);
        }
        Router::redirect('offers');
    }
}

// ────────────────────────────────────────────
// LIST
// ────────────────────────────────────────────
if ($action === 'list'):
    $offers = DB::fetchAll("
        SELECT o.*, c.company_name, p.name as project_name
        FROM offers o
        JOIN clients c ON o.client_id = c.id
        LEFT JOIN projects p ON o.project_id = p.id
        WHERE o.profile_id = ?
        ORDER BY o.created_at DESC
    ", [$profileId]);
    $clients = DB::fetchAll("SELECT id, company_name FROM clients WHERE profile_id = ? ORDER BY company_name", [$profileId]);
?>
<div class="flex-between mb-2">
  <div></div>
  <button class="btn btn-primary" onclick="openModal('modalNewOffer')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Generează Ofertă
  </button>
</div>
<div class="card">
<?php if (empty($offers)): ?>
  <div class="empty-state"><div class="empty-icon">📄</div><h3>Nicio ofertă</h3><p>Apasă "Generează Ofertă" pentru a crea prima ofertă.</p></div>
<?php else: ?>
  <div class="table-wrap"><table><thead><tr>
    <th>Nr.</th><th>Brand</th><th>Client</th><th>Proiect</th><th>Data</th>
    <th>Subtotal</th><th>Total</th><th>Status</th>
  </tr></thead><tbody>
  <?php foreach ($offers as $o):
      $brandLabel = ($o['brand'] ?? 'cybershield') === 'wht' ? '<span class="badge badge-blue">WHT</span>' : '<span class="badge badge-purple">CyberShield</span>';
  ?>
  <tr onclick="location.href='<?= Router::url("offers/{$o['id']}") ?>'" style="cursor:pointer;">
    <td><strong><?= e($o['offer_number'] ?: '#' . $o['id']) ?></strong></td>
    <td><?= $brandLabel ?></td>
    <td><?= e($o['company_name']) ?></td>
    <td class="text-muted"><?= e($o['project_name'] ?: '-') ?></td>
    <td><?= formatDate($o['offer_date']) ?></td>
    <td><?= formatMoney($o['subtotal'], $o['currency'] ?? 'EUR') ?></td>
    <td><strong><?= formatMoney($o['total'], $o['currency'] ?? 'EUR') ?></strong></td>
    <td><?= statusBadge($o['status']) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
</div>

<!-- Modal: Ofertă nouă -->
<div class="modal-overlay" id="modalNewOffer"><div class="modal" style="max-width:580px;">
  <div class="modal-header"><h3>Ofertă nouă</h3><button class="btn btn-icon btn-outline" onclick="closeModal('modalNewOffer')">✕</button></div>
  <form method="POST" class="modal-body"><?= csrf() ?><input type="hidden" name="_action" value="create_offer">
    <div class="form-group">
      <label>Client *</label>
      <select name="client_id" class="form-control" required>
        <option value="">— Selectează client —</option>
        <?php foreach ($clients as $cl): ?>
        <option value="<?= $cl['id'] ?>"><?= e($cl['company_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Brand emitent</label>
        <select name="brand" class="form-control" id="newOfferBrand" onchange="updateOfferNumberPrefix()">
          <option value="cybershield">CyberShield</option>
          <option value="wht">White Hat Technology (WHT)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Monedă</label>
        <select name="currency" class="form-control">
          <option value="EUR" selected>EUR</option>
          <option value="RON">RON</option>
        </select>
      </div>
      <div class="form-group">
        <label>TVA %</label>
        <select name="vat_rate" class="form-control">
          <option value="0">0%</option>
          <option value="19" selected>19%</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Nr. ofertă</label>
      <input type="text" name="offer_number" id="newOfferNum" class="form-control" placeholder="Auto-generat (ex: CYB-2025-001)">
    </div>
    <div class="form-group">
      <label>Descriere scurtă / obiect ofertă</label>
      <textarea name="intro_text" class="form-control" rows="3" placeholder="Servicii de securitate cibernetică pentru..."></textarea>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
      <button type="button" class="btn btn-outline" onclick="closeModal('modalNewOffer')">Anulează</button>
      <button type="submit" class="btn btn-primary">Creează oferta →</button>
    </div>
  </form>
</div></div>
<script>
function updateOfferNumberPrefix() {
  const brand = document.getElementById('newOfferBrand')?.value;
  const field = document.getElementById('newOfferNum');
  if (field && !field.value) {
    // placeholder hint only
    field.placeholder = (brand === 'wht' ? 'WHT' : 'CYB') + '-<?= date('Y') ?>-001 (auto-generat)';
  }
}
</script>

<?php
// ────────────────────────────────────────────
// VIEW / EDIT
// ────────────────────────────────────────────
elseif (($action === 'view' || $action === 'preview') && $offerId):
    if ($action === 'preview') { header('Location: ' . Router::url("api/export-pdf/{$offerId}")); exit; }

    $offer = DB::fetchOne("
        SELECT o.*, c.company_name, c.cui as client_cui, c.address as client_address,
               c.reg_com as client_reg_com, c.email as client_email,
               p.name as project_name
        FROM offers o
        JOIN clients c ON o.client_id = c.id
        LEFT JOIN projects p ON o.project_id = p.id
        WHERE o.id = ? AND o.profile_id = ?
    ", [$offerId, $profileId]);
    if (!$offer) { flash('error', 'Ofertă negăsită.'); Router::redirect('offers'); }

    $items       = DB::fetchAll("SELECT * FROM offer_items WHERE offer_id = ? ORDER BY sort_order, id", [$offerId]);
    $attachments = DB::fetchAll("SELECT * FROM offer_attachments WHERE offer_id = ? ORDER BY uploaded_at DESC", [$offerId]);
    $profile     = Auth::getProfile();
    $pageTitle   = 'Oferta ' . ($offer['offer_number'] ?: '#' . $offer['id']);
    $currency    = $offer['currency'] ?? 'EUR';
    $brand       = $offer['brand'] ?? 'cybershield';
    $brandLabel  = $brand === 'wht' ? '<span class="badge badge-blue">WHT</span>' : '<span class="badge badge-purple">CyberShield</span>';

    // EUR→RON display
    $showRon = ($currency === 'EUR');
?>
<div class="flex-between mb-2">
  <a href="<?= Router::url('offers') ?>" class="text-muted" style="font-size:13px;">← Înapoi la oferte</a>
  <div style="display:flex;gap:8px;">
    <a href="<?= Router::url("api/export-pdf/{$offerId}") ?>" class="btn btn-success btn-sm" target="_blank">Previzualizare / PDF</a>
    <button class="btn btn-outline btn-sm" onclick="openModal('modalEditOffer')">Editează oferta</button>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Ești sigur că vrei să ștergi această ofertă?')">
      <?= csrf() ?><input type="hidden" name="_action" value="delete_offer"><input type="hidden" name="offer_id" value="<?= $offerId ?>">
      <button type="submit" class="btn btn-danger btn-sm">Șterge</button>
    </form>
  </div>
</div>

<!-- Offer summary card -->
<div class="card mb-2"><div class="card-body"><div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
  <div style="font-size:13px;">
    <h2 style="margin-bottom:12px;font-size:20px;">
      <?= e($offer['offer_number'] ?: 'Oferta #' . $offer['id']) ?>
      <?= $brandLabel ?>
      <?= statusBadge($offer['status']) ?>
    </h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <div><span class="text-muted">Client:</span> <a href="<?= Router::url("clients/{$offer['client_id']}") ?>"><?= e($offer['company_name']) ?></a></div>
      <div><span class="text-muted">Proiect:</span> <?= $offer['project_name'] ? '<a href="' . Router::url("projects/{$offer['project_id']}") . '">' . e($offer['project_name']) . '</a>' : '-' ?></div>
      <div><span class="text-muted">Data ofertă:</span> <?= formatDate($offer['offer_date']) ?></div>
      <div><span class="text-muted">Valabilă până:</span> <?= formatDate($offer['valid_until']) ?></div>
      <div><span class="text-muted">Monedă:</span> <?= e($currency) ?></div>
      <div><span class="text-muted">TVA:</span> <?= $offer['vat_rate'] ?>%</div>
    </div>
    <?php if ($offer['intro_text']): ?>
    <div style="margin-top:12px;padding:12px;background:var(--bg-subtle);border-radius:var(--radius);font-size:12px;">
      <strong>Obiect:</strong> <?= nl2br(e(mb_substr($offer['intro_text'], 0, 300))) ?><?= mb_strlen($offer['intro_text']) > 300 ? '...' : '' ?>
    </div>
    <?php endif; ?>
  </div>
  <div>
    <div style="background:var(--bg-subtle);padding:16px;border-radius:var(--radius);text-align:right;">
      <div class="text-muted" style="font-size:12px;">SUBTOTAL</div>
      <div style="font-size:18px;font-weight:600;"><?= formatMoney($offer['subtotal'], $currency) ?></div>
      <?php if ($showRon): ?>
      <div style="font-size:11px;color:var(--text-muted);">≈ <?= formatMoney(round($offer['subtotal'] * $eurRon, 2)) ?> RON</div>
      <?php endif; ?>
      <div class="text-muted" style="font-size:12px;margin-top:8px;">TVA (<?= $offer['vat_rate'] ?>%)</div>
      <div style="font-size:14px;"><?= formatMoney($offer['vat_amount'], $currency) ?></div>
      <?php if ($showRon): ?>
      <div style="font-size:11px;color:var(--text-muted);">≈ <?= formatMoney(round($offer['vat_amount'] * $eurRon, 2)) ?> RON</div>
      <?php endif; ?>
      <div style="border-top:2px solid var(--primary);margin-top:8px;padding-top:8px;">
        <div class="text-muted" style="font-size:12px;">TOTAL</div>
        <div style="font-size:24px;font-weight:700;color:var(--primary);"><?= formatMoney($offer['total'], $currency) ?></div>
        <?php if ($showRon): ?>
        <div style="font-size:12px;color:var(--text-muted);font-weight:500;">≈ <?= formatMoney(round($offer['total'] * $eurRon, 2)) ?> RON</div>
        <?php endif; ?>
      </div>
      <div style="margin-top:8px;font-size:10px;color:var(--text-muted);">Curs EUR/RON: <?= number_format($eurRon, 4) ?></div>
    </div>
  </div>
</div></div></div>

<!-- Services list -->
<div class="card mb-2">
  <div class="card-header">
    <h2>Servicii (<?= count($items) ?>)</h2>
    <button class="btn btn-primary btn-sm" onclick="openOfferItemModal()">+ Adaugă serviciu</button>
  </div>
  <?php if (empty($items)): ?>
    <div class="empty-state"><p>Niciun serviciu adăugat încă.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table><thead><tr>
    <th style="width:40px">#</th><th>Serviciu</th>
    <th style="width:70px">Cant.</th>
    <th style="width:140px">Preț unitar</th>
    <th style="width:140px">Total</th>
    <th style="width:70px"></th>
  </tr></thead><tbody>
  <?php foreach ($items as $i => $it): ?>
  <tr>
    <td><?= $i + 1 ?></td>
    <td>
      <strong><?= e($it['title']) ?></strong>
      <?php if ($it['description']): ?><br><span class="text-muted" style="font-size:12px;"><?= e($it['description']) ?></span><?php endif; ?>
    </td>
    <td><?= $it['quantity'] ?> <?= e($it['unit'] ?? '') ?></td>
    <td>
      <?= formatMoney($it['unit_price'], $currency) ?>
      <?php if ($showRon): ?><br><span style="font-size:11px;color:var(--text-muted);">≈ <?= formatMoney(round($it['unit_price'] * $eurRon, 2)) ?> RON</span><?php endif; ?>
    </td>
    <td>
      <strong><?= formatMoney($it['total_price'], $currency) ?></strong>
      <?php if ($showRon): ?><br><span style="font-size:11px;color:var(--text-muted);">≈ <?= formatMoney(round($it['total_price'] * $eurRon, 2)) ?> RON</span><?php endif; ?>
    </td>
    <td style="display:flex;gap:4px;">
      <button class="btn btn-sm btn-outline btn-icon" onclick="openOfferItemModal(<?= htmlspecialchars(json_encode($it), ENT_QUOTES) ?>)" title="Editează">✏️</button>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Ștergi serviciul?')">
        <?= csrf() ?><input type="hidden" name="_action" value="delete_offer_item"><input type="hidden" name="item_id" value="<?= $it['id'] ?>">
        <button type="submit" class="btn btn-sm btn-outline btn-icon" style="color:var(--red);border-color:var(--red);" title="Șterge">✕</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <tr style="background:var(--bg-subtle);">
    <td></td><td style="text-align:right;font-weight:700;" colspan="2">SUBTOTAL</td>
    <td></td>
    <td>
      <strong><?= formatMoney($offer['subtotal'], $currency) ?></strong>
      <?php if ($showRon): ?><br><span style="font-size:11px;color:var(--text-muted);">≈ <?= formatMoney(round($offer['subtotal'] * $eurRon, 2)) ?> RON</span><?php endif; ?>
    </td>
    <td></td>
  </tr>
  </tbody></table></div>
  <?php endif; ?>
</div>

<!-- Attachments -->
<div class="card mb-2">
  <div class="card-header">
    <h2>Atașamente (<?= count($attachments) ?>)</h2>
    <button class="btn btn-outline btn-sm" onclick="openModal('modalAttachment')">+ Adaugă atașament</button>
  </div>
  <?php if (empty($attachments)): ?>
    <div class="empty-state"><p>Niciun atașament (caiet de sarcini, specificații, etc.)</p></div>
  <?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Fișier</th><th>Descriere</th><th>Dimensiune</th><th>Data</th><th></th></tr></thead><tbody>
  <?php foreach ($attachments as $att): ?>
  <tr>
    <td>
      <a href="<?= e(APP_URL . '/storage/uploads/' . $att['file_path']) ?>" target="_blank" style="display:flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <?= e($att['file_name']) ?>
      </a>
    </td>
    <td class="text-muted"><?= e($att['description'] ?: '-') ?></td>
    <td class="text-muted"><?= $att['file_size'] > 0 ? number_format($att['file_size'] / 1024, 1) . ' KB' : '-' ?></td>
    <td class="text-muted"><?= formatDate($att['uploaded_at']) ?></td>
    <td>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Ștergi atașamentul?')">
        <?= csrf() ?><input type="hidden" name="_action" value="delete_offer_attachment"><input type="hidden" name="att_id" value="<?= $att['id'] ?>">
        <button type="submit" class="btn btn-sm btn-outline btn-icon" style="color:var(--red);border-color:var(--red);" title="Șterge">✕</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>

<!-- Content sections preview -->
<?php if ($offer['deliverables_text'] || $offer['methodology_text'] || $offer['terms_text']): ?>
<div class="card mb-2"><div class="card-header"><h2>Conținut ofertă</h2></div><div class="card-body" style="font-size:13px;">
  <?php if ($offer['deliverables_text']): ?>
  <div style="margin-bottom:16px;"><strong style="color:var(--primary);">Livrabile:</strong><div class="text-muted" style="margin-top:4px;"><?= nl2br(e(mb_substr($offer['deliverables_text'], 0, 500))) ?></div></div>
  <?php endif; ?>
  <?php if ($offer['methodology_text']): ?>
  <div style="margin-bottom:16px;"><strong style="color:var(--primary);">Metodologie:</strong><div class="text-muted" style="margin-top:4px;"><?= nl2br(e(mb_substr($offer['methodology_text'], 0, 500))) ?></div></div>
  <?php endif; ?>
  <?php if ($offer['terms_text']): ?>
  <div><strong style="color:var(--primary);">Condiții:</strong><div class="text-muted" style="margin-top:4px;"><?= nl2br(e(mb_substr($offer['terms_text'], 0, 500))) ?></div></div>
  <?php endif; ?>
</div></div>
<?php endif; ?>

<!-- Modal: Edit Offer -->
<div class="modal-overlay" id="modalEditOffer"><div class="modal" style="max-width:780px;">
  <div class="modal-header"><h3>Editează oferta</h3><button class="btn btn-icon btn-outline" onclick="closeModal('modalEditOffer')">✕</button></div>
  <form method="POST" class="modal-body"><?= csrf() ?><input type="hidden" name="_action" value="update_offer"><input type="hidden" name="offer_id" value="<?= $offerId ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Nr. ofertă</label>
        <input type="text" name="offer_number" class="form-control" value="<?= e($offer['offer_number'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Brand emitent</label>
        <select name="brand" class="form-control">
          <option value="cybershield" <?= ($offer['brand'] ?? '') === 'cybershield' ? 'selected' : '' ?>>CyberShield</option>
          <option value="wht" <?= ($offer['brand'] ?? '') === 'wht' ? 'selected' : '' ?>>White Hat Technology (WHT)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
          <?php foreach (['draft', 'sent', 'accepted', 'rejected', 'expired'] as $s): ?>
          <option value="<?= $s ?>" <?= $offer['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Monedă</label>
        <select name="currency" class="form-control">
          <option value="EUR" <?= $currency === 'EUR' ? 'selected' : '' ?>>EUR</option>
          <option value="RON" <?= $currency === 'RON' ? 'selected' : '' ?>>RON</option>
        </select>
      </div>
      <div class="form-group">
        <label>Data ofertă</label>
        <input type="date" name="offer_date" class="form-control" value="<?= $offer['offer_date'] ?>">
      </div>
      <div class="form-group">
        <label>Valabilă până la</label>
        <input type="date" name="valid_until" class="form-control" value="<?= $offer['valid_until'] ?? '' ?>">
      </div>
      <div class="form-group">
        <label>TVA %</label>
        <input type="number" name="vat_rate" class="form-control" step="0.01" value="<?= $offer['vat_rate'] ?>">
      </div>
    </div>
    <div class="form-group"><label>Obiectul ofertei (text introductiv)</label><textarea name="intro_text" class="form-control" rows="4"><?= e($offer['intro_text'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Livrabile incluse</label><textarea name="deliverables_text" class="form-control" rows="4"><?= e($offer['deliverables_text'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Metodologie și standarde</label><textarea name="methodology_text" class="form-control" rows="4"><?= e($offer['methodology_text'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Condiții comerciale</label><textarea name="terms_text" class="form-control" rows="4"><?= e($offer['terms_text'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Text final / outro</label><textarea name="outro_text" class="form-control" rows="2"><?= e($offer['outro_text'] ?? '') ?></textarea></div>
    <div style="display:flex;gap:8px;justify-content:flex-end;">
      <button type="button" class="btn btn-outline" onclick="closeModal('modalEditOffer')">Anulează</button>
      <button type="submit" class="btn btn-primary">Salvează modificările</button>
    </div>
  </form>
</div></div>

<!-- Modal: Add/Edit Offer Item -->
<div class="modal-overlay" id="modalOfferItem"><div class="modal"><div class="modal-header"><h3 id="offerItemModalTitle">Adaugă serviciu în ofertă</h3><button class="btn btn-icon btn-outline" onclick="closeModal('modalOfferItem')">✕</button></div>
  <form method="POST" class="modal-body"><?= csrf() ?>
    <input type="hidden" name="_action" value="save_offer_item">
    <input type="hidden" name="offer_id" value="<?= $offerId ?>">
    <input type="hidden" name="item_id" id="offerItemId" value="">
    <div class="form-group">
      <label>Selectează din nomenclator</label>
      <select id="catalogSelect" class="form-control" onchange="fillFromCatalog(this)">
        <option value="">— Scrie manual sau selectează —</option>
      </select>
    </div>
    <div class="form-group"><label>Titlu serviciu *</label><input type="text" name="title" id="itemTitle" class="form-control" required></div>
    <div class="form-group"><label>Descriere</label><textarea name="description" id="itemDesc" class="form-control" rows="2"></textarea></div>
    <div class="form-row">
      <div class="form-group"><label>Cantitate</label><input type="number" name="quantity" id="itemQty" class="form-control" value="1" step="0.01" oninput="calcItemTotal()"></div>
      <div class="form-group"><label>Unitate</label><input type="text" name="unit" id="itemUnit" class="form-control" value="serviciu"></div>
      <div class="form-group">
        <label>Preț unitar (<?= e($currency) ?> fără TVA)</label>
        <input type="number" name="unit_price" id="itemPrice" class="form-control" step="0.01" required oninput="calcItemTotal()">
        <?php if ($showRon): ?>
        <div class="form-hint" id="itemPriceRon" style="color:var(--text-muted);"></div>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($showRon): ?>
    <div style="background:var(--bg-subtle);padding:10px 14px;border-radius:var(--radius);font-size:13px;margin-bottom:12px;" id="itemTotalPreview"></div>
    <?php endif; ?>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
      <button type="button" class="btn btn-outline" onclick="closeModal('modalOfferItem')">Anulează</button>
      <button type="submit" class="btn btn-primary" id="offerItemSubmitBtn">Adaugă în ofertă</button>
    </div>
  </form>
</div></div>

<!-- Modal: Attachment upload -->
<div class="modal-overlay" id="modalAttachment"><div class="modal"><div class="modal-header"><h3>Adaugă atașament</h3><button class="btn btn-icon btn-outline" onclick="closeModal('modalAttachment')">✕</button></div>
  <form method="POST" enctype="multipart/form-data" class="modal-body"><?= csrf() ?>
    <input type="hidden" name="_action" value="upload_offer_attachment">
    <input type="hidden" name="offer_id" value="<?= $offerId ?>">
    <div class="form-group"><label>Fișier *</label><input type="file" name="att_file" class="form-control" required></div>
    <div class="form-group"><label>Descriere (ex: Caiet de sarcini, Specificații tehnice...)</label><input type="text" name="att_description" class="form-control" placeholder="Caiet de sarcini"></div>
    <div style="display:flex;gap:8px;justify-content:flex-end;">
      <button type="button" class="btn btn-outline" onclick="closeModal('modalAttachment')">Anulează</button>
      <button type="submit" class="btn btn-primary">Încarcă</button>
    </div>
  </form>
</div></div>

<script>
const EUR_RON = <?= $eurRon ?>;
const OFFER_CURRENCY = <?= json_encode($currency) ?>;

// Open offer item modal — for add (no args) or edit (pass item obj)
function openOfferItemModal(item) {
  const isEdit = item && item.id;
  document.getElementById('offerItemModalTitle').textContent = isEdit ? 'Editează serviciu' : 'Adaugă serviciu în ofertă';
  document.getElementById('offerItemId').value = isEdit ? item.id : '';
  document.getElementById('itemTitle').value   = isEdit ? item.title : '';
  document.getElementById('itemDesc').value    = isEdit ? (item.description || '') : '';
  document.getElementById('itemQty').value     = isEdit ? item.quantity : 1;
  document.getElementById('itemUnit').value    = isEdit ? (item.unit || 'serviciu') : 'serviciu';
  document.getElementById('itemPrice').value   = isEdit ? item.unit_price : '';
  document.getElementById('offerItemSubmitBtn').textContent = isEdit ? 'Salvează modificările' : 'Adaugă în ofertă';
  calcItemTotal();
  openModal('modalOfferItem');
}

function calcItemTotal() {
  const qty   = parseFloat(document.getElementById('itemQty')?.value) || 0;
  const price = parseFloat(document.getElementById('itemPrice')?.value) || 0;
  const total = qty * price;
  if (OFFER_CURRENCY === 'EUR') {
    const ronPrice = price * EUR_RON;
    const ronTotal = total * EUR_RON;
    const priceHint = document.getElementById('itemPriceRon');
    if (priceHint) priceHint.textContent = '≈ ' + ronPrice.toFixed(2).replace('.', ',') + ' RON';
    const preview = document.getElementById('itemTotalPreview');
    if (preview) preview.innerHTML = '<strong>Total linie:</strong> ' + total.toFixed(2).replace('.', ',') + ' EUR &nbsp;·&nbsp; ≈ ' + ronTotal.toFixed(2).replace('.', ',') + ' RON';
  }
}

// Load service catalog
(async function() {
  try {
    const data = await apiGet(window.APP_URL + '/api/service-catalog');
    if (data.success && data.services) {
      const sel = document.getElementById('catalogSelect');
      let lastCat = '';
      data.services.forEach(s => {
        if (s.category !== lastCat) {
          const optg = document.createElement('optgroup');
          optg.label = s.category; sel.appendChild(optg); lastCat = s.category;
        }
        const opt = document.createElement('option');
        opt.value = JSON.stringify(s);
        opt.textContent = s.title + ' (' + parseFloat(s.default_unit_price).toLocaleString('ro-RO') + ' ' + s.default_unit + ')';
        (sel.lastElementChild.tagName === 'OPTGROUP' ? sel.lastElementChild : sel).appendChild(opt);
      });
    }
  } catch(e) { console.log('Catalog load error:', e); }
})();

function fillFromCatalog(sel) {
  if (!sel.value) return;
  try {
    const s = JSON.parse(sel.value);
    document.getElementById('itemTitle').value = s.title || '';
    document.getElementById('itemDesc').value  = s.description || '';
    document.getElementById('itemUnit').value  = s.default_unit || 'serviciu';
    document.getElementById('itemPrice').value = s.default_unit_price || '';
    document.getElementById('itemQty').value   = (s.default_unit === 'participant' || s.default_unit === 'ora') ? 10 : 1;
    calcItemTotal();
  } catch(e) {}
}
</script>

<?php endif; ?>

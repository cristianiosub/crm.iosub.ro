<?php
$profileId = Auth::profileId();
$action = $_GET['action'] ?? 'list';
$docId = (int)($_GET['id'] ?? 0);
$pageTitle = 'NDA & Contracte';
$profile = Auth::getProfile();
$isWHT = stripos($profile['name'] ?? '', 'White Hat') !== false;
$logoFile = $isWHT ? 'logo-wht.png' : 'logo-cybershield.png';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['_action'] ?? '';
    if ($pa === 'save_document') {
        $data = ['profile_id'=>$profileId, 'client_id'=>(int)$_POST['client_id'], 'project_id'=>(int)($_POST['project_id']??0)?:null, 'doc_type'=>$_POST['doc_type']??'nda', 'doc_number'=>trim($_POST['doc_number']??''), 'title'=>trim($_POST['title']??''), 'content_html'=>$_POST['content_html']??'', 'status'=>$_POST['status']??'draft', 'signed_date'=>$_POST['signed_date']?:null, 'valid_until'=>$_POST['valid_until']?:null, 'notes'=>trim($_POST['notes']??'')];
        $did = (int)($_POST['doc_id'] ?? 0);
        if ($did) { DB::update('documents', $data, 'id = ? AND profile_id = ?', [$did, $profileId]); flash('success', 'Document actualizat.'); Router::redirect('documents/' . $did); }
        else { if (empty($data['doc_number'])) $data['doc_number'] = generateDocNumber($data['doc_type'], $profileId); $newId = DB::insert('documents', $data); flash('success', 'Document creat.'); Router::redirect('documents/' . $newId); }
    }
    if ($pa === 'delete_document') { DB::delete('documents', 'id = ? AND profile_id = ?', [(int)$_POST['doc_id'], $profileId]); flash('success', 'Document șters.'); Router::redirect('documents'); }
}

if ($action === 'list'):
    $docs = DB::fetchAll("SELECT d.*, c.company_name FROM documents d JOIN clients c ON d.client_id = c.id WHERE d.profile_id = ? ORDER BY d.created_at DESC", [$profileId]);
?>
<div class="flex-between mb-2"><div></div><a href="<?= Router::url('documents/new') ?>" class="btn btn-primary">+ Document nou</a></div>
<div class="card">
<?php if (empty($docs)): ?><div class="empty-state"><div class="empty-icon">📝</div><h3>Niciun document</h3><p>Creează primul NDA sau Contract.</p></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th>Nr.</th><th>Tip</th><th>Titlu</th><th>Client</th><th>Status</th><th>Data</th></tr></thead><tbody>
<?php foreach ($docs as $d): ?><tr onclick="location.href='<?= Router::url("documents/{$d['id']}") ?>'"><td><strong><?= e($d['doc_number']?:'#'.$d['id']) ?></strong></td><td><span class="badge badge-<?= $d['doc_type']==='nda'?'purple':($d['doc_type']==='contract'?'blue':'neutral') ?>"><?= strtoupper(e($d['doc_type'])) ?></span></td><td><?= e($d['title']?:'-') ?></td><td><?= e($d['company_name']) ?></td><td><?= statusBadge($d['status']) ?></td><td class="text-muted"><?= formatDate($d['created_at']) ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>

<?php elseif ($action === 'new' || $action === 'edit'):
    $doc = null;
    if ($action === 'edit' && $docId) $doc = DB::fetchOne("SELECT * FROM documents WHERE id = ? AND profile_id = ?", [$docId, $profileId]);
    $clients = DB::fetchAll("SELECT id, company_name FROM clients WHERE profile_id = ? ORDER BY company_name", [$profileId]);
    $projects = DB::fetchAll("SELECT id, name FROM projects WHERE profile_id = ? ORDER BY name", [$profileId]);
    $existingContent = $doc['content_html'] ?? '';
    $currentDocType = $doc['doc_type'] ?? 'nda';
    $logoUrl = APP_URL . '/assets/img/' . $logoFile;
    $sigUrl  = APP_URL . '/assets/img/semnatura.png';
    $profileName = htmlspecialchars($profile['legal_name'] ?: $profile['name'], ENT_QUOTES);
    $profileCUI  = htmlspecialchars($profile['cui'] ?? '', ENT_QUOTES);
    $profileAddr = htmlspecialchars($profile['address'] ?? '', ENT_QUOTES);
    $profileEmail= htmlspecialchars($profile['email'] ?? '', ENT_QUOTES);
    $profilePhone= htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES);
    $userName    = htmlspecialchars(Auth::userName(), ENT_QUOTES);
?>
<div class="card" style="max-width:960px;">
<div class="card-header">
  <h2><?= $doc ? 'Editare document' : 'Document nou' ?></h2>
  <?php if (!$doc): ?>
  <div style="display:flex;gap:8px;align-items:center;font-size:12px;color:var(--text-muted);">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    Selectează tipul — template-ul se completează automat
  </div>
  <?php endif; ?>
</div>
<form method="POST" class="card-body"><?= csrf() ?>
<input type="hidden" name="_action" value="save_document">
<input type="hidden" name="doc_id" value="<?= $doc['id']??'' ?>">

<div class="form-row">
  <div class="form-group">
    <label>Tip document *</label>
    <select name="doc_type" id="docTypeSelect" class="form-control" <?= $doc ? '' : 'onchange="applyDocTemplate(this.value)"' ?>>
          <option value="nda_cs" <?= $currentDocType==='nda_cs'?'selected':'' ?>>NDA — CyberShield</option>
      <option value="nda_wht" <?= $currentDocType==='nda_wht'?'selected':'' ?>>NDA — White Hat Technology</option>
      <option value="contract_cs" <?= $currentDocType==='contract_cs'?'selected':'' ?>>Contract Servicii — CyberShield</option>
      <option value="contract_wht" <?= $currentDocType==='contract_wht'?'selected':'' ?>>Contract Servicii — White Hat Technology</option>
      <option value="voluntariat" <?= $currentDocType==='voluntariat'?'selected':'' ?>>Contract de Voluntariat</option>
      <option value="oferta_cs" <?= $currentDocType==='oferta_cs'?'selected':'' ?>>Ofertă CyberShield</option>
      <option value="oferta_wht" <?= $currentDocType==='oferta_wht'?'selected':'' ?>>Ofertă White Hat Technology</option>
      <option value="other" <?= $currentDocType==='other'?'selected':'' ?>>Alt document</option>
    </select>
  </div>
  <div class="form-group">
    <label>Număr</label>
    <input type="text" name="doc_number" class="form-control" value="<?= e($doc['doc_number']??'') ?>" placeholder="Auto-generat">
  </div>
  <div class="form-group">
    <label>Status</label>
    <select name="status" class="form-control"><?php foreach (['draft','sent','signed','expired','cancelled'] as $s): ?><option value="<?= $s ?>" <?= ($doc['status']??'draft')===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
  </div>
</div>

<div class="form-row">
  <div class="form-group">
    <label>Client *</label>
    <select name="client_id" class="form-control" required>
      <option value="">— Selectează client —</option>
      <?php foreach ($clients as $cl): ?>
      <option value="<?= $cl['id'] ?>" <?= ($doc['client_id']??0)==$cl['id']?'selected':'' ?>><?= e($cl['company_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label>Proiect asociat</label>
    <select name="project_id" class="form-control">
      <option value="">—</option>
      <?php foreach ($projects as $pr): ?>
      <option value="<?= $pr['id'] ?>" <?= ($doc['project_id']??0)==$pr['id']?'selected':'' ?>><?= e($pr['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="form-row">
  <div class="form-group">
    <label>Titlu document</label>
    <input type="text" name="title" class="form-control" value="<?= e($doc['title']??'') ?>" placeholder="Completat automat după tip">
  </div>
  <div class="form-group">
    <label>Data semnării</label>
    <input type="date" name="signed_date" class="form-control" value="<?= $doc['signed_date']??'' ?>">
  </div>
  <div class="form-group">
    <label>Valabil până la</label>
    <input type="date" name="valid_until" class="form-control" value="<?= $doc['valid_until']??'' ?>">
  </div>
</div>

<div class="form-group">
  <label>Conținut document</label>
  <div style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-xs);">
    <div style="padding:8px 12px;background:var(--bg-subtle);border-bottom:1px solid var(--border);display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
      <button type="button" class="btn btn-sm btn-outline" onclick="execCmd('bold')" title="Bold"><b>B</b></button>
      <button type="button" class="btn btn-sm btn-outline" onclick="execCmd('italic')" title="Italic"><i>I</i></button>
      <button type="button" class="btn btn-sm btn-outline" onclick="execCmd('underline')" title="Subliniat"><u>U</u></button>
      <div style="width:1px;height:20px;background:var(--border);margin:0 4px;"></div>
      <button type="button" class="btn btn-sm btn-outline" onclick="execCmdVal('formatBlock','h2')" title="Titlu secțiune">H2</button>
      <button type="button" class="btn btn-sm btn-outline" onclick="execCmdVal('formatBlock','h3')" title="Titlu articol">H3</button>
      <button type="button" class="btn btn-sm btn-outline" onclick="execCmdVal('formatBlock','p')" title="Paragraf">¶</button>
      <div style="width:1px;height:20px;background:var(--border);margin:0 4px;"></div>
      <button type="button" class="btn btn-sm btn-outline" onclick="execCmd('insertUnorderedList')" title="Listă">• Listă</button>
      <?php if (!$doc): ?>
      <div style="margin-left:auto;display:flex;gap:6px;">
        <button type="button" class="btn btn-sm" style="background:var(--purple-light);color:var(--purple);border:1px solid rgba(124,58,237,0.2);" onclick="applyDocTemplate('nda_cs')">NDA CyberShield</button>
        <button type="button" class="btn btn-sm" style="background:#eff6ff;color:#1d4ed8;border:1px solid rgba(29,78,216,0.2);" onclick="applyDocTemplate('nda_wht')">NDA WHT</button>
        <button type="button" class="btn btn-sm" style="background:var(--blue-light);color:var(--blue);border:1px solid rgba(37,99,235,0.2);" onclick="applyDocTemplate('contract_cs')">Contract CyberShield</button>
        <button type="button" class="btn btn-sm" style="background:#dbeafe;color:#1e40af;border:1px solid rgba(30,64,175,0.2);" onclick="applyDocTemplate('contract_wht')">Contract WHT</button>
        <button type="button" class="btn btn-sm" style="background:#f0fdf4;color:#16a34a;border:1px solid rgba(22,163,74,0.2);" onclick="applyDocTemplate('voluntariat')">Voluntariat</button>
        <button type="button" class="btn btn-sm" style="background:#fffbeb;color:#d97706;border:1px solid rgba(217,119,6,0.2);" onclick="applyDocTemplate('oferta_cs')">Ofertă CyberShield</button>
        <button type="button" class="btn btn-sm" style="background:#faf5ff;color:#7c3aed;border:1px solid rgba(124,58,237,0.2);" onclick="applyDocTemplate('oferta_wht')">Ofertă WHT</button>
      </div>
      <?php endif; ?>
    </div>
    <div id="docEditor" contenteditable="true" style="min-height:500px;padding:30px 36px;font-family:Arial,sans-serif;font-size:14px;line-height:1.8;outline:none;background:#fff;">
      <?= $existingContent ?>
    </div>
  </div>
  <textarea name="content_html" id="docHtmlField" style="display:none;"></textarea>
</div>

<div class="form-group">
  <label>Note interne</label>
  <textarea name="notes" class="form-control" rows="2" placeholder="Note pentru uz intern, nu apar în document"><?= e($doc['notes']??'') ?></textarea>
</div>

<div style="display:flex;gap:8px;justify-content:flex-end;padding-top:8px;border-top:1px solid var(--border-light);margin-top:8px;">
  <a href="<?= Router::url('documents') ?>" class="btn btn-outline">Anulează</a>
  <button type="submit" class="btn btn-primary" onclick="document.getElementById('docHtmlField').value=document.getElementById('docEditor').innerHTML;">Salvează documentul</button>
</div>
</form>
</div>

<script>
// ── Template data ──────────────────────────────────────────────────────
const DOC_LOGO_URL  = <?= json_encode($logoUrl) ?>;
const DOC_SIG_URL   = <?= json_encode($sigUrl) ?>;
const PROFILE_NAME  = <?= json_encode($profileName) ?>;
const PROFILE_CUI   = <?= json_encode($profileCUI) ?>;
const PROFILE_ADDR  = <?= json_encode($profileAddr) ?>;
const PROFILE_EMAIL = <?= json_encode($profileEmail) ?>;
const PROFILE_PHONE = <?= json_encode($profilePhone) ?>;
const USER_NAME     = <?= json_encode($userName) ?>;
const HAS_EXISTING  = <?= json_encode(!empty($existingContent)) ?>;

function buildHeader(label) {
  return `<div style="font-family:Arial,sans-serif;">
<table style="width:100%;margin-bottom:20px;border-bottom:3px solid #1e3a5f;padding-bottom:14px;"><tr>
  <td><img src="${DOC_LOGO_URL}" style="height:52px;" alt="${PROFILE_NAME}"></td>
  <td style="text-align:right;font-size:11px;color:#64748b;line-height:1.6;">${PROFILE_NAME}<br>${PROFILE_ADDR}<br>${PROFILE_EMAIL} | ${PROFILE_PHONE}</td>
</tr></table>
<div style="text-align:center;margin-bottom:20px;">
  <p style="font-size:11px;color:#64748b;letter-spacing:.1em;text-transform:uppercase;margin-bottom:6px;">${PROFILE_NAME}</p>
  <h1 style="font-size:17px;font-weight:700;color:#1e3a5f;letter-spacing:.04em;margin-bottom:4px;">${label}</h1>
  <p style="color:#64748b;font-size:12px;">Nr. {doc_number} din data de {date}</p>
</div>`;
}

function buildFooter(leftLabel, rightLabel) {
  leftLabel  = leftLabel  || 'PRESTATOR / PARTE DIVULGATOARE';
  rightLabel = rightLabel || 'BENEFICIAR / PARTE RECEPTOARE';
  return `
<div style="margin-top:40px;padding-top:20px;border-top:2px solid #1e3a5f;">
  <table style="width:100%;"><tr>
    <td style="width:50%;padding-right:30px;vertical-align:top;">
      <p style="font-weight:700;color:#1e3a5f;margin-bottom:6px;">${leftLabel}</p>
      <p style="margin-bottom:2px;">${PROFILE_NAME}</p>
      <p style="font-size:11px;color:#64748b;margin-bottom:14px;">CUI: ${PROFILE_CUI}</p>
      <p style="margin-bottom:4px;">Prin: <strong>${USER_NAME}</strong>, Președinte</p>
      <img src="${DOC_SIG_URL}" style="height:60px;margin:6px 0 0;" alt="Semnătură">
      <p style="margin-top:4px;border-top:1px solid #cbd5e1;padding-top:4px;font-size:11px;color:#64748b;">Semnătură și ștampilă</p>
    </td>
    <td style="width:50%;padding-left:30px;vertical-align:top;">
      <p style="font-weight:700;color:#1e3a5f;margin-bottom:6px;">${rightLabel}</p>
      <p style="margin-bottom:2px;">{client_name}</p>
      <p style="font-size:11px;color:#64748b;margin-bottom:14px;">CUI: {client_cui}</p>
      <p style="margin-bottom:4px;">Prin: <strong>________________________</strong></p>
      <div style="height:60px;margin:6px 0 0;"></div>
      <p style="margin-top:4px;border-top:1px solid #cbd5e1;padding-top:4px;font-size:11px;color:#64748b;">Semnătură și ștampilă</p>
    </td>
  </tr></table>
</div>
</div>`;
}

const TEMPLATES = {

// ─────────────────────────────────────────────────────────────────────────────
// NDA CyberShield
// ─────────────────────────────────────────────────────────────────────────────
  nda_cs: () => buildHeader('ACORD DE CONFIDENȚIALITATE') + `

<p style="margin-bottom:20px;text-align:justify;font-size:13.5px;">
Prezentul Acord de Confidențialitate (<strong>"Acordul"</strong>) este încheiat la data de <strong>{date}</strong> între:
</p>

<table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:13px;">
<tr style="background:#f8fafc;">
  <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:700;width:160px;">PARTE DIVULGATOARE</td>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;">${PROFILE_NAME}, CUI ${PROFILE_CUI}, cu sediul la ${PROFILE_ADDR}, denumită în continuare <em>"Divulgatorul"</em>.</td>
</tr>
<tr>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:700;">PARTE RECEPTOARE</td>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;">{client_name}, CUI {client_cui}, cu sediul la {client_address}, denumită în continuare <em>"Receptorul"</em>.</td>
</tr>
</table>

<p style="margin-bottom:16px;font-size:13.5px;">Fiecare Parte va fi denumită individual <em>"Partea"</em>, iar împreună <em>"Părțile"</em>.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 1 – OBIECTUL ACORDULUI</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">1.1 Prezentul Acord reglementează condițiile în care Divulgatorul poate transmite Receptorului Informații Confidențiale, în scopul evaluării și desfășurării unei posibile colaborări comerciale sau prestări de servicii între Părți.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">1.2 Prin <strong>"Informații Confidențiale"</strong> se înțelege orice informații tehnice, comerciale, financiare, juridice, strategice sau de altă natură, divulgate de Divulgator Receptorului în formă scrisă, verbală, electronică sau prin orice alt mijloc, inclusiv, fără a se limita la: date privind clienții, partenerii sau furnizorii, know-how tehnic, coduri sursă, arhitecturi de sistem, vulnerabilități de securitate, politici interne, date financiare, planuri de afaceri.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">1.3 Nu constituie Informații Confidențiale: (a) informațiile devenite publice fără culpa Receptorului; (b) informațiile cunoscute anterior de Receptor, demonstrabile prin documente anterioare acordului; (c) informațiile obținute legal de la terți neobligați la confidențialitate; (d) informațiile a căror divulgare este impusă prin lege sau hotărâre judecătorească, cu notificarea prealabilă a Divulgatorului.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 2 – OBLIGAȚIILE RECEPTORULUI</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.1 Receptorul se obligă să păstreze stricta confidențialitate a Informațiilor Confidențiale și să nu le divulge unor terți fără acordul prealabil scris al Divulgatorului.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.2 Receptorul va utiliza Informațiile Confidențiale exclusiv în scopul pentru care au fost divulgate și menționat în prezentul Acord.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.3 Receptorul va restricționa accesul la Informațiile Confidențiale strict la angajații sau colaboratorii săi care au nevoie de acestea în scopul colaborării, asigurând că aceștia sunt obligați la rândul lor prin angajamente de confidențialitate cel puțin echivalente.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.4 Receptorul va implementa și menține măsuri tehnice și organizatorice adecvate pentru protejarea Informațiilor Confidențiale împotriva accesului neautorizat, divulgării sau utilizării abuzive.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.5 Receptorul va notifica Divulgatorul în termen de maximum 48 de ore dacă ia cunoștință de orice breșă de securitate sau divulgare neautorizată a Informațiilor Confidențiale.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 3 – DURATA ACORDULUI</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">3.1 Prezentul Acord intră în vigoare la data semnării și rămâne în vigoare pe o perioadă de <strong>5 (cinci) ani</strong>, dacă nu este reziliat anterior prin acordul scris al ambelor Părți.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">3.2 Obligațiile de confidențialitate stipulate în prezentul Acord supraviețuiesc încetării acestuia și rămân în vigoare pentru o perioadă de <strong>5 (cinci) ani</strong> de la data încetării, din orice motiv.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 4 – RĂSPUNDERE ȘI DAUNE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.1 Orice încălcare a obligațiilor de confidențialitate de către Receptor dă dreptul Divulgatorului la daune-interese minime de <strong>50.000 EUR</strong>, fără a fi necesară dovedirea prejudiciului efectiv, în plus față de orice alte daune demonstrate.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.2 Divulgatorul are dreptul de a solicita în instanță măsuri provizorii urgente (injuncție/ordonanță) pentru prevenirea sau stoparea oricărei încălcări iminente sau continue.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.3 Niciuna dintre limitările de mai sus nu exclude răspunderea în caz de dol sau culpă gravă.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 5 – RESTITUIREA INFORMAȚIILOR</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">5.1 La cererea Divulgatorului sau la încetarea prezentului Acord, Receptorul se obligă să returneze sau să distrugă imediat toate copiile (fizice și electronice) ale Informațiilor Confidențiale și să confirme în scris această acțiune.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">5.2 Obligația de restituire/distrugere nu se aplică copiilor păstrate obligatoriu prin lege sau hotărâre judecătorească, Receptorul notificând Divulgatorul cu privire la existența acestora.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 6 – DISPOZIȚII FINALE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.1 Prezentul Acord este guvernat și interpretat în conformitate cu legislația română. Orice dispută va fi soluționată pe cale amiabilă (minimum 30 zile), ulterior de instanțele competente din România.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.2 Prezentul Acord poate fi semnat în mai multe exemplare originale, inclusiv cu semnătură electronică calificată, fiecare cu valoare de original.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.3 Dacă o prevedere a prezentului Acord este declarată nulă sau inaplicabilă, celelalte prevederi rămân pe deplin valabile și aplicabile.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.4 Prezentul Acord reprezintă acordul integral al Părților cu privire la obiectul său și înlocuiește orice înțelegeri anterioare verbale sau scrise pe aceeași temă.</p>
` + buildFooter(),

// NDA WHT — identic ca structură, dar cu logo WHT
  nda_wht: () => buildHeader('ACORD DE CONFIDENȚIALITATE — WHITE HAT TECHNOLOGY') + `

<p style="margin-bottom:20px;text-align:justify;font-size:13.5px;">
Prezentul Acord de Confidențialitate (<strong>„Acordul”</strong>) este încheiat la data de <strong>{date}</strong> între:
</p>

<table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:13px;">
<tr style="background:#f8fafc;">
  <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:700;width:160px;">PARTE DIVULGATOARE</td>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;">${PROFILE_NAME}, CUI ${PROFILE_CUI}, cu sediul la ${PROFILE_ADDR}, denumită în continuare <em>„Divulgatorul”</em>.</td>
</tr>
<tr>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:700;">PARTE RECEPTOARE</td>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;">{client_name}, CUI {client_cui}, cu sediul la {client_address}, denumită în continuare <em>„Receptorul”</em>.</td>
</tr>
</table>

<p style="margin-bottom:16px;font-size:13.5px;">Fiecare Parte va fi denumită individual <em>„Partea”</em>, iar împreună <em>„Părțile”</em>.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 1 – OBIECTUL ACORDULUI</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">1.1 Prezentul Acord reglementează condițiile în care Divulgatorul poate transmite Receptorului Informații Confidențiale, în scopul evaluării şi desfăşurării unei posibile colaborări comerciale sau prestări de servicii între Părți.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">1.2 Prin <strong>„Informații Confidențiale”</strong> se ënțelege orice informații tehnice, comerciale, financiare, juridice, strategice sau de altă natură, divulgate de Divulgator Receptorului în formă scrisă, verbală, electronică sau prin orice alt mijloc, inclusiv, fără a se limita la: date privind clienții, partenerii sau furnizorii, know-how tehnic, coduri sursă, arhitecturi de sistem, vulnerabilități de securitate, politici interne, date financiare, planuri de afaceri.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">1.3 Nu constituie Informații Confidențiale: (a) informațiile devenite publice fără culpa Receptorului; (b) informațiile cunoscute anterior de Receptor, demonstrabile prin documente anterioare acordului; (c) informațiile obținute legal de la terți neobligați la confidențialitate; (d) informațiile a căror divulgare este impusă prin lege sau hotărâre judecătorească, cu notificarea prealabilă a Divulgatorului.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 2 – OBLIGAȚIILE RECEPTORULUI</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.1 Receptorul se obligă să păstreze stricta confidențialitate a Informațiilor Confidențiale şi să nu le divulge unor terți fără acordul prealabil scris al Divulgatorului.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.2 Receptorul va utiliza Informațiile Confidențiale exclusiv în scopul pentru care au fost divulgate şi menționat în prezentul Acord.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.3 Receptorul va restricționa accesul la Informațiile Confidențiale strict la angajații sau colaboratorii săi care au nevoie de acestea în scopul colaborării, asigurând că aceştia sunt obligați la rândul lor prin angajamente de confidențialitate cel puțin echivalente.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.4 Receptorul va implementa şi menține măsuri tehnice şi organizațorice adecvate pentru protejarea Informațiilor Confidențiale împotriva accesului neautorizat, divulgării sau utilizării abuzive.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.5 Receptorul va notifica Divulgatorul în termen de maximum 48 de ore dacă ia cunoştință de orice breşă de securitate sau divulgare neautorizată a Informațiilor Confidențiale.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 3 – DURATA ACORDULUI</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">3.1 Prezentul Acord intră în vigoare la data semnării şi rămâne în vigoare pe o perioadă de <strong>5 (cinci) ani</strong>, dacă nu este reziliat anterior prin acordul scris al ambelor Părți.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">3.2 Obligațiile de confidențialitate stipulate în prezentul Acord supraviețuiesc încetării acestuia şi rămân în vigoare pentru o perioadă de <strong>5 (cinci) ani</strong> de la data încetării, din orice motiv.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 4 – RĂSPUNDERE ŞI DAUNE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.1 Orice încălcare a obligațiilor de confidențialitate de către Receptor dă dreptul Divulgatorului la daune-interese minime de <strong>50.000 EUR</strong>, fără a fi necesară dovedirea prejudiciului efectiv, în plus față de orice alte daune demonstrate.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.2 Divulgatorul are dreptul de a solicita în instanță măsuri provizorii urgente (injuncție/ordonanță) pentru prevenirea sau stoparea oricărei încălcări iminente sau continue.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.3 Niciuna dintre limitările de mai sus nu exclude răspunderea în caz de dol sau culpă gravă.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 5 – RESTITUIREA INFORMAȚIILOR</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">5.1 La cererea Divulgatorului sau la încetarea prezentului Acord, Receptorul se obligă să returneze sau să distrugă imediat toate copiile (fizice şi electronice) ale Informațiilor Confidențiale şi să confirme în scris această acțiune.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">5.2 Obligația de restituire/distrugere nu se aplică copiilor păstrate obligatoriu prin lege sau hotărâre judecătorească, Receptorul notificând Divulgatorul cu privire la existența acestora.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">Art. 6 – DISPOZIȚII FINALE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.1 Prezentul Acord este guvernat şi interpretat în conformitate cu legislația română. Orice dispută va fi soluționată pe cale amiabilă (minimum 30 zile), ulterior de instanțele competente din România.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.2 Prezentul Acord poate fi semnat în mai multe exemplare originale, inclusiv cu semnătură electronică calificată, fiecare cu valoare de original.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.3 Dacă o prevedere a prezentului Acord este declarată nulă sau inaplicabilă, celelalte prevederi rămân pe deplin valabile şi aplicabile.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.4 Prezentul Acord reprezintă acordul integral al Părților cu privire la obiectul său şi înlocuieşte orice înțelegeri anterioare verbale sau scrise pe aceeaşi temă.</p>
` + buildFooter(),

  contract_cs: () => buildHeader('CONTRACT DE PRESTĂRI SERVICII SECURITATE CIBERNETICĂ — CYBERSHIELD') + `

<p style="margin-bottom:20px;text-align:justify;font-size:13.5px;">
Prezentul Contract de Prestări Servicii (<strong>"Contractul"</strong>) este încheiat la data de <strong>{date}</strong> între:
</p>

<table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:13px;">
<tr style="background:#f8fafc;">
  <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:700;width:160px;">PRESTATOR</td>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;">${PROFILE_NAME}, CUI ${PROFILE_CUI}, sediu: ${PROFILE_ADDR}, e-mail: ${PROFILE_EMAIL}, denumit în continuare <em>"Prestatorul"</em>.</td>
</tr>
<tr>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:700;">BENEFICIAR</td>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;">{client_name}, CUI {client_cui}, sediu: {client_address}, denumit în continuare <em>"Beneficiarul"</em>.</td>
</tr>
</table>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">1. DEFINIȚII ȘI INTERPRETARE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">1.1 În prezentul Contract, termenii următori au înțelesurile indicate: <strong>„Contract"</strong> — prezentul Contract, inclusiv Anexele; <strong>„Servicii"</strong> — ansamblul prestațiilor convenite; <strong>„Livrabile"</strong> — documentele, rapoartele și rezultatele concrete ale Serviciilor; <strong>„Onorariu"</strong> — suma totală datorată; <strong>„Zi Lucrătoare"</strong> — luni–vineri, exclusiv sărbători legale.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">1.2 Anexele fac parte integrantă din Contract. În caz de conflict, prevederile Contractului prevalează față de Anexe, dacă nu se specifică altfel în mod expres.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">2. INTRAREA ÎN VIGOARE ȘI DURATA</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.1 Contractul intră în vigoare la data semnării de către ambele Părți și se încheie pe durată determinată, până la finalizarea Serviciilor și predarea tuturor Livrabilelor, dar nu mai târziu de termenele convenite în Anexa 1.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.2 Prelungirea Contractului se face numai prin act adițional semnat de ambele Părți.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">3. OBIECTUL CONTRACTULUI</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">3.1 Prestatorul se obligă să furnizeze Beneficiarului, pe durata Contractului, serviciile specializate descrise în Anexa 1 – Condiții Specifice.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">3.2 Beneficiarul se obligă să pună la dispoziția Prestatorului toate accesele, credențialele, documentațiile și informațiile necesare executării Serviciilor, conform Clauzei 5.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">3.3 Prestatorul va presta Serviciile cu diligența unui profesionist din domeniu, respectând cele mai bune practici și standarde internaționale aplicabile.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">4. FURNIZAREA SERVICIILOR</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.1 Prestatorul se angajează că pe durata Contractului: (a) va furniza Serviciile cu profesionalism, respectând termenele agreate; (b) va menține Beneficiarul informat cu privire la progresul Serviciilor; (c) va desemna un coordonator de proiect responsabil de comunicarea cu Beneficiarul; (d) va presta Serviciile cu propriile resurse, fără a angaja Beneficiarul față de terți.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.2 Activitățile vor fi desfășurate cu precauție maximă pentru a nu afecna funcționarea normală a sistemelor Beneficiarului. Orice acțiune cu impact operațional va fi agreată în prealabil.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.3 Prestatorul va notifica de îndată Beneficiarul în cazul în care nu poate furniza Serviciile conform programului agreat. Părțile vor stabili de comun acord un termen alternativ.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">5. OBLIGAȚIILE BENEFICIARULUI</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">5.1 Beneficiarul se obligă să pună la dispoziția Prestatorului, în termen de maximum 3 Zile Lucrătoare de la semnare: (a) accesele și credențialele necesare; (b) documentația tehnică relevantă; (c) un reprezentant tehnic intern ca punct de contact; (d) spațiul și echipamentele necesare sesiunilor de training, dacă este cazul.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">5.2 Beneficiarul va coopera cu bună-credință cu Prestatorul și va furniza în timp util toate informațiile solicitate.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">6. ONORARII ȘI MODALITĂȚI DE PLATĂ</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.1 Pentru furnizarea integrală a Serviciilor, Beneficiarul va plăti Prestatorului Onorariul prevăzut în Anexa 1.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.2 Prestatorul va emite factura în termen de 5 zile calendaristice de la finalizarea etapei convenite. Plata se va efectua prin transfer bancar în termen de 5 zile calendaristice de la emiterea facturii.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.3 În caz de întârziere la plată, suma datorată se majorează cu penalități de 0,1% pe zi de întârziere, calculate din ziua ulterioară scadenței.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.4 Cheltuielile de deplasare/cazare sunt suportate de Beneficiar numai dacă au fost aprobate în prealabil și în scris. Licențele software și echipamentele utilizate de Prestator sunt suportate exclusiv de acesta.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">7. RĂSPUNDERE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">7.1 Răspunderea Prestatorului pentru prejudicii directe nu va depăși valoarea totală a Onorariului plătit în baza prezentului Contract.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">7.2 Limitarea de la 7.1 nu se aplică prejudiciilor cauzate prin dol sau culpă gravă.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">7.3 Prestatorul nu răspunde pentru: (a) daune indirecte sau pierderi de profit; (b) vulnerabilități preexistente nedescoperibile prin metodele standard; (c) incidente survenite ca urmare a neimplementării recomandărilor.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">8. INFORMAȚII CONFIDENȚIALE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">8.1 Prestatorul va păstra stricta confidențialitate a tuturor Informațiilor Confidențiale ale Beneficiarului, inclusiv date tehnice, configurații de sistem, vulnerabilități identificate și orice alte informații sensibile.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">8.2 Obligația de confidențialitate se menține pe durata Contractului și pentru o perioadă de 5 ani de la data încetării acestuia. Încălcarea dă dreptul Beneficiarului la daune minime de 50.000 EUR.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">9. PROPRIETATE INTELECTUALĂ</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">9.1 Toate Livrabilele create de Prestator devin proprietatea exclusivă a Beneficiarului la momentul achitării integrale a Onorariului.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">9.2 Instrumentele, metodologiile și know-how-ul Prestatorului preexistente rămân proprietatea Prestatorului. Beneficiarul obține o licență neexclusivă de utilizare internă.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">10. PROTECȚIA DATELOR CU CARACTER PERSONAL</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">10.1 Prestatorul acționează în calitate de Împuternicit al Operatorului, iar Beneficiarul în calitate de Operator de Date, în sensul GDPR (Regulamentul (UE) 2016/679).</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">10.2 Prestatorul va prelucra datele exclusiv pe baza instrucțiunilor documentate ale Beneficiarului și va notifica orice incident de securitate în maximum 24 de ore. La finalizarea Serviciilor, va returna și șterge toate datele personale prelucrate.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">11. DECLARAȚII ȘI GARANȚII</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">11.1 Prestatorul garantează că: este o entitate legal constituită; are capacitatea de a semna Contractul; deține competențele tehnice necesare; va respecta toate legile aplicabile; activitățile de testare vor fi desfășurate exclusiv pe sistemele Beneficiarului și în limitele acceselor acordate.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">11.2 Beneficiarul garantează că: are autoritatea legală de a autoriza accesul la sistemele sale; va coopera cu bună-credință; va furniza în timp util toate informațiile necesare.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">12. ÎNCETARE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">12.1 Contractul încetează prin: executarea integrală; ajungerea la termen; acordul scris al Părților; falimentul/insolvența oricărei Părți; reziliere pentru încălcare substanțială neremediată în 14 zile de la notificare.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">12.2 La încetare, Prestatorul va preda toate documentele și accesele și va confirma în scris revocarea acestora în 5 Zile Lucrătoare.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">13. FORȚA MAJORĂ</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">13.1 Nicio Parte nu este responsabilă pentru neîndeplinirea obligațiilor cauzată de un eveniment de forță majoră. Notificarea se face în maximum 3 zile de la producerea evenimentului. Dacă forța majoră depășește 30 de zile, oricare Parte poate denunța Contractul cu 10 zile preaviz, fără penalități.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">14. NOTIFICĂRI</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">14.1 Notificările vor fi în scris și recepționate: imediat prin e-mail cu confirmare de citire; la data confirmată prin curierat. Coordonate: Prestator — ${PROFILE_EMAIL} / ${PROFILE_PHONE}; Beneficiar — {client_email}.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">15. DISPOZIȚII FINALE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">15.1 <strong>Legea aplicabilă:</strong> Legea română. <strong>Litigii:</strong> Soluționare amiabilă (min. 30 zile), ulterior instanțele competente din România.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">15.2 <strong>Integralitate:</strong> Prezentul Contract reprezintă acordul integral și înlocuiește orice înțelegeri anterioare. <strong>Modificări:</strong> Numai prin act adițional semnat de ambele Părți. <strong>Non-solicitare:</strong> Niciuna dintre Părți nu va recruta angajații celeilalte pe durata Contractului și 12 luni ulterior.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">15.3 Prezentul Contract a fost negociat și acceptat în mod expres de ambele Părți, semnarea sa echivalând cu acceptul prevăzut de Art. 1203 Cod Civil pentru toate clauzele conținute.</p>
` + buildFooter(),

  voluntariat: () => buildHeader('C O N T R A C T   D E   V O L U N T A R I A T') + `
<p style="margin-bottom:8px;"><strong>Contract de voluntariat numărul {doc_number} din data de {date}</strong></p>
<p style="margin-bottom:4px;"><strong>Preşedintele Asociației "EDUCAȞIE ÎN SECURITATE CIBERNETICĂ": ${USER_NAME}</strong></p>
<p style="margin-bottom:20px;"><strong>Voluntar al Asociației "EDUCAȞIE ÎN SECURITATE CIBERNETICĂ": {client_name}</strong></p>
<h2 style="font-size:14px;font-weight:700;margin:20px 0 10px;">I. PĂRȚILE CONTRACTANTE</h2>
<p style="text-align:justify;margin-bottom:10px;">Asociația <strong>„EDUCAȞIE ÎN SECURITATE CIBERNETICĂ”</strong>, cu sediul în strada Linia de Centură, numărul 50, vila N32 D2, apartament 002, cod poştal 077175, Ştefăneştii de Jos, Ilfov, e-mail: cristian.iosub@cybershield.org, telefon +40 723 444 825, cod fiscal 48106635, având contul RO20BREL0002004031270100, deschis la Libra Internet Bank, reprezentată prin <strong>Cristian Iosub</strong>, cu funcția de Preşedinte, în calitate de beneficiar al voluntariatului şi</p>
<p style="text-align:justify;margin-bottom:20px;"><strong>{client_name}</strong> cu domiciliul în {client_domiciliu}, având actul de identitate seria {client_ci_serie}, numărul {client_ci_numar}, eliberat la data de {client_ci_data} de către {client_ci_emitent}, codul numeric personal {client_cnp}, în calitate de voluntar, au convenit sa încheie prezentul contract de voluntariat, cu respectarea următoarelor clauze:</p>
<p style="font-weight:700;margin-bottom:8px;">1. Termeni si condiții</p>
<p style="text-align:justify;margin-bottom:8px;">1.1. voluntariatul reprezintă participarea voluntarului persoana fizică la activități de interes public desfăşurate în folosul altor persoane sau al societății, organizate de către persoane juridice de drept public sau de drept privat, fără remunerație, individual sau in grup;</p>
<p style="text-align:justify;margin-bottom:8px;">1.2. activitatea de interes public reprezintă activitatea desfăşurată în domenii precum: arta şi cultura, sportul si recreerea, educația şi cercetarea, protecția mediului, sănătatea, asistența socială, religia, activismul civic, drepturile omului, ajutorul umanitar şi/sau filantropic, dezvoltarea comunitară, dezvoltarea socială;</p>
<p style="text-align:justify;margin-bottom:8px;">1.3. organizația-gazda este persoana juridică de drept public sau de drept privat, fără scop lucrativ, care organizează şi administrează activități de voluntariat;</p>
<p style="text-align:justify;margin-bottom:8px;">1.4. contractul de voluntariat reprezintă convenția încheiată intre un voluntar şi organizația-gazdă, în temeiul căreia prima parte se obliga sa presteze o activitate de interes public, fără a fi remunerată, iar cea de-a doua se obligă să ofere o activitate adecvată solicitării sau pregătirii voluntarului;</p>
<p style="text-align:justify;margin-bottom:8px;">1.5. voluntarul este orice persoana fizică, fără deosebire de rasă, origine etnică, religie, sex, opinie, apartenență politică, care a dobândit capacitate de munca potrivit legislației în domeniul muncii şi desfăşoară activități de voluntariat;</p>
<p style="text-align:justify;margin-bottom:8px;">1.6. beneficiarul activității de voluntariat este persoana fizică, alta decât soțul/soția şi copiii, sau persoana juridică in folosul căreia se desfăşoară activitatea de voluntariat;</p>
<p style="text-align:justify;margin-bottom:16px;">1.7. coordonatorul voluntarilor este voluntarul sau angajatul organizației-gazda, care îndeplineşte sarcinile legate de coordonarea şi administrarea activității voluntarilor.</p>
<h2 style="font-size:14px;font-weight:700;margin:20px 0 10px;">II. OBIECTUL CONTRACTULUI</h2>
<p style="text-align:justify;margin-bottom:8px;">2.1. Obiectul contractului îl constituie activitatea de interes public în domeniul educației cibernetice precum crearea şi predarea materialelor educative ce pot fi folosite ca suport de curs sau auxiliare pentru elevi, profesori, părinți sau bunici, folosind tehnici moderne şi interactive pe care o va desfăşura voluntarul fără a obține contraprestație materială.</p>
<p style="text-align:justify;margin-bottom:8px;">2.2. Principiile pe care se realizează obiectul contractului sunt următoarele:</p>
<p style="padding-left:20px;margin-bottom:4px;">a) participarea ca voluntar pe baza consimțământului liber exprimat;</p>
<p style="padding-left:20px;margin-bottom:4px;">b) implicarea activă a voluntarului în viața comunității;</p>
<p style="padding-left:20px;margin-bottom:4px;">c) desfăşurarea voluntariatului cu excluderea oricărei contraprestații materiale din partea beneficiarului activității;</p>
<p style="padding-left:20px;margin-bottom:10px;">d) recrutarea voluntarilor pe baza egalității şanselor, fără nici un fel de discriminări.</p>
<p style="text-align:justify;margin-bottom:16px;">2.3. În executarea contractului de voluntariat, voluntarul se subordonează coordonatorului de voluntari al Asociației, conform prevederilor art. 16 din Legea 78/2014 privind reglementarea activității de voluntariat în România.</p>
<h2 style="font-size:14px;font-weight:700;margin:20px 0 10px;">III. DURATA CONTRACTULUI</h2>
<p style="text-align:justify;margin-bottom:8px;">3.1. Prezentul contract se încheie pe o durata <strong>nedeterminată</strong>.</p>
<p style="text-align:justify;margin-bottom:16px;">3.2. Activitățile de voluntariat care fac obiectul prezentului contract se desfăşoară pe timp de zi şi/sau pe timp de noapte. Programul, timpul şi perioada de desfăşurare a activității de voluntariat precum şi responsabilitățile sunt stabilite prin fişa voluntarului, completată la fiecare activitate, care este parte integrantă a prezentului contract.</p>
<h2 style="font-size:14px;font-weight:700;margin:20px 0 10px;">IV. DREPTURI SI OBLIGATII</h2>
<p style="font-weight:700;margin-bottom:6px;">4.1. Drepturi ale voluntarului:</p>
<p style="padding-left:20px;margin-bottom:4px;">a) participarea activă la elaborarea şi derularea programelor în considerarea cărora s-a încheiat contractul;</p>
<p style="padding-left:20px;margin-bottom:4px;">b) desfăşurarea activității în concordanță cu capacitatea şi disponibilitatea acestuia;</p>
<p style="padding-left:20px;margin-bottom:4px;">c) asigurarea de către Asociație a desfăşurării activităților sub coordonarea unui îndrumător, în condițiile legale de protecție a muncii, în funcție de natura şi de caracteristicile activității respective;</p>
<p style="padding-left:20px;margin-bottom:4px;">d) eliberarea de către Asociație a unui certificat nominal însoțit de raportul de activitate care să recunoască prestarea activității de voluntar, precum si experiența si aptitudinile dobândite;</p>
<p style="padding-left:20px;margin-bottom:4px;">e) rambursarea de către Asociație in condițiile prevăzute de prezentul contract, potrivit legii, a cheltuielilor efectuate pentru realizarea activității;</p>
<p style="padding-left:20px;margin-bottom:4px;">f) durata timpului de lucru, stabilita in condițiile legii, sa nu afecteze sănătatea si resursele psihofizice ale voluntarului;</p>
<p style="padding-left:20px;margin-bottom:4px;">g) sa beneficieze de titluri onorifice, decorații, premii, in condițiile legii;</p>
<p style="padding-left:20px;margin-bottom:4px;">h) dreptul la confidențialitate şi protecția datelor personale;</p>
<p style="padding-left:20px;margin-bottom:12px;">i) dreptul la timp liber corespunzător cu activitatea de voluntariat.</p>
<p style="font-weight:700;margin-bottom:6px;">4.2. Obligațiile voluntarului:</p>
<p style="padding-left:20px;margin-bottom:4px;">a) să presteze o activitate de interes public, fără a fi remunerat;</p>
<p style="padding-left:20px;margin-bottom:4px;">b) să aibă o conduită civică bună, complementară cu obiectivele generale ale voluntariatului;</p>
<p style="padding-left:20px;margin-bottom:4px;">c) să îşi desfăşoare activitatea cu depunerea tuturor diligențelor, să îndeplinească sarcinile prevăzute în fişa de voluntariat şi să respecte instrucțiunile stipulate în fişa de protecție a voluntarului;</p>
<p style="padding-left:20px;margin-bottom:4px;">d) să păstreze confidențialitatea informațiilor la care are acces în cadrul activității de voluntariat, în perioada desfăşurării contractului de voluntariat şi pe o perioadă ulterioară de <strong>3 ani</strong> de la data încetării acestuia;</p>
<p style="padding-left:20px;margin-bottom:4px;">e) să participe la cursurile de instruire organizate, inițiate sau propuse de către Asociație;</p>
<p style="padding-left:20px;margin-bottom:4px;">f) să respecte regulile şi normele de comportament ale asociației precum şi procedurile, politicile şi regulamentele interne ale asociației;</p>
<p style="padding-left:20px;margin-bottom:4px;">g) să prezinte coordonatorului rapoartele de activitate;</p>
<p style="padding-left:20px;margin-bottom:12px;">h) să ocrotească bunurile pe care le foloseşte în cadrul activității de voluntariat, în caz contrar, acesta va achita contravaloarea lor, direct proporțional cu pagubele produse;</p>
<p style="text-align:justify;margin-bottom:8px;">4.3. Răspunderea voluntarului în astfel de situații se angajează potrivit regulilor stabilite în legislația în vigoare şi în regulamentele interne ale Asociației.</p>
<p style="text-align:justify;margin-bottom:8px;">4.4. În executarea contractului de voluntariat, voluntarul se subordonează conducerii Asociației, parte a prezentului contract:</p>
<p style="padding-left:20px;margin-bottom:4px;">a) să asigure desfăşurarea activității voluntarului, în condițiile legale de protecție a muncii, în funcție de natura şi caracteristicile activității respective;</p>
<p style="padding-left:20px;margin-bottom:4px;">b) să elibereze certificatul nominal care sa ateste calitatea de voluntar;</p>
<p style="padding-left:20px;margin-bottom:4px;">c) să asigure voluntarului condiții necesare să-şi desfăşoare activitatea fără sa afecteze sănătatea şi resursele psihofizice ale acestuia;</p>
<p style="padding-left:20px;margin-bottom:4px;">d) să organizeze, periodic, cursuri de instruire pentru voluntari;</p>
<p style="padding-left:20px;margin-bottom:12px;">e) să evalueze, periodic, activitatea voluntarului şi, în funcție de evenimentele deosebite semnalate de acesta, să-i atribuie titluri onorifice, decorații şi premii, în condițiile legii, cu ocazia unor evenimente deosebite privind activitatea pe care a desfăşurat-o.</p>
<p style="font-weight:700;margin-bottom:6px;">4.5. Drepturi ale Asociației „EDUCAȞIE ÎN SECURITATE CIBERNETICĂ”:</p>
<p style="padding-left:20px;margin-bottom:4px;">a) dreptul de a stabili organizarea şi funcționarea activității de voluntariat;</p>
<p style="padding-left:20px;margin-bottom:4px;">b) dreptul de a iniția conținutul fişei de voluntariat, pe care o adaptează la pregătirea voluntarului, precum şi la tipul de activități de voluntariat desfăşurate de către Asociație;</p>
<p style="padding-left:20px;margin-bottom:4px;">c) dreptul de a exercita controlul asupra modului de implementare a fisei de voluntariat prin coordonatorul de voluntari;</p>
<p style="padding-left:20px;margin-bottom:12px;">d) dreptul de constata şi a retrage statutul de voluntar, fie ca urmare a unor abateri ale voluntarului, raportate la clauzele stabilite în contractul de voluntariat, fişa de voluntariat şi/sau în fişa de protecție a voluntarului, fie ca urmare a unor activități din mediul digital ce pot aduce prejudicii de imagine Asociației.</p>
<p style="font-weight:700;margin-bottom:6px;">4.6. Obligații ale Asociației:</p>
<p style="padding-left:20px;margin-bottom:4px;">a) să asigure desfăşurarea activităților sub îndrumarea şi coordonarea unui coordonator de voluntari, cu respectarea condițiilor legale în vigoare privind securitatea şi sănătatea în muncă, în funcție de natura şi de caracteristicile activității în cauză;</p>
<p style="padding-left:20px;margin-bottom:4px;">b) să elibereze voluntarului certificatul nominal care atestă calitatea de voluntar şi raportul de activitate;</p>
<p style="padding-left:20px;margin-bottom:4px;">c) să trateze voluntarul ca un coleg cu drepturi egale, fără discriminări;</p>
<p style="padding-left:20px;margin-bottom:4px;">d) să pună la dispoziția voluntarului statutul, regulamentul de ordine interioară al asociației, precum şi deciziile conducerii sau ale coordonatorilor de voluntari, dacă acestea afectează în mod direct sau indirect voluntarul şi au legătură cu activitatea desfăşurată de acesta;</p>
<p style="padding-left:20px;margin-bottom:4px;">e) să pună la dispoziția voluntarului datele de contact ale coordonatorului de voluntari şi ale responsabilului cu protecția datelor cu caracter personal;</p>
<p style="padding-left:20px;margin-bottom:12px;">f) să informeze voluntarul cu privire la metoda de raportare şi de lucru.</p>
<p style="text-align:justify;margin-bottom:16px;">4.7. La solicitarea voluntarului, Asociația poate încheia contract de asigurare împotriva riscurilor de accident si de boala sau a altor riscuri ce decurg din natura activității, în funcție de complexitatea activității la care participa acesta si in limita sumelor alocate cu aceasta destinație.</p>
<h2 style="font-size:14px;font-weight:700;margin:20px 0 10px;">V. ALTE DISPOZIȚII</h2>
<p style="text-align:justify;margin-bottom:8px;">5.1. În executarea contractului de voluntariat, voluntarul se subordonează conducerii persoanei juridice cu care s-a încheiat contractul.</p>
<p style="text-align:justify;margin-bottom:8px;">5.2. Executarea obligațiilor contractuale ce revin voluntarului nu se poate face prin reprezentare.</p>
<p style="text-align:justify;margin-bottom:8px;">5.3. Răspunderea pentru neexecutarea sau pentru executarea necorespunzătoare a prezentului contract este supusa dispozițiilor Codului civil.</p>
<p style="text-align:justify;margin-bottom:8px;">5.4. Dacă pe parcursul executării prezentului contract, intervine, independent de voința parților, o situație de natură să îngreuneze executarea obligațiilor ce revin voluntarului, contractul va fi renegociat, iar dacă situația face imposibila executarea, în continuare, a prezentului contract, acesta este reziliat de plin drept.</p>
<p style="text-align:justify;margin-bottom:16px;">5.5. Denunțarea unilaterala a prezentului contract, din inițiativa parților, se poate face cu un preaviz de <strong>15 zile</strong>, fără ca hotărârea parții respective sa fie obligata să şi-o motiveze. În vederea comunicării denunțării prezentului contract, pot fi folosite mijloacele de comunicare la distanță în conformitate cu datele de contact menționate în capitolul I.</p>
<h2 style="font-size:14px;font-weight:700;margin:20px 0 10px;">VI. CLAUZE GENERALE REFERITOARE LA PROTECȚIA DATELOR CU CARACTER PERSONAL</h2>
<p style="text-align:justify;margin-bottom:8px;">6.1. Asociația colectează şi prelucrează datele personale ale voluntarului in conformitate cu legislația in vigoare, in modalități care asigură confidențialitatea şi securitatea adecvată a acestor date, in vederea asigurării protecției împotriva prelucrării neautorizate sau ilegale si împotriva pierderii, a distrugerii sau a deteriorării accidentale.</p>
<p style="text-align:justify;margin-bottom:8px;">6.2. In procesul de prelucrarea a datelor cu caracter personal, Asociația aplica prevederile Regulamentului (UE) 2016/679 al Parlamentului European si al Consiliului din 27 aprilie 2016 privind protecția persoanelor fizice in ceea ce priveşte prelucrarea datelor cu caracter personal si privind libera circulație a acestor date si de abrogare a Directivei 95/46/CE (regulamentul general privind protecția datelor) si ale legislației naționale.</p>
<p style="text-align:justify;margin-bottom:8px;">6.3. Datele cu caracter personal ale voluntarului, comunicate in cadrul prezentului contract, vor fi prelucrate de Asociație in scopul executării prezentului contract la care voluntarul este parte contractantă.</p>
<p style="text-align:justify;margin-bottom:8px;">6.4. Datele cu caracter personal colectate şi prelucrate în vederea executării prezentului contract sunt următoarele: nume şi prenume, adresa, serie şi număr carte de identitate, cod numeric personal, număr de telefon, adresa de poşta electronică.</p>
<p style="text-align:justify;margin-bottom:8px;">6.5. Datele personale ale voluntarului, comunicate in cadrul prezentului contract, pot fi comunicate de Asociație instituțiilor publice, in conformitate cu obligațiile legale care ii revin acestuia.</p>
<p style="text-align:justify;margin-bottom:8px;">6.6. In situația in care este necesara prelucrarea datelor personale ale voluntarului in alte scopuri decât cele prevăzute la alin. 6.3. Asociația va informa voluntarul şi îi va solicita acordul scris cu privire la prelucrarea datelor cu caracter personal, în conformitate cu prevederile legislației în vigoare.</p>
<p style="text-align:justify;margin-bottom:8px;">6.7. Asociația asigură dreptul voluntarului la informare şi acces la datele cu caracter personal, dreptul la rectificare, actualizare, portabilitate, ştergere, la restricționare si opoziție în conformitate cu prevederile legislației în vigoare.</p>
<p style="text-align:justify;margin-bottom:16px;">6.8. Datele personale ale voluntarului sunt păstrate de către Asociație pe întreaga perioada de executare a contractului si ulterior încetării acestuia, in conformitate cu prevederile legale referitoare la arhivarea documentelor.</p>
<h2 style="font-size:14px;font-weight:700;margin:20px 0 10px;">VII. CLAUZA DE CONFIDENȚIALITATE</h2>
<p style="text-align:justify;margin-bottom:8px;">7.1 Voluntarul se obligă să păstreze secretul cu privire la activitățile Asociației „EDUCAȞIE ÎN SECURITATE CIBERNETICĂ” şi să nu utilizeze astfel de informații nici în interes propriu şi personal şi nici să nu le dezvăluie altor persoane. Prin informații cu privire la activitățile Asociației se înteleg acele informații care nu sunt în general cunoscute sau nu sunt uşor accesibile persoanelor din mediul care se ocupă în mod obişnuit cu acest gen de informații.</p>
<p style="text-align:justify;margin-bottom:8px;">7.2 Voluntarul se obligă ca pe toată durata derulării şi după încetarea prezentului contract, pe o perioadă de <strong>3 ani</strong>, să nu folosească pentru uzul propriu sau al altora, să nu divulge, reproducă, sintetizeze sau să distribuie datele sau informațiile aflate în posesia sa.</p>
<p style="text-align:justify;margin-bottom:8px;">7.3 În cazul în care voluntarul săvârşeşte un act care contravine clauzei de confidențialitate, va fi obligat să înceteze sau să înlăture actul şi să restituie documentele confidențiale însuşite în mod ilicit de la Asociație.</p>
<p style="text-align:justify;margin-bottom:16px;">7.4 ÎnCălcarea acestui acord de confidențialitate de către voluntar va atrage răspunderea contractuală a acestuia în condițiile prevăzute de legislația în vigoare.</p>
<h2 style="font-size:14px;font-weight:700;margin:20px 0 10px;">VIII. CLAUZE FINALE</h2>
<p style="text-align:justify;margin-bottom:8px;">8.1. Modificarea prezentului contract se face numai prin act adițional încheiat intre părțile contractante.</p>
<p style="text-align:justify;margin-bottom:8px;">8.2. Prezentul contract, împreună cu anexele sale care fac parte integranta din cuprinsul sau, reprezintă voința parților şi înlătură orice altă înțelegere verbala dintre acestea, anterioară sau ulterioară încheierii lui.</p>
<p style="text-align:justify;margin-bottom:8px;">8.3. Prezentul contract este clasificat ca fiind <strong>Confidențial (date personale)</strong> cu acces limitat la HR, Coordonator voluntari şi Reprezentanții legali.</p>
<p style="text-align:justify;margin-bottom:24px;">8.4. Prezentul contract a fost întocmit şi semnat în format electronic. Forma electronică reprezintă forma originală a acestui contract.</p>
<p style="margin-bottom:20px;">Asociația <strong>„EDUCAȞIE ÎN SECURITATE CIBERNETICĂ”</strong></p>
<table style="width:100%;margin-top:20px;"><tr>
<td style="width:50%;padding-right:24px;vertical-align:top;">
<p style="font-weight:700;color:#1e3a5f;margin-bottom:4px;">Preşedinte</p>
<p style="font-size:12px;margin-bottom:4px;">${USER_NAME}</p>
<img src="${DOC_SIG_URL}" style="height:58px;margin:6px 0 0;" alt="Semnătură">
<p style="margin-top:4px;border-top:1px solid #e2e8f0;padding-top:4px;font-size:11px;color:#94a3b8;">Semnătură şi ştampilă</p>
</td>
<td style="width:50%;padding-left:24px;vertical-align:top;">
<p style="font-weight:700;color:#1e3a5f;margin-bottom:4px;">Voluntar</p>
<p style="font-size:12px;margin-bottom:4px;">{client_name}</p>
<div style="height:58px;margin:6px 0 0;"></div>
<p style="margin-top:4px;border-top:1px solid #e2e8f0;padding-top:4px;font-size:11px;color:#94a3b8;">Semnătură</p>
</td>
</tr></table></div>`,

// Contract WHT — același corp, titlu diferit
  contract_wht: () => buildHeader('CONTRACT DE PRESTĂRI SERVICII SECURITATE CIBERNETICĂ — WHITE HAT TECHNOLOGY') + `

<p style="margin-bottom:20px;text-align:justify;font-size:13.5px;">
Prezentul Contract de Prestări Servicii (<strong>„Contractul”</strong>) este încheiat la data de <strong>{date}</strong> între:
</p>

<table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:13px;">
<tr style="background:#f8fafc;">
  <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:700;width:160px;">PRESTATOR</td>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;">${PROFILE_NAME}, CUI ${PROFILE_CUI}, sediu: ${PROFILE_ADDR}, e-mail: ${PROFILE_EMAIL}, denumit în continuare <em>„Prestatorul”</em>.</td>
</tr>
<tr>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:700;">BENEFICIAR</td>
  <td style="padding:8px 12px;border:1px solid #e2e8f0;">{client_name}, CUI {client_cui}, sediu: {client_address}, denumit în continuare <em>„Beneficiarul”</em>.</td>
</tr>
</table>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">1. DEFINIȚII ȘI INTERPRETARE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">1.1 În prezentul Contract, termenii următori au ënțelesurile indicate: <strong>„Contract”</strong> — prezentul Contract, inclusiv Anexele; <strong>„Servicii”</strong> — ansamblul prestațiilor convenite; <strong>„Librabile”</strong> — documentele, rapoartele şi rezultatele concrete ale Serviciilor; <strong>„Onorariu”</strong> — suma totală datorată; <strong>„Zi Lucrătoare”</strong> — luni–vineri, exclusiv sărbători legale.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">1.2 Anexele fac parte integrantă din Contract. În caz de conflict, prevederile Contractului prevalează față de Anexe, dacă nu se specifică altfel în mod expres.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">2. INTRAREA În VIGOARE ȘI DURATA</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.1 Contractul intră în vigoare la data semnării de către ambele Părți şi se încheie pe durată determinată, până la finalizarea Serviciilor şi predarea tuturor Livrabilelor, dar nu mai târziu de termenele convenite în Anexă.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">2.2 Prelungirea Contractului se face numai prin act adițional semnat de ambele Părți.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">3. OBIECTUL CONTRACTULUI</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">3.1 Prestatorul se obligă să furnizeze Beneficiarului, pe durata Contractului, serviciile specializate de securitate cibernetică descrise în Anexă.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">3.2 Beneficiarul se obligă să pună la dispoziția Prestatorului toate accesele, credențialele, documentațiile şi informațiile necesare executării Serviciilor.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">3.3 Prestatorul va presta Serviciile cu diligența unui profesionist din domeniu, respectând cele mai bune practici şi standarde internaționale aplicabile.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">4. ONORARII ȘI MODALITĂȚI DE PLATĂ</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.1 Pentru furnizarea integrală a Serviciilor, Beneficiarul va plăti Prestatorului Onorariul prevăzut în Anexă.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.2 Prestatorul va emite factura în termen de 5 zile calendaristice de la finalizarea etapei convenite. Plata se va efectua prin transfer bancar în termen de 5 zile calendaristice de la emiterea facturii.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">4.3 În caz de întârziere la plată, suma datorată se majorează cu penalizări de 0,1% pe zi de întârziere, calculate din ziua ulterioară scadenței.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">5. RĂSPUNDERE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">5.1 Răspunderea Prestatorului pentru prejudicii directe nu va depăşi valoarea totală a Onorariului plătit în baza prezentului Contract.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">5.2 Limitările de răspundere nu se aplică prejudiciilor cauzate prin dol sau culpă gravă.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">6. INFORMAȚII CONFIDENȚIALE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">6.1 Prestatorul va păstra stricta confidențialitate a tuturor Informațiilor Confidențiale ale Beneficiarului. Obligația de confidențialitate se menține pe durata Contractului şi pentru o perioadă de 5 ani de la data încetării acestuia. Încălcarea dă dreptul Beneficiarului la daune minime de 50.000 EUR.</p>

<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">7. DISPOZIȚII FINALE</h2>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">7.1 <strong>Legea aplicabilă:</strong> Legea română. <strong>Litigii:</strong> Soluționare amiabilă (min. 30 zile), ulterior instanțele competente din România.</p>
<p style="text-align:justify;font-size:13.5px;margin-bottom:10px;">7.2 <strong>Integralitate:</strong> Prezentul Contract reprezintă acordul integral şi înlocuieşte orice înțelegeri anterioare. <strong>Modificări:</strong> Numai prin act adițional semnat de ambele Părți.</p>
` + buildFooter(),

  oferta_cs: () => buildHeader('OFERTĂ DE SERVICII') + `
<p style="text-align:center;font-size:14px;font-weight:700;margin-bottom:4px;">Program de Formare în Securitate Cibernetică</p>
<p style="text-align:center;color:#64748b;font-size:12px;margin-bottom:24px;">Destinatar: {client_name} | Data: {date}</p>
<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">1. De ce securitatea cibernetică contează pentru echipa ta</h2>
<p style="text-align:justify;margin-bottom:10px;">Intr-o lume in care atacurile digitale devin tot mai sofisticate, angajatii reprezinta prima — si adesea cea mai vulnerabila — linie de aparare a unei organizatii. Escrocii nu mai ataca doar sistemele informatice: ei vizeaza oamenii, prin e-mailuri false, apeluri telefonice manipulatoare si continut generat cu inteligenta artificiala.</p>
<p style="text-align:justify;margin-bottom:10px;">Costul unei singure brese de securitate poate depasi cu mult costul pregatirii intregii echipe. Formarea angajatilor {client_name} este o investitie directa in protectia datelor clientilor si in reputatia organizatiei.</p>
<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">2. Despre CyberShield</h2>
<p style="text-align:justify;margin-bottom:10px;">CyberShield este o organizatie specializata in educatie digitala si securitate cibernetica, cu experienta in formarea unor audiente variate — de la elevi si cadre didactice, la angajati din mediul corporativ, ofițers de penitenciar si functionari publici. Abordarea noastra este practica, accesibila si adaptata realitatilor din Romania.</p>
<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">3. Programul: "Digital Threat Awareness"</h2>
<p style="text-align:justify;margin-bottom:10px;">Un program interactiv, cu exemple reale si exercitii practice, conceput pentru angajatii care lucreaza cu date sensibile ale clientilor si care utilizeaza zilnic internetul, e-mailul si comunicatiile digitale.</p>
<p style="font-weight:700;margin-bottom:8px;">Teme abordate:</p>
<p style="padding-left:20px;margin-bottom:4px;">▪ Navigare sigura pe internet — recunoasterea site-urilor false, conexiuni securizate, riscuri la retele Wi-Fi publice</p>
<p style="padding-left:20px;margin-bottom:4px;">▪ Phishing si scam-uri — cum arata un e-mail periculos, tehnici de manipulare, simulari practice</p>
<p style="padding-left:20px;margin-bottom:4px;">▪ Spoofing si apeluri false — atacuri prin falsificarea numarului de telefon al unor institutii sau persoane de incredere</p>
<p style="padding-left:20px;margin-bottom:4px;">▪ Voice cloning si deepfake audio — fraude prin voce generata cu AI: cum le recunosti si cum te protejezi (conform materialelor ANP)</p>
<p style="padding-left:20px;margin-bottom:4px;">▪ Inginerie sociala — manipularea psihologica, urgenta artificiala, pretextul de autoritate</p>
<p style="padding-left:20px;margin-bottom:4px;">▪ Protectia datelor clientilor — GDPR de baza, ce nu trimiti pe e-mail/chat, cum gestionezi datele sensibile</p>
<p style="padding-left:20px;margin-bottom:4px;">▪ Securitatea parolelor si a conturilor — autentificare in doi pasi, manageri de parole, semne ale unui cont compromis</p>
<p style="padding-left:20px;margin-bottom:16px;">▪ Reactia corecta la un incident — ce faci daca ai dat click gresit, cui raportezi, cum limitezi paguba</p>
<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">4. Variante de livrare si preturi</h2>
<table style="width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:12px;">
<tr><th style="border:1px solid #1e3a5f;padding:10px 14px;background:#1e3a5f;color:#fff;width:50%;text-align:left;">🖥 ONLINE (ZOOM)</th><th style="border:1px solid #1e3a5f;padding:10px 14px;background:#1e3a5f;color:#fff;width:50%;text-align:left;">🏢 CU PREZENȚA FIZICĂ</th></tr>
<tr><td style="border:1px solid #e2e8f0;padding:8px 14px;vertical-align:top;"><p style="margin-bottom:4px;"><strong>Durată:</strong> 3 zile x 2 ore/zi</p><p style="margin-bottom:4px;"><strong>Platforma:</strong> Zoom</p><p style="margin-bottom:4px;"><strong>Grup minim:</strong> 10 participanți</p><p style="margin-bottom:4px;"><strong>Preț / participant:</strong> 799 lei</p><p style="font-size:11px;color:#64748b;font-style:italic;">Fără costuri adiționale de deplasare</p></td>
<td style="border:1px solid #e2e8f0;padding:8px 14px;vertical-align:top;"><p style="margin-bottom:4px;"><strong>Durată:</strong> 2 zile x 3 ore/zi</p><p style="margin-bottom:4px;"><strong>Locație:</strong> La sediul beneficiarului</p><p style="margin-bottom:4px;"><strong>Grup minim:</strong> 10 participanți</p><p style="margin-bottom:4px;"><strong>Preț / participant:</strong> 1.240 lei</p><p style="font-size:11px;color:#64748b;font-style:italic;">* Se adaugă costuri de transport şi cazare, variabile după locația beneficiarului</p></td></tr>
</table>
<p style="font-size:12px;color:#475569;font-style:italic;margin-bottom:16px;">Ambele variante includ materiale de suport digitale, sesiuni de Q&amp;A si certificat de participare pentru fiecare angajat.</p>
<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">5. Ce include pachetul</h2>
<p style="padding-left:20px;margin-bottom:4px;">✓ Conținut actualizat cu cele mai recente tipuri de atacuri (inclusiv AI-generate)</p>
<p style="padding-left:20px;margin-bottom:4px;">✓ Trainer certificat cu experiență în domeniu</p>
<p style="padding-left:20px;margin-bottom:4px;">✓ Materiale de suport (PDF + fişe practice) trimise participanților</p>
<p style="padding-left:20px;margin-bottom:4px;">✓ Sesiune de întrebări &amp; răspunsuri la finalul fiecărei zile</p>
<p style="padding-left:20px;margin-bottom:4px;">✓ Certificat de participare pentru fiecare angajat</p>
<p style="padding-left:20px;margin-bottom:16px;">✓ Suport post-curs (30 de zile) prin e-mail pentru întrebări punctuale</p>
<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">6. De ce CyberShield?</h2>
<p style="padding-left:20px;margin-bottom:4px;">▪ Experiență dovedită în formarea adulților şi a angajaților din mediul corporativ</p>
<p style="padding-left:20px;margin-bottom:4px;">▪ Conținut 100% adaptat la realitățile din Moldova şi Romania, nu traduceri din materiale occidentale</p>
<p style="padding-left:20px;margin-bottom:4px;">▪ Abordare practică, nu teoretică — angajații pleacă cu instrumente concrete de aplicat imediat</p>
<p style="padding-left:20px;margin-bottom:16px;">▪ Conținut actualizat permanent, inclusiv cu amenințări bazate pe inteligență artificială (voice cloning, deepfakes)</p>
<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">7. Pasul următor</h2>
<p style="text-align:justify;margin-bottom:10px;">Suntem la dispoziția dumneavoastră pentru orice detalii suplimentare, pentru a stabili o dată convenabilă sau pentru a adapta programul nevoilor specifice ale echipei {client_name}.</p>
<p style="font-style:italic;color:#64748b;margin-bottom:20px;">Oferta este valabilă 30 de zile de la data emiterii.</p>
<div style="margin-top:30px;text-align:right;"><p>Cu stimă,</p><p style="font-weight:700;">${USER_NAME}</p><p>Preşedinte, ${PROFILE_NAME}</p><p style="color:#64748b;font-size:12px;">${PROFILE_EMAIL} | ${PROFILE_PHONE}</p></div></div>`,

  oferta_wht: () => buildHeader('OFERTĂ DE SERVICII — WHITE HAT TECHNOLOGY') + `
<p style="text-align:justify;margin-bottom:10px;">Suntem o companie românească specializată în servicii de securitate cibernetică, audit informatic şi dezvoltare de soluții software personalizate pentru instituții publice şi organizații private. Ne-am construit reputația oferind servicii profesioniste, riguroase şi transparente, în linie cu cerințele legale şi cele mai bune practici din domeniu.</p>
<p style="text-align:justify;margin-bottom:20px;">Simulăm atacuri reale pentru a identifica riscurile înainte ca acestea să se transforme în breşe de securitate. Echipa noastră combină metode de hacking, audituri de infrastructură, evaluări ale securității fizice şi simulări de phishing pentru a testa cât de sigură este cu adevărat organizația dumneavoastră.</p>
<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">PORTOFOLIU DE SERVICII</h2>
<h3 style="font-size:13px;font-weight:700;margin:16px 0 8px;">AGENȚI VOCALI CU INTELIGENȚĂ ARTIFICIALĂ</h3>
<p style="text-align:justify;margin-bottom:10px;">White Hat Technology pune la dispoziție soluții avansate de automatizare a comunicării prin intermediul agenților vocali bazați pe inteligență artificială. Aceste sisteme funcționează atât în regim inbound (preluarea apelurilor primite), cât şi în regim outbound (inițierea de apeluri automate), în limba română, cu un dialog natural, clar şi adaptat contextului specific al fiecărei organizații.</p>
<table style="width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:12px;">
<tr><td style="border:1px solid #e2e8f0;padding:8px 12px;vertical-align:top;width:50%;"><p style="font-weight:700;margin-bottom:6px;">INBOUND — Preluarea apelurilor primite</p><p style="padding-left:12px;margin-bottom:3px;">· Trierea şi preluarea cererilor de la clienți</p><p style="padding-left:12px;margin-bottom:3px;">· Furnizarea de informații standardizate</p><p style="padding-left:12px;margin-bottom:3px;">· Colectarea structurată a datelor apelantului</p><p style="padding-left:12px;margin-bottom:3px;">· Escaladare automată la operator uman</p><p style="padding-left:12px;margin-bottom:0;">· Gestionarea programărilor şi confirmărilor</p></td>
<td style="border:1px solid #e2e8f0;padding:8px 12px;vertical-align:top;width:50%;"><p style="font-weight:700;margin-bottom:6px;">OUTBOUND — Inițierea de apeluri automate</p><p style="padding-left:12px;margin-bottom:3px;">· Campanii proactive de informare a clienților</p><p style="padding-left:12px;margin-bottom:3px;">· Sondaje şi colectare de feedback la scară mare</p><p style="padding-left:12px;margin-bottom:3px;">· Remindere automate (plăți, programări, acțiuni)</p><p style="padding-left:12px;margin-bottom:3px;">· Monitorizarea periodică a portofoliului de clienți</p><p style="padding-left:12px;margin-bottom:0;">· Pre-filtrarea oportunităților de vânzare</p></td></tr>
</table>
<p style="text-align:justify;margin-bottom:16px;"><strong>Securitate şi conformitate GDPR:</strong> Datele sunt stocate securizat, accesul este controlat pe principiul least privilege, iar prelucrările sunt limitate strict la scopul definit, în conformitate cu GDPR. Implementarea este propusă etapizat: pilot, configurare scenarii, testare, extindere graduală.</p>
<h3 style="font-size:13px;font-weight:700;margin:16px 0 8px;">CAMPANII DE PREVENIRE ŞI CURSURI DE SECURITATE CIBERNETICĂ</h3>
<p style="text-align:justify;margin-bottom:10px;">Cea mai vulnerabilă componentă a oricărei organizații nu este tehnologia — ci factorul uman. White Hat Technology oferă un program complet de educație şi conştientizare, conceput să transforme comportamentul organizației de la interior.</p>
<p style="font-weight:700;margin-bottom:8px;">Cursuri şi workshop-uri structurate:</p>
<p style="padding-left:20px;margin-bottom:4px;">✓ Cursuri de bază pentru angajați fără background tehnic — igienă digitală şi comportament securizat</p>
<p style="padding-left:20px;margin-bottom:4px;">✓ Workshop-uri pentru echipele IT — configurare securizată, patch management, răspuns la incidente</p>
<p style="padding-left:20px;margin-bottom:4px;">✓ Sesiuni pentru management şi CISO — risc cibernetic, conformitate NIS2/GDPR, decizii strategice</p>
<p style="padding-left:20px;margin-bottom:4px;">✓ Training pentru echipele de development — programare securizată (Secure SDLC)</p>
<p style="padding-left:20px;margin-bottom:12px;">✓ Pregătire pentru certificări în securitate cibernetică</p>
<p style="font-weight:700;margin-bottom:8px;">Exerciții de phishing şi raportare:</p>
<p style="text-align:justify;margin-bottom:16px;">Campanii cu e-mailuri-momeală personalizate, monitorizarea reacțiilor şi livrarea unui raport care cuantifică expunerea organizației: rata de click, rata de divulgare a credențialelor, analiza pe departamente şi recomandări personalizate. Durată: 3–4 zile.</p>
<h3 style="font-size:13px;font-weight:700;margin:16px 0 8px;">TESTE DE PENETRARE — METODOLOGIE ŞI STANDARDE</h3>
<table style="width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:16px;">
<tr><td style="border:1px solid #e2e8f0;padding:8px 12px;vertical-align:top;width:50%;"><p style="font-weight:700;margin-bottom:6px;">Standarde &amp; cadre:</p><p style="padding-left:12px;margin-bottom:3px;">· OWASP — testarea aplicațiilor web şi API</p><p style="padding-left:12px;margin-bottom:3px;">· NIS 2 — cadru de conformitate europeană</p><p style="padding-left:12px;margin-bottom:3px;">· ISO/IEC 27001 — managementul securității informației</p><p style="padding-left:12px;margin-bottom:3px;">· GDPR — protecția datelor cu caracter personal</p><p style="padding-left:12px;margin-bottom:3px;">· NIST SP 800 — publicații speciale NIST</p><p style="padding-left:12px;margin-bottom:3px;">· PTES — standard pentru teste de penetrare</p><p style="padding-left:12px;margin-bottom:0;">· OSSTMM — metodologie pentru audituri de securitate</p></td>
<td style="border:1px solid #e2e8f0;padding:8px 12px;vertical-align:top;width:50%;"><p style="font-weight:700;margin-bottom:6px;">Abordare metodologică:</p><p style="margin-bottom:6px;">Fiecare angajament parcurge etape bine definite:</p><p style="padding-left:12px;margin-bottom:3px;">1. Planificare &amp; definire scop (scoping)</p><p style="padding-left:12px;margin-bottom:3px;">2. Culegere de informații (reconnaissance)</p><p style="padding-left:12px;margin-bottom:3px;">3. Testare activă (manuală + automată)</p><p style="padding-left:12px;margin-bottom:3px;">4. Analiza şi corelarea constatărilor</p><p style="padding-left:12px;margin-bottom:3px;">5. Elaborarea raportului de audit</p><p style="padding-left:12px;margin-bottom:3px;">6. Debriefing cu echipa clientului</p><p style="font-style:italic;font-size:11px;margin-top:8px;">Niciun test nu este inițiat fără autorizare scrisă prealabilă.</p></td></tr>
</table>
<h2 style="font-size:14px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:4px;margin:24px 0 12px;">OFERTĂ DE PREȚURI — {client_name}</h2>
<p style="font-size:12px;color:#64748b;margin-bottom:12px;">Prețuri cu discount aplicat de 15% — prima colaborare. Prețurile nu includ TVA.</p>
<table style="width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:16px;">
<tr style="background:#1e3a5f;color:#fff;"><th style="border:1px solid #1e3a5f;padding:8px 12px;text-align:left;">Serviciu</th><th style="border:1px solid #1e3a5f;padding:8px 12px;text-align:right;">Preț</th><th style="border:1px solid #1e3a5f;padding:8px 12px;text-align:right;">Durată</th></tr>
<tr><td style="border:1px solid #e2e8f0;padding:7px 12px;">Campanie de prevenire (sesiune de conştientizare)<br><span style="font-size:11px;color:#64748b;">Per zi · max. 2 grupe / zi</span></td><td style="border:1px solid #e2e8f0;padding:7px 12px;text-align:right;font-weight:700;">350 € / zi</td><td style="border:1px solid #e2e8f0;padding:7px 12px;text-align:right;">La cerere</td></tr>
<tr style="background:#f8fafc;"><td style="border:1px solid #e2e8f0;padding:7px 12px;">Campanie de phishing simulat şi raportare<br><span style="font-size:11px;color:#64748b;">Per participant · minim 50 adrese e-mail</span></td><td style="border:1px solid #e2e8f0;padding:7px 12px;text-align:right;font-weight:700;">13,5 € / participant</td><td style="border:1px solid #e2e8f0;padding:7px 12px;text-align:right;">3–4 zile</td></tr>
<tr><td style="border:1px solid #e2e8f0;padding:7px 12px;">Audit securitate cibernetică — site {client_name}<br><span style="font-size:11px;color:#64748b;">Grey pentest (user fără cont admin / fără acces cod sursă)</span></td><td style="border:1px solid #e2e8f0;padding:7px 12px;text-align:right;font-weight:700;">1.100 €</td><td style="border:1px solid #e2e8f0;padding:7px 12px;text-align:right;">3–4 zile</td></tr>
<tr style="background:#f8fafc;"><td style="border:1px solid #e2e8f0;padding:7px 12px;">Audit de securitate fizică<br><span style="font-size:11px;color:#64748b;">Compromitere spații, dispozitive interceptare, extragere echipament</span></td><td style="border:1px solid #e2e8f0;padding:7px 12px;text-align:right;font-weight:700;">6.300 €</td><td style="border:1px solid #e2e8f0;padding:7px 12px;text-align:right;">3–5 zile</td></tr>
<tr><td style="border:1px solid #e2e8f0;padding:7px 12px;">Audit aplicație web — date financiare/personale clienți<br><span style="font-size:11px;color:#64748b;">Condiționat de acceptul scris al furnizorului aplicației</span></td><td style="border:1px solid #e2e8f0;padding:7px 12px;text-align:right;font-weight:700;">4.800 €</td><td style="border:1px solid #e2e8f0;padding:7px 12px;text-align:right;">10–15 zile</td></tr>
</table>
<p style="font-size:12px;font-style:italic;color:#64748b;margin-bottom:16px;"><strong>Discount 15% — prima colaborare.</strong> Toate prețurile au fost calculate cu un discount de 15% față de tarifele standard, ca semn de apreciere pentru această primă colaborare. Oferta este valabilă 30 de zile de la data emiterii.</p>
<p style="font-size:12px;color:#475569;margin-bottom:20px;"><strong>Prestatori:</strong> Serviciile prevăzute în această ofertă pot fi prestate atât de White Hat Technology, cât şi de Asociația Educație în Securitate Cibernetică, în funcție de strategiile de CSR aplicate la nivelul companiei {client_name}.</p>
<div style="margin-top:30px;text-align:right;"><p>Cu stimă,</p><p style="font-weight:700;">${USER_NAME}</p><p>White Hat Technology</p><p style="color:#64748b;font-size:12px;">${PROFILE_EMAIL} | ${PROFILE_PHONE}</p></div></div>`
};

function applyDocTemplate(type) {
  const editor = document.getElementById('docEditor');
  if (!editor) return;
  if (HAS_EXISTING && editor.innerHTML.trim().length > 100 && !confirm('Înlocuiești conținutul existent cu template-ul ' + type.toUpperCase() + '?')) return;
  const fn = TEMPLATES[type];
  if (!fn) return;
  editor.innerHTML = fn();
  const titleMap = {
    nda_cs: 'NDA — CyberShield',
    nda_wht: 'NDA — White Hat Technology',
    contract_cs: 'Contract Servicii — CyberShield',
    contract_wht: 'Contract Servicii — White Hat Technology',
    voluntariat: 'Contract de Voluntariat',
    oferta_cs: 'Ofertă Servicii CyberShield',
    oferta_wht: 'Ofertă Servicii White Hat Technology'
  };
  const titleField = document.querySelector('[name="title"]');
  if (titleField && !titleField.value) titleField.value = titleMap[type] || '';
  const typeSelect = document.getElementById('docTypeSelect');
  if (typeSelect) typeSelect.value = type;
}

// Auto-apply template on page load for new docs
if (!HAS_EXISTING) {
  const t = document.getElementById('docTypeSelect')?.value || 'nda';
  if (TEMPLATES[t]) setTimeout(() => applyDocTemplate(t), 50);
}
</script>

<?php elseif ($action === 'view' && $docId):
    $doc = DB::fetchOne("SELECT d.*, c.company_name, c.cui as client_cui, c.address as client_address, c.email as client_email, c.phone as client_phone FROM documents d JOIN clients c ON d.client_id = c.id WHERE d.id = ? AND d.profile_id = ?", [$docId, $profileId]);
    if (!$doc) { flash('error', 'Negăsit.'); Router::redirect('documents'); }
    $html = str_replace(
        ['{profile_name}','{client_name}','{client_cui}','{client_address}','{client_email}','{doc_number}','{date}','{user_name}','{profile_address}'],
        [$profile['legal_name']?:$profile['name'], $doc['company_name'], $doc['client_cui']??'', $doc['client_address']??'', $doc['client_email']??'', $doc['doc_number']??'', formatDate($doc['created_at']), Auth::userName(), $profile['address']??''],
        $doc['content_html']??''
    );
    $pageTitle = ($doc['doc_number']?:strtoupper($doc['doc_type'])) . ' — ' . $doc['company_name'];
?>
<div class="flex-between mb-2">
  <a href="<?= Router::url('documents') ?>" class="text-muted" style="font-size:13px;">← Înapoi la documente</a>
  <div style="display:flex;gap:8px;">
    <a href="<?= Router::url("documents/{$docId}/edit") ?>" class="btn btn-outline btn-sm">Editează</a>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Sigur ștergi documentul?')">
      <?= csrf() ?><input type="hidden" name="_action" value="delete_document"><input type="hidden" name="doc_id" value="<?= $docId ?>">
      <button type="submit" class="btn btn-danger btn-sm">Șterge</button>
    </form>
  </div>
</div>

<div class="card mb-2">
  <div class="card-body" style="font-size:13px;">
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
      <div><span class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Tip</span><br><strong><?= strtoupper(e($doc['doc_type'])) ?></strong></div>
      <div><span class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Număr</span><br><strong><?= e($doc['doc_number']?:'-') ?></strong></div>
      <div><span class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Client</span><br><?= e($doc['company_name']) ?></div>
      <div><span class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Status</span><br><?= statusBadge($doc['status']) ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2>Conținut document</h2></div>
  <div class="card-body">
    <div style="border:1px solid var(--border);border-radius:var(--radius);padding:40px 48px;background:#fff;font-family:Arial,sans-serif;font-size:13.5px;line-height:1.8;max-width:800px;margin:0 auto;box-shadow:var(--shadow-xs);">
      <?= str_replace(
        ['<img src="{logo_url}"', 'src="{logo_url}"'],
        ['<img src="' . APP_URL . '/assets/img/' . $logoFile . '"', 'src="' . APP_URL . '/assets/img/' . $logoFile . '"'],
        $html
      ) ?>
    </div>
  </div>
</div>

<?php endif; ?>

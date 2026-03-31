<?php
$profileId = Auth::profileId();
$action = $_GET['action'] ?? 'list';
$campaignId = (int)($_GET['id'] ?? 0);
$pageTitle = 'Campanii Email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['_action'] ?? '';
    if ($pa === 'save_campaign') {
        $data = ['profile_id'=>$profileId, 'name'=>trim($_POST['name']??''), 'subject'=>trim($_POST['subject']??''), 'body_html'=>$_POST['body_html']??'', 'daily_limit'=>(int)($_POST['daily_limit']??400), 'status'=>$_POST['status']??'draft'];
        $cid = (int)($_POST['campaign_id'] ?? 0);
        if ($cid) { DB::update('email_campaigns', $data, 'id = ? AND profile_id = ?', [$cid, $profileId]); flash('success', 'Campanie actualizată.'); Router::redirect('campaigns/' . $cid); }
        else { $newId = DB::insert('email_campaigns', $data); flash('success', 'Campanie creată.'); Router::redirect('campaigns/' . $newId); }
    }
    if ($pa === 'upload_csv') {
        $cid = (int)$_POST['campaign_id'];
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $header = fgetcsv($handle, 0, ','); $header = array_map(fn($h) => strtolower(trim($h)), $header ?: []);
            $emailCol = array_search('email', $header); $nameCol = array_search('name', $header) !== false ? array_search('name', $header) : array_search('nume', $header);
            $companyCol = array_search('company', $header) !== false ? array_search('company', $header) : array_search('companie', $header);
            $cuiCol = array_search('cui', $header); if ($emailCol === false) $emailCol = 0;
            $count = 0;
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $email = trim($row[$emailCol] ?? ''); if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                $exists = DB::fetchOne("SELECT id FROM campaign_recipients WHERE campaign_id = ? AND email = ?", [$cid, $email]); if ($exists) continue;
                DB::insert('campaign_recipients', ['campaign_id'=>$cid, 'email'=>$email, 'name'=>$nameCol!==false?trim($row[$nameCol]??''):'', 'company'=>$companyCol!==false?trim($row[$companyCol]??''):'', 'cui'=>$cuiCol!==false?trim($row[$cuiCol]??''):'']);
                $count++;
            }
            fclose($handle);
            $total = DB::count('campaign_recipients', 'campaign_id = ?', [$cid]);
            DB::update('email_campaigns', ['total_recipients'=>$total], 'id = ?', [$cid]);
            flash('success', "$count destinatari importați.");
        }
        Router::redirect('campaigns/' . $cid);
    }
    if ($pa === 'upload_attachment') {
        $cid = (int)$_POST['campaign_id'];
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $path = uploadFile($_FILES['attachment'], 'campaigns/' . $cid);
            if ($path) { $campaign = DB::fetchOne("SELECT attachment_paths FROM email_campaigns WHERE id = ?", [$cid]); $att = json_decode($campaign['attachment_paths']??'[]', true)?:[]; $att[] = ['path'=>$path, 'name'=>$_FILES['attachment']['name']]; DB::update('email_campaigns', ['attachment_paths'=>json_encode($att)], 'id = ?', [$cid]); flash('success', 'Atașament adăugat.'); }
        }
        Router::redirect('campaigns/' . $cid);
    }
    if ($pa === 'start_campaign') { $cid=(int)$_POST['campaign_id']; DB::update('email_campaigns', ['status'=>'active','started_at'=>date('Y-m-d H:i:s')], 'id = ? AND profile_id = ?', [$cid, $profileId]); flash('success', 'Campanie pornită!'); Router::redirect('campaigns/' . $cid); }
    if ($pa === 'pause_campaign') { $cid=(int)$_POST['campaign_id']; DB::update('email_campaigns', ['status'=>'paused'], 'id = ? AND profile_id = ?', [$cid, $profileId]); flash('success', 'Pauză.'); Router::redirect('campaigns/' . $cid); }
    if ($pa === 'delete_campaign') { DB::delete('email_campaigns', 'id = ? AND profile_id = ?', [(int)$_POST['campaign_id'], $profileId]); flash('success', 'Campanie ștearsă.'); Router::redirect('campaigns'); }
}

if ($action === 'list'):
    $campaigns = DB::fetchAll("SELECT * FROM email_campaigns WHERE profile_id = ? ORDER BY created_at DESC", [$profileId]);
?>
<div class="flex-between mb-2"><div></div><a href="<?= Router::url('campaigns/new') ?>" class="btn btn-primary">+ Campanie nouă</a></div>
<div class="card">
<?php if (empty($campaigns)): ?><div class="empty-state"><div class="empty-icon">⚡</div><h3>Nicio campanie</h3></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th>Campanie</th><th>Subiect</th><th>Dest.</th><th>Trimise</th><th>Deschise</th><th>Erori</th><th>Status</th></tr></thead><tbody>
<?php foreach ($campaigns as $c): ?><tr onclick="location.href='<?= Router::url("campaigns/{$c['id']}") ?>'"><td><strong><?= e($c['name']) ?></strong></td><td class="text-muted"><?= e(mb_substr($c['subject'],0,40)) ?></td><td><?= $c['total_recipients'] ?></td><td><?= $c['total_sent'] ?></td><td class="text-success"><?= $c['total_opened'] ?></td><td class="text-danger"><?= $c['total_failed'] ?></td><td><?= statusBadge($c['status']) ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>

<?php elseif ($action === 'new' || $action === 'edit'):
    $campaign = null; if ($action==='edit' && $campaignId) $campaign = DB::fetchOne("SELECT * FROM email_campaigns WHERE id = ? AND profile_id = ?", [$campaignId, $profileId]);
?>
<div class="card" style="max-width:900px;"><div class="card-header"><h2><?= $campaign ? 'Editare' : 'Campanie nouă' ?></h2></div>
<form method="POST" class="card-body"><?= csrf() ?><input type="hidden" name="_action" value="save_campaign"><input type="hidden" name="campaign_id" value="<?= $campaign['id']??'' ?>">
<div class="form-row"><div class="form-group"><label>Denumire *</label><input type="text" name="name" class="form-control" required value="<?= e($campaign['name']??'') ?>"></div><div class="form-group"><label>Limită/zi</label><input type="number" name="daily_limit" class="form-control" value="<?= $campaign['daily_limit']??400 ?>"></div></div>
<div class="form-group"><label>Subiect *</label><input type="text" name="subject" class="form-control" required value="<?= e($campaign['subject']??'') ?>" placeholder="Variabile: {name}, {company}, {cui}"><div class="form-hint">Variabile: {name}, {company}, {cui}, {email}</div></div>
<div class="form-group"><label>Conținut email (HTML)</label>
<div style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
<div style="padding:8px 12px;background:var(--bg-subtle);border-bottom:1px solid var(--border);display:flex;gap:4px;flex-wrap:wrap;"><button type="button" class="btn btn-sm btn-outline" onclick="execCmd('bold')"><b>B</b></button><button type="button" class="btn btn-sm btn-outline" onclick="execCmd('italic')"><i>I</i></button><button type="button" class="btn btn-sm btn-outline" onclick="execCmd('underline')"><u>U</u></button><button type="button" class="btn btn-sm btn-outline" onclick="execCmd('insertUnorderedList')">• List</button>
<select onchange="execCmdVal('foreColor',this.value);this.selectedIndex=0;" style="padding:4px;border:1px solid var(--border);border-radius:4px;"><option value="">Culoare</option><option value="#000000">Negru</option><option value="#1d4ed8">Albastru</option><option value="#dc2626">Roșu</option><option value="#16a34a">Verde</option></select></div>
<div id="emailEditor" contenteditable="true" style="min-height:250px;padding:16px;font-family:Arial,sans-serif;font-size:14px;line-height:1.6;outline:none;"><?= $campaign['body_html'] ?? '<p>Bună ziua {name},</p><p></p><p>Cu stimă,</p>' ?></div></div>
<textarea name="body_html" id="bodyHtmlField" style="display:none;"></textarea></div>
<div style="display:flex;gap:8px;justify-content:flex-end;"><a href="<?= Router::url('campaigns') ?>" class="btn btn-outline">Anulează</a><button type="submit" class="btn btn-primary" onclick="document.getElementById('bodyHtmlField').value=document.getElementById('emailEditor').innerHTML;">Salvează</button></div>
</form></div>

<?php elseif ($action === 'view' && $campaignId):
    $campaign = DB::fetchOne("SELECT * FROM email_campaigns WHERE id = ? AND profile_id = ?", [$campaignId, $profileId]);
    if (!$campaign) { flash('error', 'Negăsită.'); Router::redirect('campaigns'); }
    $recipients = DB::fetchAll("SELECT * FROM campaign_recipients WHERE campaign_id = ? ORDER BY status, email", [$campaignId]);
    $attachments = json_decode($campaign['attachment_paths']??'[]', true)?:[];
    $openRate = $campaign['total_sent']>0 ? round(($campaign['total_opened']/$campaign['total_sent'])*100,1) : 0;
    $remaining = $campaign['total_recipients'] - $campaign['total_sent'] - $campaign['total_failed'];
    $pageTitle = $campaign['name'];
?>
<div class="flex-between mb-2"><a href="<?= Router::url('campaigns') ?>" class="text-muted" style="font-size:13px;">← Înapoi</a>
<div style="display:flex;gap:8px;">
<?php if (in_array($campaign['status'],['draft','paused'])): ?><form method="POST" style="display:inline;"><?= csrf() ?><input type="hidden" name="_action" value="start_campaign"><input type="hidden" name="campaign_id" value="<?= $campaignId ?>"><button type="submit" class="btn btn-success btn-sm" <?= count($recipients)===0?'disabled':'' ?>>▶ Pornește</button></form><?php endif; ?>
<?php if ($campaign['status']==='active'): ?><form method="POST" style="display:inline;"><?= csrf() ?><input type="hidden" name="_action" value="pause_campaign"><input type="hidden" name="campaign_id" value="<?= $campaignId ?>"><button type="submit" class="btn btn-warning btn-sm">⏸ Pauză</button></form><?php endif; ?>
<a href="<?= Router::url("campaigns/{$campaignId}/edit") ?>" class="btn btn-outline btn-sm">Editează</a>
<form method="POST" style="display:inline;" onsubmit="return confirm('Sigur?')"><?= csrf() ?><input type="hidden" name="_action" value="delete_campaign"><input type="hidden" name="campaign_id" value="<?= $campaignId ?>"><button type="submit" class="btn btn-danger btn-sm">Șterge</button></form></div></div>

<div class="stats-grid">
<div class="stat-card"><div class="stat-label">Destinatari</div><div class="stat-value"><?= $campaign['total_recipients'] ?></div></div>
<div class="stat-card"><div class="stat-label">Trimise</div><div class="stat-value"><?= $campaign['total_sent'] ?></div><div class="stat-sub">Rămase: <?= $remaining ?></div></div>
<div class="stat-card"><div class="stat-label">Deschise</div><div class="stat-value text-success"><?= $campaign['total_opened'] ?></div><div class="stat-sub">Rată: <?= $openRate ?>%</div></div>
<div class="stat-card"><div class="stat-label">Erori</div><div class="stat-value text-danger"><?= $campaign['total_failed'] ?></div></div>
<div class="stat-card"><div class="stat-label">Status</div><div class="stat-value" style="font-size:18px;"><?= statusBadge($campaign['status']) ?></div></div></div>

<div class="grid-2">
<div class="card"><div class="card-header"><h2>Import CSV</h2></div><form method="POST" enctype="multipart/form-data" class="card-body"><?= csrf() ?><input type="hidden" name="_action" value="upload_csv"><input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
<div class="form-group"><label>CSV *</label><input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required><div class="form-hint">Coloane: email (obligatoriu), name, company, cui</div></div>
<button type="submit" class="btn btn-primary btn-sm">Importă</button></form></div>
<div class="card"><div class="card-header"><h2>Atașamente (<?= count($attachments) ?>)</h2></div><div class="card-body">
<?php foreach ($attachments as $att): ?><div style="padding:4px 0;font-size:13px;">📎 <?= e($att['name']??$att['path']) ?></div><?php endforeach; ?>
<form method="POST" enctype="multipart/form-data" style="margin-top:12px;"><?= csrf() ?><input type="hidden" name="_action" value="upload_attachment"><input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
<div style="display:flex;gap:8px;"><input type="file" name="attachment" class="form-control" required><button type="submit" class="btn btn-outline btn-sm">Adaugă</button></div></form></div></div></div>

<div class="card mt-2 mb-2"><div class="card-header"><h2>Previzualizare</h2></div><div class="card-body"><div style="font-size:13px;margin-bottom:8px;"><strong>Subiect:</strong> <?= e($campaign['subject']) ?></div><div style="border:1px solid var(--border);border-radius:var(--radius);padding:16px;background:#fff;font-size:14px;line-height:1.6;"><?= $campaign['body_html'] ?></div></div></div>

<div class="card"><div class="card-header"><h2>Destinatari (<?= count($recipients) ?>)</h2></div>
<?php if (empty($recipients)): ?><div class="empty-state"><p>Încarcă CSV.</p></div>
<?php else: ?><div class="table-wrap" style="max-height:400px;overflow-y:auto;"><table><thead><tr><th>Email</th><th>Nume</th><th>Companie</th><th>Status</th><th>Trimis</th><th>Deschis</th></tr></thead><tbody>
<?php foreach ($recipients as $r): ?><tr><td><?= e($r['email']) ?></td><td><?= e($r['name']?:'-') ?></td><td><?= e($r['company']?:'-') ?></td><td><?= statusBadge($r['status']) ?></td><td class="text-muted"><?= $r['sent_at'] ? formatDate($r['sent_at'],'d.m.Y H:i') : '-' ?></td><td><?= $r['opened_at'] ? '✅ '.formatDate($r['opened_at'],'d.m.Y H:i') : '-' ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<?php endif; ?>

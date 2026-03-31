<?php
$profileId = Auth::profileId();
$pageTitle = 'Email-uri trimise';
$search = trim($_GET['q'] ?? '');
$where = "profile_id = ?"; $params = [$profileId];
if ($search) { $where .= " AND (to_email LIKE ? OR subject LIKE ? OR to_name LIKE ?)"; $s = "%$search%"; $params = array_merge($params, [$s, $s, $s]); }
$emails = DB::fetchAll("SELECT * FROM email_log WHERE $where ORDER BY created_at DESC LIMIT 200", $params);
$totalSent = DB::fetchOne("SELECT COUNT(*) as c FROM email_log WHERE profile_id = ? AND status = 'sent'", [$profileId])['c'] ?? 0;
$totalOpened = DB::fetchOne("SELECT COUNT(*) as c FROM email_log WHERE profile_id = ? AND opened = 1", [$profileId])['c'] ?? 0;
$totalFailed = DB::fetchOne("SELECT COUNT(*) as c FROM email_log WHERE profile_id = ? AND status = 'failed'", [$profileId])['c'] ?? 0;
?>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Total trimise</div><div class="stat-value"><?= $totalSent ?></div></div>
    <div class="stat-card"><div class="stat-label">Deschise</div><div class="stat-value text-success"><?= $totalOpened ?></div><div class="stat-sub"><?= $totalSent > 0 ? round(($totalOpened/$totalSent)*100,1) : 0 ?>% rată deschidere</div></div>
    <div class="stat-card"><div class="stat-label">Erori</div><div class="stat-value text-danger"><?= $totalFailed ?></div></div>
</div>
<div class="flex-between mb-2">
    <form method="GET" style="display:flex;gap:8px;">
        <input type="text" name="q" class="form-control" style="width:300px" placeholder="Caută după email sau subiect..." value="<?= e($search) ?>">
        <button type="submit" class="btn btn-outline">Caută</button>
    </form>
</div>
<div class="card">
    <?php if (empty($emails)): ?>
        <div class="empty-state"><div class="empty-icon">📧</div><h3>Niciun email trimis</h3></div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Către</th><th>Subiect</th><th>Status</th><th>Deschis</th><th>Campanie</th><th>Trimis la</th></tr></thead>
            <tbody>
            <?php foreach ($emails as $em): ?>
                <tr>
                    <td><strong><?= e($em['to_email']) ?></strong><?php if ($em['to_name']): ?><br><span class="text-muted"><?= e($em['to_name']) ?></span><?php endif; ?></td>
                    <td><?= e(mb_substr($em['subject'] ?: '(fără subiect)', 0, 50)) ?></td>
                    <td><?= statusBadge($em['status']) ?></td>
                    <td><?= $em['opened'] ? '✅ ' . formatDate($em['opened_at'], 'd.m H:i') : '—' ?></td>
                    <td class="text-muted"><?= $em['campaign_id'] ? '#'.$em['campaign_id'] : 'Direct' ?></td>
                    <td class="text-muted"><?= formatDate($em['sent_at'] ?: $em['created_at'], 'd.m.Y H:i') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>

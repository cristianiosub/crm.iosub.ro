<?php
$pid = Auth::profileId();
$totalClients = DB::count('clients', 'profile_id = ?', [$pid]);
$totalProjects = DB::count('projects', 'profile_id = ?', [$pid]);
$totalOffers = DB::count('offers', 'profile_id = ?', [$pid]);
$pipelineValue = (float)(DB::fetchColumn("SELECT COALESCE(SUM(estimated_value),0) FROM clients WHERE profile_id = ? AND pipeline_status IN ('lead','contacted','offer_sent','negotiation')", [$pid]) ?? 0);
$wonValue = (float)(DB::fetchColumn("SELECT COALESCE(SUM(estimated_value),0) FROM clients WHERE profile_id = ? AND pipeline_status = 'won'", [$pid]) ?? 0);
$emailsSent = DB::count('email_log', 'profile_id = ? AND status = ?', [$pid, 'sent']);
$recentClients = DB::fetchAll("SELECT * FROM clients WHERE profile_id = ? ORDER BY created_at DESC LIMIT 5", [$pid]);
$recentOffers = DB::fetchAll("SELECT o.*, c.company_name FROM offers o JOIN clients c ON o.client_id = c.id WHERE o.profile_id = ? ORDER BY o.created_at DESC LIMIT 5", [$pid]);
$pageTitle = 'Dashboard';
?>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Clienți</div><div class="stat-value"><?= $totalClients ?></div><div class="stat-sub">Total activi</div></div>
    <div class="stat-card"><div class="stat-label">Proiecte</div><div class="stat-value"><?= $totalProjects ?></div></div>
    <div class="stat-card"><div class="stat-label">Oferte</div><div class="stat-value"><?= $totalOffers ?></div></div>
    <div class="stat-card"><div class="stat-label">Pipeline</div><div class="stat-value"><?= formatMoney($pipelineValue) ?></div><div class="stat-sub">Valoare estimată</div></div>
    <div class="stat-card"><div class="stat-label">Câștigat</div><div class="stat-value" style="color:var(--green)"><?= formatMoney($wonValue) ?></div></div>
    <div class="stat-card"><div class="stat-label">Email-uri</div><div class="stat-value"><?= $emailsSent ?></div><div class="stat-sub">Total trimise</div></div>
</div>
<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Clienți recenți</h2><a href="<?= Router::url('clients') ?>" class="btn btn-sm btn-outline">Toți →</a></div>
        <?php if (empty($recentClients)): ?>
            <div class="empty-state"><div class="empty-icon">👥</div><h3>Niciun client încă</h3><p><a href="<?= Router::url('clients/new') ?>" class="btn btn-primary btn-sm mt-1">+ Client nou</a></p></div>
        <?php else: ?>
            <div class="table-wrap"><table><thead><tr><th>Companie</th><th>CUI</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($recentClients as $c): ?>
                <tr onclick="location.href='<?= Router::url("clients/{$c['id']}") ?>'"><td><strong><?= e($c['company_name']) ?></strong></td><td class="text-muted"><?= e($c['cui'] ?: '—') ?></td><td><?= statusBadge($c['pipeline_status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header"><h2>Oferte recente</h2><a href="<?= Router::url('offers') ?>" class="btn btn-sm btn-outline">Toate →</a></div>
        <?php if (empty($recentOffers)): ?>
            <div class="empty-state"><div class="empty-icon">📄</div><h3>Nicio ofertă</h3></div>
        <?php else: ?>
            <div class="table-wrap"><table><thead><tr><th>Nr.</th><th>Client</th><th>Total</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($recentOffers as $o): ?>
                <tr onclick="location.href='<?= Router::url("offers/{$o['id']}") ?>'"><td><strong><?= e($o['offer_number'] ?: '#'.$o['id']) ?></strong></td><td><?= e($o['company_name']) ?></td><td><?= formatMoney($o['total']) ?></td><td><?= statusBadge($o['status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</div>

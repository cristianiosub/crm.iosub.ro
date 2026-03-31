<?php
$profileId = Auth::profileId();
$profile = Auth::getProfile();
$pageTitle = 'AI Assistant';

// Get existing clients for context
$clients = DB::fetchAll("SELECT id, company_name FROM clients WHERE profile_id = ? ORDER BY company_name", [$profileId]);
$projects = DB::fetchAll("SELECT p.id, p.name, c.company_name FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.profile_id = ? ORDER BY p.created_at DESC", [$profileId]);

$vatRate = $profile['default_vat_rate'] ?? 0;
$profileName = $profile['name'] ?? 'CyberCRM';
?>

<div style="max-width:900px;">
    <div class="card mb-2">
        <div class="card-header">
            <h2>AI Assistant — Creeaza clienti, proiecte si oferte din conversatie</h2>
        </div>
        <div class="card-body">
            <p class="text-muted" style="font-size:13px;margin-bottom:16px;">
                Descrie-i lui Claude despre ce e proiectul si el va genera automat: client, contact, proiect, servicii, oferta.
                Apoi mergi la Oferte si dai Export PDF. Profil activ: <strong><?= e($profileName) ?></strong> (TVA: <?= $vatRate ?>%).
            </p>

            <div class="form-group">
                <label>Context rapid (optional)</label>
                <div class="form-row">
                    <div class="form-group">
                        <select id="ai-client" class="form-control">
                            <option value="">Client nou (se creeaza automat)</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select id="ai-project" class="form-control">
                            <option value="">Proiect nou (se creeaza automat)</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['company_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Spune-mi despre proiect</label>
                <textarea id="ai-prompt" class="form-control" rows="8" placeholder="Exemplu: Am un client nou, se numeste ABC SRL, CUI 12345678, din Bucuresti. Contact: Ion Popescu, director IT, ion@abc.ro, +40 721 000 000. Vreau sa le fac un audit de securitate cibernetica (15.000 RON) si un pentest (20.000 RON). Oferta trimisa azi."></textarea>
                <div class="form-hint">Descrie: numele firmei, CUI, adresa, contact, serviciile dorite cu preturi, statusul (draft/sent). Cu cat dai mai multe detalii, cu atat oferta e mai completa.</div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn btn-primary btn-lg" id="ai-generate" onclick="generateFromAI()">
                    Genereaza tot
                </button>
            </div>
        </div>
    </div>

    <!-- Rezultat -->
    <div id="ai-result" style="display:none;">
        <div class="card mb-2">
            <div class="card-header"><h2>Rezultat</h2></div>
            <div class="card-body" id="ai-result-content" style="font-size:13px;"></div>
        </div>
    </div>

    <!-- Processing indicator -->
    <div id="ai-loading" style="display:none;" class="card">
        <div class="card-body text-center" style="padding:40px;">
            <div style="font-size:24px;margin-bottom:12px;">⏳</div>
            <p>Claude analizeaza si genereaza...</p>
            <p class="text-muted" style="font-size:12px;">Creez client, proiect, servicii si oferta...</p>
        </div>
    </div>
</div>

<script>
async function generateFromAI() {
    const prompt = document.getElementById('ai-prompt').value.trim();
    if (!prompt) { notify('Descrie proiectul mai intai!', 'warning'); return; }

    const clientId = document.getElementById('ai-client').value;
    const projectId = document.getElementById('ai-project').value;
    const btn = document.getElementById('ai-generate');

    btn.disabled = true; btn.textContent = 'Se genereaza...';
    document.getElementById('ai-loading').style.display = '';
    document.getElementById('ai-result').style.display = 'none';

    try {
        const response = await fetch(window.APP_URL + '/api/ai-generate', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: '_token=' + encodeURIComponent(getCSRFToken()) +
                  '&prompt=' + encodeURIComponent(prompt) +
                  '&client_id=' + encodeURIComponent(clientId) +
                  '&project_id=' + encodeURIComponent(projectId),
            credentials: 'same-origin'
        });

        const data = await response.json();
        document.getElementById('ai-loading').style.display = 'none';

        if (data.success) {
            let html = '<div class="flash flash-success" style="position:static;">Totul a fost creat cu succes!</div>';
            if (data.client) html += '<p><strong>Client:</strong> <a href="' + window.APP_URL + '/clients/' + data.client.id + '">' + data.client.name + '</a></p>';
            if (data.project) html += '<p><strong>Proiect:</strong> <a href="' + window.APP_URL + '/projects/' + data.project.id + '">' + data.project.name + '</a></p>';
            if (data.offer) html += '<p><strong>Oferta:</strong> <a href="' + window.APP_URL + '/offers/' + data.offer.id + '">' + data.offer.number + '</a> — <strong>' + data.offer.total + '</strong></p>';
            if (data.offer) html += '<p style="margin-top:12px;"><a href="' + window.APP_URL + '/api/export-pdf/' + data.offer.id + '" target="_blank" class="btn btn-success">Export PDF</a> <a href="' + window.APP_URL + '/offers/' + data.offer.id + '" class="btn btn-primary">Vezi oferta</a></p>';
            document.getElementById('ai-result-content').innerHTML = html;
            document.getElementById('ai-result').style.display = '';
            notify('Client, proiect si oferta create!', 'success');
        } else {
            notify(data.error || 'Eroare la generare', 'error');
            document.getElementById('ai-result-content').innerHTML = '<div class="flash flash-error" style="position:static;">' + (data.error || 'Eroare') + '</div>' + (data.debug ? '<pre style="font-size:11px;margin-top:8px;">' + data.debug + '</pre>' : '');
            document.getElementById('ai-result').style.display = '';
        }
    } catch (err) {
        document.getElementById('ai-loading').style.display = 'none';
        notify('Eroare de retea: ' + err.message, 'error');
    }

    btn.disabled = false; btn.textContent = 'Genereaza tot';
}
</script>

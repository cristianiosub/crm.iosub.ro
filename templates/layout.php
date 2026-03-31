<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= Security::generateCSRF() ?>">
    <meta name="csrf-param" content="<?= CSRF_TOKEN_NAME ?>">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <script>window.APP_URL = '<?= APP_URL ?>';</script>
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <a href="<?= Router::url('dashboard') ?>" class="sidebar-brand" style="text-decoration:none;">
            <div class="brand-icon">
                <svg viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <defs>
                    <linearGradient id="sg1" x1="0" y1="0" x2="38" y2="38" gradientUnits="userSpaceOnUse">
                      <stop offset="0%" stop-color="#818cf8"/>
                      <stop offset="100%" stop-color="#6366f1"/>
                    </linearGradient>
                  </defs>
                  <!-- Folder / CRM icon -->
                  <rect x="3" y="10" width="32" height="22" rx="3" fill="url(#sg1)" opacity="0.85"/>
                  <path d="M3 13h32" stroke="rgba(255,255,255,0.2)" stroke-width="1"/>
                  <rect x="3" y="10" width="14" height="5" rx="2" fill="#a5b4fc" opacity="0.6"/>
                  <!-- Lines inside folder -->
                  <rect x="8" y="18" width="10" height="2" rx="1" fill="white" opacity="0.5"/>
                  <rect x="8" y="22" width="16" height="2" rx="1" fill="white" opacity="0.35"/>
                  <rect x="8" y="26" width="13" height="2" rx="1" fill="white" opacity="0.25"/>
                  <!-- Person dot -->
                  <circle cx="28" cy="22" r="5" fill="#6366f1"/>
                  <circle cx="28" cy="20.5" r="2" fill="white" opacity="0.9"/>
                  <path d="M23.5 26.5c.5-2 2.2-3 4.5-3s4 1 4.5 3" stroke="white" stroke-width="1.4" stroke-linecap="round" opacity="0.9" fill="none"/>
                </svg>
            </div>
            <div style="display:flex;flex-direction:column;gap:1px;">
                <span class="brand-name">CyberCRM</span>
                <span style="font-size:9px;color:var(--sidebar-text);letter-spacing:.04em;">iosub.ro</span>
            </div>
            <span class="brand-version">v<?= APP_VERSION ?></span>
        </a>
        <div class="profile-switch">
            <?php $activeProfile = Auth::getProfile(); ?>
            <div style="padding:8px 12px;font-size:12px;font-weight:600;color:var(--text-muted);letter-spacing:.04em;">
                <?= e($activeProfile['name'] ?? APP_NAME) ?>
            </div>
        </div>
        <nav class="sidebar-nav">
            <!-- AI Assistant -->
            <a href="<?= Router::url('ai-assistant') ?>" class="nav-item <?= $currentPage === 'ai-assistant' ? 'active' : '' ?>" style="<?= $currentPage !== 'ai-assistant' ? 'background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(168,85,247,0.08));border:1px solid rgba(99,102,241,0.15);' : '' ?>margin:6px 0 10px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v4m0 12v4m-7.07-2.93l2.83-2.83m8.48-8.48l2.83-2.83M2 12h4m12 0h4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83"/></svg>
                AI Assistant
            </a>

            <div class="nav-label">Principal</div>
            <?php
            $navItems = [
                ['dashboard', 'Dashboard', '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
                ['clients', 'Clienti', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
                ['projects', 'Proiecte', '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>'],
            ];
            foreach ($navItems as [$href, $label, $icon]):
            ?>
            <a href="<?= Router::url($href) ?>" class="nav-item <?= $currentPage === $href ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
                <?= $label ?>
            </a>
            <?php endforeach; ?>

            <div class="nav-label">Documente</div>
            <?php
            $docItems = [
                ['offers', 'Oferte', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
                ['documents', 'NDA & Contracte', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/>'],
            ];
            foreach ($docItems as [$href, $label, $icon]):
            ?>
            <a href="<?= Router::url($href) ?>" class="nav-item <?= $currentPage === $href ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
                <?= $label ?>
            </a>
            <?php endforeach; ?>

            <div class="nav-label">Comunicare</div>
            <?php
            $commItems = [
                ['email-log', 'Email-uri', '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'],
                ['campaigns', 'Campanii', '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>'],
                ['correspondence', 'Corespondenta', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
            ];
            foreach ($commItems as [$href, $label, $icon]):
            ?>
            <a href="<?= Router::url($href) ?>" class="nav-item <?= $currentPage === $href ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
                <?= $label ?>
            </a>
            <?php endforeach; ?>

            <div class="nav-label">Setari</div>
            <a href="<?= Router::url('profiles') ?>" class="nav-item <?= $currentPage === 'profiles' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Companie
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-footer-links">
                <a href="<?= Router::url('profiles', ['section' => 'password']) ?>" title="Schimba parola">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Parolă
                </a>
                <a href="<?= Router::url('login', ['action' => 'logout']) ?>" title="Ieșire" style="color:#f87171;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Ieșire
                </a>
            </div>
            <div class="sidebar-user">
                <div class="user-avatar"><?= strtoupper(mb_substr(Auth::userName(), 0, 1)) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= e(Auth::userName()) ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </div>
    </aside>
    <main class="main-content">
        <div class="top-bar">
            <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
            <div class="top-bar-actions" id="top-actions"></div>
        </div>
        <div class="page-content">
            <?php foreach (getFlash() as $msg): ?>
                <div class="flash flash-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
            <?php endforeach; ?>
            <?php require $pageFile; ?>
        </div>
    </main>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>

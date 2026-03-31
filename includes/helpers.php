<?php
/**
 * CyberCRM — Helper Functions
 */
function e(string $str): string { return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

function flash(string $type, string $message): void { $_SESSION['flash'][] = ['type' => $type, 'message' => $message]; }

function getFlash(): array { $m = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $m; }

function formatMoney(float $amount, string $currency = 'RON'): string {
    return number_format($amount, 2, ',', '.') . ' ' . $currency;
}

function formatDate(?string $date, string $fmt = 'd.m.Y'): string {
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : '—';
}

function generateOfferNumber(int $profileId): string {
    $year = date('Y');
    $count = DB::count('offers', 'profile_id = ? AND YEAR(created_at) = ?', [$profileId, $year]);
    $profile = DB::fetchOne("SELECT name FROM profiles WHERE id = ?", [$profileId]);
    return sprintf("%s-%s-%03d", strtoupper(substr($profile['name'] ?? 'CRM', 0, 3)), $year, $count + 1);
}

function generateDocNumber(string $type, int $profileId): string {
    $year = date('Y');
    $count = DB::count('documents', 'profile_id = ? AND doc_type = ? AND YEAR(created_at) = ?', [$profileId, $type, $year]);
    $profile = DB::fetchOne("SELECT name FROM profiles WHERE id = ?", [$profileId]);
    return sprintf("%s-%s-%s-%03d", strtoupper($type), strtoupper(substr($profile['name'] ?? 'CRM', 0, 3)), $year, $count + 1);
}

function uploadFile(array $file, string $subdir = ''): ?string {
    $error = Security::validateUpload($file);
    if ($error) return null;
    $dir = UPLOAD_PATH . ($subdir ? '/' . $subdir : '');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = Security::secureFilename($file['name']);
    $path = $dir . '/' . $name;
    if (move_uploaded_file($file['tmp_name'], $path)) return ($subdir ? $subdir . '/' : '') . $name;
    return null;
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function lookupCUI(string $cui): ?array {
    $cui = preg_replace('/[^0-9]/', '', $cui);
    if (strlen($cui) < 2 || strlen($cui) > 15) return null;
    $ch = curl_init('https://webservicesp.anaf.ro/PlatitorTvaRest/api/v8/ws/tva');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode([['cui' => (int)$cui, 'data' => date('Y-m-d')]]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !$resp) return null;
    $data = json_decode($resp, true);
    if (!isset($data['found'][0])) return null;
    $c = $data['found'][0];
    return ['company_name' => $c['denumire'] ?? '', 'cui' => $cui, 'reg_com' => $c['numar_registru_comert'] ?? '', 'address' => $c['adresa_sediu_social'] ?? ($c['adresa'] ?? ''), 'phone' => $c['telefon'] ?? ''];
}

function statusBadge(string $status): string {
    $map = ['draft'=>'neutral','lead'=>'neutral','pending'=>'purple','queued'=>'purple','active'=>'blue','contacted'=>'blue','sent'=>'blue','new'=>'blue',
        'offer_sent'=>'amber','negotiation'=>'amber','on_hold'=>'amber','paused'=>'amber','won'=>'green','completed'=>'green','accepted'=>'green',
        'signed'=>'green','replied'=>'green','opened'=>'green','lost'=>'red','cancelled'=>'red','rejected'=>'red','failed'=>'red',
        'expired'=>'gray','dormant'=>'gray','no_response'=>'gray','low'=>'green','medium'=>'blue','high'=>'amber','critical'=>'red'];
    $color = $map[$status] ?? 'neutral';
    return "<span class=\"badge badge-{$color}\">" . ucfirst(str_replace('_', ' ', $status)) . "</span>";
}

function getTrackingPixelUrl(int $emailLogId): string { return APP_URL . '/api/track/' . $emailLogId; }
function csrf(): string { return Security::csrfField(); }

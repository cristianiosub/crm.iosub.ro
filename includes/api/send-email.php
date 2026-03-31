<?php
if (!Auth::check()) jsonResponse(['error' => 'Unauthorized'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
Security::requireCSRF();
$profileId = Auth::profileId();
$profile = Auth::getProfile();
$to = Security::sanitizeEmail($_POST['to'] ?? '');
$subject = Security::sanitize($_POST['subject'] ?? '');
$bodyHtml = Security::sanitizeHTML($_POST['body_html'] ?? '');
$clientId = (int)($_POST['client_id'] ?? 0) ?: null;
if (!$to || !$subject) jsonResponse(['error' => 'Email și subiect obligatorii'], 400);
$logId = DB::insert('email_log', [
    'profile_id' => $profileId, 'client_id' => $clientId,
    'to_email' => $to, 'from_email' => $profile['smtp_from_email'] ?: $profile['email'],
    'from_name' => $profile['smtp_from_name'] ?: $profile['name'],
    'subject' => $subject, 'body_html' => $bodyHtml, 'status' => 'queued',
]);
$bodyHtml .= '<img src="' . getTrackingPixelUrl($logId) . '" width="1" height="1" alt="" style="display:none" />';
require_once ROOT_PATH . '/cron/send_campaigns.php';
$success = sendEmail($profile, $to, '', $subject, $bodyHtml);
DB::update('email_log', ['status' => $success ? 'sent' : 'failed', 'sent_at' => $success ? date('Y-m-d H:i:s') : null], 'id = ?', [$logId]);
jsonResponse($success ? ['success' => true] : ['error' => 'Trimitere eșuată'], $success ? 200 : 500);

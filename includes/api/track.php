<?php
$trackId = $id ?? 0;
if ($trackId > 0) {
    DB::query("UPDATE email_log SET opened = 1, opened_at = COALESCE(opened_at, NOW()) WHERE id = ? AND opened = 0", [$trackId]);
    $email = DB::fetchOne("SELECT campaign_id, to_email FROM email_log WHERE id = ?", [$trackId]);
    if ($email && $email['campaign_id']) {
        DB::query("UPDATE campaign_recipients SET status = 'opened', opened_at = COALESCE(opened_at, NOW()) WHERE campaign_id = ? AND email = ? AND status = 'sent'", [$email['campaign_id'], $email['to_email']]);
    }
}
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit;

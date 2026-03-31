<?php
/**
 * CRON JOB: Send campaign emails
 * cPanel: */15 * * * * php /home/i0sub/crm-app/cron/send_campaigns.php
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

function sendEmail(array $profile, string $to, string $toName, string $subject, string $bodyHtml, array $attachmentPaths = [], ?int $trackingId = null): bool {
    if ($trackingId) $bodyHtml .= '<img src="' . getTrackingPixelUrl($trackingId) . '" width="1" height="1" alt="" style="display:none" />';
    if (!empty($profile['smtp_host']) && !empty($profile['smtp_user']) && !empty($profile['smtp_pass'])) return sendViaSMTP($profile, $to, $toName, $subject, $bodyHtml, $attachmentPaths);
    $fromName = $profile['smtp_from_name'] ?: $profile['name']; $fromEmail = $profile['smtp_from_email'] ?: $profile['email'];
    $headers = "From: $fromName <$fromEmail>\r\nReply-To: $fromEmail\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $bodyHtml, $headers);
}

function sendViaSMTP(array $profile, string $to, string $toName, string $subject, string $bodyHtml, array $attachments = []): bool {
    $host=$profile['smtp_host']; $port=$profile['smtp_port']?:587; $user=$profile['smtp_user']; $pass=$profile['smtp_pass'];
    $fromName=$profile['smtp_from_name']?:$profile['name']; $fromEmail=$profile['smtp_from_email']?:$profile['email'];
    $boundary = md5(uniqid(time()));
    $message = "MIME-Version: 1.0\r\nFrom: $fromName <$fromEmail>\r\nTo: $toName <$to>\r\nSubject: $subject\r\n";
    if (!empty($attachments)) {
        $message .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($bodyHtml)) . "\r\n";
        foreach ($attachments as $att) { $fp=UPLOAD_PATH.'/'.($att['path']??$att); if(!file_exists($fp))continue; $fn=$att['name']??basename($fp); $message.="--$boundary\r\nContent-Type: application/octet-stream; name=\"$fn\"\r\nContent-Disposition: attachment; filename=\"$fn\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode(file_get_contents($fp)))."\r\n"; }
        $message .= "--$boundary--\r\n";
    } else { $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $bodyHtml; }
    $socket = @fsockopen(($port==465?'ssl://':'').$host, $port, $errno, $errstr, 10); if (!$socket) return false;
    fgets($socket, 515);
    fwrite($socket, "EHLO ".gethostname()."\r\n"); while($l=fgets($socket,515)){if(substr($l,3,1)==' ')break;}
    if ($port==587) { fwrite($socket,"STARTTLS\r\n"); while($l=fgets($socket,515)){if(substr($l,3,1)==' ')break;} stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT); fwrite($socket,"EHLO ".gethostname()."\r\n"); while($l=fgets($socket,515)){if(substr($l,3,1)==' ')break;} }
    fwrite($socket,"AUTH LOGIN\r\n"); fgets($socket,515); fwrite($socket,base64_encode($user)."\r\n"); fgets($socket,515); fwrite($socket,base64_encode($pass)."\r\n");
    $auth=fgets($socket,515); if(substr($auth,0,3)!=='235'){fclose($socket);return false;}
    fwrite($socket,"MAIL FROM:<$fromEmail>\r\n"); fgets($socket,515); fwrite($socket,"RCPT TO:<$to>\r\n"); fgets($socket,515);
    fwrite($socket,"DATA\r\n"); fgets($socket,515); fwrite($socket,$message."\r\n.\r\n"); $dr=fgets($socket,515); fwrite($socket,"QUIT\r\n"); fclose($socket);
    return substr($dr,0,3)==='250';
}

$activeCampaigns = DB::fetchAll("SELECT * FROM email_campaigns WHERE status = 'active'");
foreach ($activeCampaigns as $campaign) {
    $profile = DB::fetchOne("SELECT * FROM profiles WHERE id = ?", [$campaign['profile_id']]); if (!$profile) continue;
    $sentToday = DB::fetchOne("SELECT COUNT(*) as c FROM campaign_recipients WHERE campaign_id = ? AND status = 'sent' AND DATE(sent_at) = CURDATE()", [$campaign['id']])['c'] ?? 0;
    $remaining = ($campaign['daily_limit'] ?: DAILY_EMAIL_LIMIT) - $sentToday; if ($remaining <= 0) continue;
    $recipients = DB::fetchAll("SELECT * FROM campaign_recipients WHERE campaign_id = ? AND status = 'pending' ORDER BY id ASC LIMIT ?", [$campaign['id'], min($remaining, EMAIL_BATCH_SIZE)]);
    if (empty($recipients)) { DB::update('email_campaigns', ['status'=>'completed','completed_at'=>date('Y-m-d H:i:s')], 'id = ?', [$campaign['id']]); continue; }
    $attachments = json_decode($campaign['attachment_paths']??'[]', true)?:[];
    foreach ($recipients as $r) {
        $vars = ['{name}'=>$r['name']?:'', '{email}'=>$r['email'], '{company}'=>$r['company']?:'', '{cui}'=>$r['cui']?:''];
        $custom = json_decode($r['custom_fields']??'{}', true)?:[]; foreach ($custom as $k=>$v) $vars['{'.$k.'}'] = $v;
        $subj = str_replace(array_keys($vars), array_values($vars), $campaign['subject']);
        $body = str_replace(array_keys($vars), array_values($vars), $campaign['body_html']);
        $logId = DB::insert('email_log', ['profile_id'=>$campaign['profile_id'], 'campaign_id'=>$campaign['id'], 'to_email'=>$r['email'], 'to_name'=>$r['name'], 'from_email'=>$profile['smtp_from_email']?:$profile['email'], 'from_name'=>$profile['smtp_from_name']?:$profile['name'], 'subject'=>$subj, 'body_html'=>$body, 'status'=>'queued']);
        $ok = sendEmail($profile, $r['email'], $r['name']?:'', $subj, $body, $attachments, $logId);
        if ($ok) { DB::update('campaign_recipients', ['status'=>'sent','sent_at'=>date('Y-m-d H:i:s')], 'id = ?', [$r['id']]); DB::update('email_log', ['status'=>'sent','sent_at'=>date('Y-m-d H:i:s')], 'id = ?', [$logId]); }
        else { DB::update('campaign_recipients', ['status'=>'failed','error_message'=>'Send failed'], 'id = ?', [$r['id']]); DB::update('email_log', ['status'=>'failed','error_message'=>'SMTP failed'], 'id = ?', [$logId]); }
        usleep(200000);
    }
    $stats = DB::fetchOne("SELECT COUNT(CASE WHEN status='sent' THEN 1 END) as sent, COUNT(CASE WHEN status='opened' THEN 1 END) as opened, COUNT(CASE WHEN status='failed' THEN 1 END) as failed FROM campaign_recipients WHERE campaign_id = ?", [$campaign['id']]);
    DB::update('email_campaigns', ['total_sent'=>$stats['sent']??0, 'total_opened'=>$stats['opened']??0, 'total_failed'=>$stats['failed']??0], 'id = ?', [$campaign['id']]);
}
echo "Cron completed at " . date('Y-m-d H:i:s') . "\n";

<?php
/**
 * Export PDF — Template profesional cu logo per profil + semnatura
 */
if (!Auth::check()) { http_response_code(401); die('Unauthorized'); }
$offerId = $id ?? 0;
$profileId = Auth::profileId();
if (!$offerId) die('Missing offer ID');

$offer = DB::fetchOne(
    "SELECT o.*, c.company_name, c.cui as client_cui, c.address as client_address, c.reg_com as client_reg_com, c.city as client_city, c.county as client_county, c.phone as client_phone, c.email as client_email, c.website as client_website, p.name as project_name
    FROM offers o JOIN clients c ON o.client_id = c.id LEFT JOIN projects p ON o.project_id = p.id
    WHERE o.id = ? AND o.profile_id = ?", [$offerId, $profileId]
);
if (!$offer) die('Not found');
$items = DB::fetchAll("SELECT * FROM offer_items WHERE offer_id = ? ORDER BY sort_order", [$offerId]);
$profile = Auth::getProfile();

$isWHT = stripos($profile['name'], 'White Hat') !== false;
$brandColor = $isWHT ? '#0f2b46' : '#1d4ed8';
$accentColor = $isWHT ? '#1a3a5c' : '#2563eb';
$logoFile = $isWHT ? 'logo-wht.png' : 'logo-cybershield.png';
$logoUrl = APP_URL . '/assets/img/' . $logoFile;
$sigUrl = APP_URL . '/assets/img/semnatura.png';

$secNum = 0;
function nextSec() { global $secNum; return ++$secNum; }

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="ro"><head><meta charset="UTF-8">
<title>Oferta <?= e($offer['offer_number']) ?> — <?= e($profile['name']) ?></title>
<style>
@page{margin:15mm 18mm;size:A4}*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',Arial,sans-serif;color:#1a1a2e;font-size:12.5px;line-height:1.55}
.page{max-width:800px;margin:0 auto;padding:30px 40px}
.hdr{display:flex;justify-content:space-between;align-items:center;padding-bottom:16px;border-bottom:3px solid <?=$brandColor?>;margin-bottom:24px}
.hdr-right{text-align:right;font-size:10.5px;color:#64748b;line-height:1.5}
.ttl{text-align:center;margin-bottom:6px}
.ttl h1{font-size:20px;font-weight:700;color:<?=$brandColor?>}
.ttl .sub{font-size:13px;color:<?=$accentColor?>;font-style:italic;margin-top:2px}
.meta{text-align:center;color:#64748b;font-size:11px;margin-bottom:24px;font-style:italic}
h2{font-size:13px;font-weight:700;color:<?=$brandColor?>;border-bottom:2px solid <?=$brandColor?>;padding-bottom:3px;margin:22px 0 10px}
table.i{width:100%;border-collapse:collapse;margin:8px 0 16px}
table.i td{padding:6px 10px;border:1px solid #e2e8f0;font-size:11.5px}
table.i td:first-child{background:#f8fafc;font-weight:600;width:170px;color:#334155}
table.p{width:100%;border-collapse:collapse;margin:10px 0 16px}
table.p thead th{background:<?=$brandColor?>;color:#fff;padding:8px 10px;font-size:10px;text-transform:uppercase;letter-spacing:.04em}
table.p thead th:last-child{text-align:right}
table.p tbody td{padding:8px 10px;border-bottom:1px solid #e2e8f0;font-size:11.5px;vertical-align:top}
table.p tbody td:first-child{width:36px;text-align:center;color:#64748b;font-weight:600}
table.p tbody td:last-child{text-align:right;font-weight:500;white-space:nowrap}
table.p tbody tr:nth-child(even){background:#f8fafc}
.st{font-weight:600;color:#0f172a}.sd{color:#64748b;font-size:10.5px;margin-top:1px;line-height:1.35}
.tr{background:#f0f9ff!important}.tr td{font-weight:700;font-size:13px;border-top:2px solid <?=$brandColor?>;color:<?=$brandColor?>;padding:10px}
.tv{font-size:10.5px;color:#64748b;font-style:italic;margin:6px 0 16px;padding:8px 10px;background:#f8fafc;border-radius:3px;border-left:3px solid <?=$accentColor?>}
.sc{font-size:12px;line-height:1.65;color:#334155;margin-bottom:14px}
.sc ul{margin:6px 0 6px 18px}.sc li{margin-bottom:3px}.sc p{margin-bottom:6px}
table.tm{width:100%;border-collapse:collapse;margin:8px 0}
table.tm td{padding:6px 10px;border:1px solid #e2e8f0;font-size:11.5px}
table.tm td:first-child{font-weight:600;width:170px;background:#f8fafc}
.sig{margin-top:30px;border-top:2px solid <?=$brandColor?>;padding-top:16px}
.ft{margin-top:30px;padding-top:8px;border-top:1px solid #e2e8f0;text-align:center;color:#94a3b8;font-size:9.5px}
.pb{position:fixed;top:16px;right:16px;background:<?=$accentColor?>;color:#fff;border:none;padding:10px 24px;font-size:13px;border-radius:6px;cursor:pointer;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,.15);font-weight:600}
@media print{.pb{display:none}.page{padding:0}}
</style></head><body>
<button class="pb" onclick="window.print()">&#128424; Salveaza PDF (Ctrl+P)</button>
<div class="page">

<div class="hdr">
    <img src="<?=$logoUrl?>" style="height:50px;" alt="<?=e($profile['name'])?>">
    <div class="hdr-right"><?=e($profile['email']??'')?><br><?=e($profile['phone']??'')?><?php if($profile['website']):?><br><?=e($profile['website'])?><?php endif;?></div>
</div>

<div class="ttl"><h1>OFERTA DE PRET</h1>
<?php if($offer['project_name']):?><div class="sub"><?=e($offer['project_name'])?></div>
<?php else:?><div class="sub">Servicii profesionale de securitate cibernetica</div><?php endif;?></div>
<div class="meta">Destinatar: <?=e($offer['company_name'])?> &nbsp;|&nbsp; Data: <?=formatDate($offer['offer_date'])?> &nbsp;|&nbsp; Nr. <?=e($offer['offer_number'])?></div>

<h2><?=nextSec()?>. Date furnizor</h2>
<table class="i">
<tr><td>Denumire</td><td><?=e($profile['legal_name']?:$profile['name'])?></td></tr>
<?php if($profile['cui']):?><tr><td>CUI</td><td><?=e($profile['cui'])?></td></tr><?php endif;?>
<?php if($profile['reg_com']):?><tr><td>Nr. Reg. Com.</td><td><?=e($profile['reg_com'])?></td></tr><?php endif;?>
<?php if($profile['address']):?><tr><td>Sediu social</td><td><?=e($profile['address'])?><?php if($profile['city']):?>, <?=e($profile['city'])?><?php endif;?><?php if($profile['county']):?>, <?=e($profile['county'])?><?php endif;?></td></tr><?php endif;?>
<?php if($isWHT):?><tr><td>Obiect de activitate</td><td>Securitate cibernetica, audit informatic, securitate ofensiva</td></tr><?php endif;?>
<tr><td>Persoana de contact</td><td><?=e(Auth::userName())?></td></tr>
<tr><td>Telefon</td><td><?=e($profile['phone']??'')?></td></tr>
<tr><td>E-mail</td><td><?=e($profile['email']??'')?></td></tr>
</table>

<h2><?=nextSec()?>. Date beneficiar</h2>
<table class="i">
<tr><td>Denumire</td><td><?=e($offer['company_name'])?></td></tr>
<?php if($offer['client_cui']):?><tr><td>CUI / IDNO</td><td><?=e($offer['client_cui'])?></td></tr><?php endif;?>
<?php if($offer['client_reg_com']):?><tr><td>Nr. Reg. Com.</td><td><?=e($offer['client_reg_com'])?></td></tr><?php endif;?>
<?php if($offer['client_address']):?><tr><td>Adresa</td><td><?=e($offer['client_address'])?><?php if($offer['client_city']):?>, <?=e($offer['client_city'])?><?php endif;?></td></tr><?php endif;?>
<?php if($offer['client_phone']):?><tr><td>Telefon</td><td><?=e($offer['client_phone'])?></td></tr><?php endif;?>
<?php if($offer['client_email']):?><tr><td>E-mail</td><td><?=e($offer['client_email'])?></td></tr><?php endif;?>
</table>

<?php if($offer['intro_text']):?>
<h2><?=nextSec()?>. Obiectul ofertei</h2>
<div class="sc"><?php $lines=explode("\n",$offer['intro_text']);$u=false;foreach($lines as $l){$l=trim($l);if(!$l)continue;if(preg_match('/^[-•*]/',$l)){if(!$u){echo'<ul>';$u=true;}echo'<li>'.e(ltrim($l,'-•* ')).'</li>';}else{if($u){echo'</ul>';$u=false;}echo'<p>'.e($l).'</p>';}}if($u)echo'</ul>';?></div>
<?php endif;?>

<h2><?=nextSec()?>. Oferta de pret</h2>
<table class="p"><thead><tr><th>Nr.</th><th>Serviciu</th><th style="text-align:right">Valoare fara TVA (<?=e($offer['currency'])?>)</th></tr></thead><tbody>
<?php foreach($items as $i=>$it):?><tr><td><?=$i+1?></td><td><div class="st"><?=e($it['title'])?></div><?php if($it['description']):?><div class="sd"><?=e($it['description'])?></div><?php endif;?></td><td style="text-align:right"><?=formatMoney($it['total_price'],$offer['currency'])?></td></tr><?php endforeach;?>
<tr class="tr"><td></td><td style="text-align:right">TOTAL</td><td style="text-align:right"><?=formatMoney($offer['subtotal'],$offer['currency'])?></td></tr>
</tbody></table>
<div class="tv"><?php if($offer['vat_rate']>0):?>Preturile sunt exprimate in <?=e($offer['currency'])?>. TVA aplicat: <?=$offer['vat_rate']?>%, conform legislatiei fiscale in vigoare.<br><strong>Total cu TVA: <?=formatMoney($offer['total'],$offer['currency'])?></strong><?php else:?>Preturile sunt exprimate in <?=e($offer['currency'])?>. Organizatie nonprofit — TVA neaplicabil.<?php endif;?></div>

<?php if($offer['deliverables_text']):?>
<h2><?=nextSec()?>. Livrabile incluse</h2>
<div class="sc"><?php $lines=explode("\n",$offer['deliverables_text']);$u=false;foreach($lines as $l){$l=trim($l);if(!$l)continue;if(preg_match('/^[-•*]/',$l)){if(!$u){echo'<ul>';$u=true;}echo'<li>'.e(ltrim($l,'-•* ')).'</li>';}else{if($u){echo'</ul>';$u=false;}echo'<p>'.e($l).'</p>';}}if($u)echo'</ul>';?></div>
<?php endif;?>

<?php if($offer['methodology_text']):?>
<h2><?=nextSec()?>. Metodologie si standarde</h2>
<div class="sc"><?php $lines=explode("\n",$offer['methodology_text']);$u=false;foreach($lines as $l){$l=trim($l);if(!$l)continue;if(preg_match('/^[-•*]/',$l)){if(!$u){echo'<ul>';$u=true;}echo'<li>'.e(ltrim($l,'-•* ')).'</li>';}else{if($u){echo'</ul>';$u=false;}echo'<p>'.e($l).'</p>';}}if($u)echo'</ul>';?></div>
<?php endif;?>

<?php if($offer['terms_text']):?>
<h2><?=nextSec()?>. Conditii comerciale</h2>
<div class="sc"><?php $t=explode("\n",$offer['terms_text']);$tb=false;foreach($t as $r){$r=trim($r);if(!$r)continue;if(strpos($r,':')!==false&&strlen($r)<150){if(!$tb){echo'<table class="tm">';$tb=true;}list($k,$v)=explode(':',$r,2);echo'<tr><td>'.e(trim($k)).'</td><td>'.e(trim($v)).'</td></tr>';}else{if($tb){echo'</table>';$tb=false;}echo'<p>'.e($r).'</p>';}}if($tb)echo'</table>';?></div>
<?php endif;?>

<div class="sig">
    <p style="color:#64748b;font-size:11px;">Cu stima,</p>
    <img src="<?=$sigUrl?>" style="height:70px;margin:8px 0;" alt="Semnatura">
    <p style="font-weight:700;font-size:13px;"><?=e(Auth::userName())?></p>
    <p style="color:<?=$accentColor?>;font-weight:500;font-size:12px;"><?=e($profile['name'])?></p>
    <p style="color:#64748b;font-size:11px;"><?=e($profile['email']??'')?> | <?=e($profile['phone']??'')?></p>
</div>

<div class="ft"><?=e($profile['name'])?> | <?=e($profile['email']??'')?> | <?=e($profile['phone']??'')?> | Oferta valabila <?php
if($offer['valid_until']){$d=max(0,(int)((strtotime($offer['valid_until'])-strtotime($offer['offer_date']))/86400));echo $d.' de zile';}else echo'30 de zile';?> de la data emiterii</div>

</div></body></html><?php exit;

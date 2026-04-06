<?php
/**
 * CyberCRM Reimport FULL — v1
 * 1. Sterge clienti, proiecte, oferte existente
 * 2. Reimporta tot din arhiva /oferte/
 * URL: https://crm.iosub.ro/reimport.php?run=ReimportFull2026
 * STERGE DUPA RULARE!
 */
if (!isset($_GET['run']) || $_GET['run'] !== 'ReimportFull2026') die('Access denied');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pdo = new PDO('mysql:host=localhost;dbname=i0sub_crm;charset=utf8mb4', 'i0sub_crm', 'i0sub_crm1234', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("SET NAMES utf8mb4; SET sql_mode=''; SET FOREIGN_KEY_CHECKS=0;");

$log = [];
$now = date('Y-m-d H:i:s');

// ══════════════════════════════════════════════════════════════
// STEP 1 — STERGERE TOTALA
// ══════════════════════════════════════════════════════════════
try {
    // Sterge in ordinea corecta (FK)
    $pdo->exec("DELETE FROM offer_attachments WHERE 1");
    $pdo->exec("DELETE FROM offer_items WHERE 1");
    $pdo->exec("DELETE FROM offers WHERE 1");
    $pdo->exec("DELETE FROM documents WHERE 1");
    $pdo->exec("DELETE FROM services WHERE 1");
    $pdo->exec("DELETE FROM project_documents WHERE 1");
    $pdo->exec("DELETE FROM projects WHERE 1");
    $pdo->exec("DELETE FROM client_contacts WHERE 1");
    $pdo->exec("DELETE FROM clients WHERE 1");
    $log[] = "OK: Stergere completa finalizata";
} catch(Exception $e) { $log[] = "ERR Stergere: ".$e->getMessage(); die(implode("\n", $log)); }

$pdo->exec("SET FOREIGN_KEY_CHECKS=1;");

// ══════════════════════════════════════════════════════════════
// STEP 2 — DETECTIE COLOANE
// ══════════════════════════════════════════════════════════════
$clientCols = array_column($pdo->query("DESCRIBE clients")->fetchAll(), 'Field');
$hasWebsite = in_array('website', $clientCols);
$hasSource  = in_array('source', $clientCols);
$log[] = "INFO: website=".($hasWebsite?'DA':'NU')." source=".($hasSource?'DA':'NU');

// Helper insert client
function insertClient($pdo, $data) {
global $hasWebsite, $hasSource;
$base = ['profile_id','company_name','cui','reg_com','address','city','county','country','phone','email','industry','notes','pipeline_status','estimated_value','created_at'];
if ($hasWebsite) $base[] = 'website';
if ($hasSource)  $base[] = 'source';
$cols = []; $vals = [];
foreach ($base as $c) { if (isset($data[$c])) { $cols[] = $c; $vals[] = $data[$c]; } }
$sql = "INSERT INTO clients (".implode(',',$cols).") VALUES (".implode(',',array_fill(0,count($cols),'?')).")";    
$pdo->prepare($sql)->execute($vals);
    return (int)$pdo->lastInsertId();
}

// Helper insert project
function insertProject($pdo, $data) {
    $cols = array_keys($data);
    $sql = "INSERT INTO projects (".implode(',',$cols).") VALUES (".implode(',',array_fill(0,count($cols),'?')).")";
    $pdo->prepare($sql)->execute(array_values($data));
    return (int)$pdo->lastInsertId();
}

// Helper insert offer + items
function insertOffer($pdo, $data, $items=[]) {
    $cols = array_keys($data);
    $sql = "INSERT INTO offers (".implode(',',$cols).") VALUES (".implode(',',array_fill(0,count($cols),'?')).")";
    $pdo->prepare($sql)->execute(array_values($data));
    $oid = (int)$pdo->lastInsertId();
    $sub = 0;
    foreach ($items as $i => $it) {
        $total = $it['qty'] * $it['price'];
        $sub += $total;
        $pdo->prepare("INSERT INTO offer_items (offer_id,title,description,unit,quantity,unit_price,total_price,sort_order) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$oid, $it['title'], $it['desc']??'', $it['unit']??'serviciu', $it['qty'], $it['price'], $total, $i+1]);
    }
    if ($sub > 0) {
        $vat = ($data['vat_rate']??0)/100 * $sub;
        $pdo->prepare("UPDATE offers SET subtotal=?,vat_amount=?,total=? WHERE id=?")
            ->execute([$sub, $vat, $sub+$vat, $oid]);
    }
    return $oid;
}

// Helper insert document
function insertDoc($pdo, $profileId, $clientId, $projectId, $type, $num, $title, $html, $status, $signed=null, $valid=null, $notes='') {
    global $now;
    $pdo->prepare("INSERT INTO documents (profile_id,client_id,project_id,doc_type,doc_number,title,content_html,status,signed_date,valid_until,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$profileId,$clientId,$projectId,$type,$num,$title,$html,$status,$signed,$valid,$notes,$now]);
    return (int)$pdo->lastInsertId();
}

// Helper attach file to offer
function attachFile($pdo, $offerId, $fname, $fpath, $desc='') {
    global $now;
    $full = __DIR__.'/../oferte/'.$fname;
    $size = file_exists($full) ? filesize($full) : 0;
    $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
    $mime = $ext === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    $pdo->prepare("INSERT INTO offer_attachments (offer_id,file_name,file_path,file_size,file_type,description,uploaded_at) VALUES (?,?,?,?,?,?,?)")
        ->execute([$offerId,$fname,'oferte/'.$fname,$size,$mime,$desc,$now]);
}

$logoCS  = 'https://crm.iosub.ro/assets/img/logo-cybershield.png';
$logoWHT = 'https://crm.iosub.ro/assets/img/logo-wht.png';
$sig     = 'https://crm.iosub.ro/assets/img/semnatura.png';

// ══════════════════════════════════════════════════════════════
// CLIENTI, PROIECTE, OFERTE
// ══════════════════════════════════════════════════════════════

// ── 1. AIR CLAIM S.A. (profile CyberShield = 1) ──────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'AIR CLAIM S.A.','cui'=>'39395976',
        'reg_com'=>'J23/016631402','address'=>'Bdul. Pipera Nr.1/VI Hyperion Towers, et.3',
        'city'=>'Voluntari','county'=>'Ilfov','country'=>'Romania',
        'phone'=>'+40 760 616 930','email'=>'as@airclaim.com',
        'website'=>'https://airclaim.com','industry'=>'Aviație / Servicii digitale',
        'notes'=>'Platformă digitală gestionare reclamații zboruri. NDA + Contract nr.23 / 12.03.2026.',
        'pipeline_status'=>'won','estimated_value'=>7000.00,'source'=>'manual','created_at'=>$now,
    ]);
    $pdo->prepare("INSERT INTO client_contacts (client_id,full_name,position,email,phone,is_primary) VALUES (?,?,?,?,?,?)")
        ->execute([$cid,'STRAUT DAN-ANDREI','Administrator','as@airclaim.com','+40 760 616 930',1]);
    $log[] = "OK: AIR CLAIM S.A. (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Audit Securitate Cibernetică — Air Claim',
        'description'=>'Training personal, audit website OWASP, audit email SPF/DKIM/DMARC, analiză loguri Hetzner (forensică), raport final.',
        'status'=>'active','estimated_budget'=>7000.00,'priority'=>'high','category'=>'audit',
        'brand'=>'cybershield','notes'=>'Contract nr.23 / 12.03.2026. Termen 15 zile. Onorariu 7000 EUR.','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'CYB-2026-001','offer_date'=>'2026-03-12','valid_until'=>'2026-04-12',
        'status'=>'accepted','currency'=>'EUR','vat_rate'=>0,'brand'=>'cybershield',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Audit securitate cibernetică integrat: training personal, pentest website, audit email, forensică Hetzner.',
        'deliverables_text'=>"- Raport training + materiale\n- Raport audit website (OWASP, PoC vulnerabilități)\n- Raport audit email (SPF/DKIM/DMARC)\n- Raport forensic loguri Hetzner\n- Raport Audit Final cu plan acțiune",
        'methodology_text'=>"- OWASP Testing Guide v4.2\n- NIST SP 800-115\n- ISO/IEC 27001:2022\n- PTES + RFC 3227",
        'terms_text'=>"Onorariu: 7.000 EUR în lei BNR\nTermen plată: 5 zile de la factură\nPenalități: 0,1%/zi\nTermen execuție: 15 zile\nConfidențialitate: 5 ani, daune min. 50.000 EUR",
        'created_at'=>$now,
    ], [
        ['title'=>'Training Securitate Cibernetică Personal','desc'=>'Sesiuni practice 4-6h: phishing, inginerie socială, parole, reactie incidente.','unit'=>'serviciu','qty'=>1,'price'=>1500.00],
        ['title'=>'Audit Website Air Claim','desc'=>'Pentest OWASP Top 10+, security headers, autentificare, sesiuni, scraping. Raport cu PoC.','unit'=>'serviciu','qty'=>1,'price'=>1800.00],
        ['title'=>'Audit Server Email','desc'=>'SPF/DKIM/DMARC, vulnerabilități SMTP, loguri suspecte.','unit'=>'serviciu','qty'=>1,'price'=>1200.00],
        ['title'=>'Analiză Forensică Loguri Hetzner','desc'=>'Conservare probe, cronologie, entry-points atacatori, date accesate.','unit'=>'serviciu','qty'=>1,'price'=>1500.00],
        ['title'=>'Raport Audit Final','desc'=>'Executive summary, CVSS, plan acțiune termen scurt/mediu/lung.','unit'=>'serviciu','qty'=>1,'price'=>1000.00],
    ]);
    attachFile($pdo, $oid, 'CyberShield x AirClaim - NDA.pdf', 'NDA semnat AirClaim');
    attachFile($pdo, $oid, 'CyberShield x AirClaim - Contract 23.pdf', 'Contract nr.23 semnat');
    $log[] = "OK: Ofertă + documente AirClaim";

    // NDA AirClaim
    insertDoc($pdo, 1, $cid, $pid, 'nda_cs', 'NDA-CYB-2026-001', 'Acord de Confidențialitate — AIR CLAIM S.A.',
        '<p>NDA CyberShield x AIR CLAIM S.A. — Nr. NDA-CYB-2026-001 din 12.03.2026. Semnat.</p>',
        'signed','2026-03-12','2031-03-12','NDA semnat 12.03.2026');
    // Contract AirClaim
    insertDoc($pdo, 1, $cid, $pid, 'contract_cs', 'CTR-23-2026', 'Contract Prestări Servicii nr.23 — AIR CLAIM S.A.',
        '<p>Contract nr.23 / 12.03.2026 — CyberShield x AIR CLAIM S.A. — 7.000 EUR. Semnat.</p>',
        'signed','2026-03-12','2026-03-27','Contract nr.23, 7000 EUR, 15 art.');
    $log[] = "OK: NDA + Contract AirClaim";
} catch(Exception $e) { $log[] = "ERR AirClaim: ".$e->getMessage(); }

// ── 2. ZBOR.MD (profile CyberShield = 1) ─────────────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'Zbor.md','city'=>'Chișinău','country'=>'Moldova',
        'industry'=>'Aviație / Transport aerian',
        'notes'=>'Ofertă Digital Threat Awareness. Online 799 lei/pers, Fizic 1240 lei/pers, min 10 participanți.',
        'pipeline_status'=>'offer_sent','estimated_value'=>12400.00,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: Zbor.md (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Digital Threat Awareness — Zbor.md',
        'description'=>'Training angajați: navigare sigură, phishing, spoofing, voice cloning/deepfake AI, GDPR, parole, reacție incidente.',
        'status'=>'active','estimated_budget'=>12400.00,'priority'=>'medium','category'=>'training',
        'brand'=>'cybershield','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'CYB-2026-002','offer_date'=>'2026-03-19','valid_until'=>'2026-04-18',
        'status'=>'sent','currency'=>'RON','vat_rate'=>0,'brand'=>'cybershield',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Program Digital Threat Awareness pentru Zbor.md — training angajați împotriva amenințărilor digitale.',
        'terms_text'=>"Online: 799 lei/participant | Fizic: 1.240 lei/participant\nGrup minim: 10 participanți\nValabilitate: 30 zile",
        'created_at'=>$now,
    ], [
        ['title'=>'Digital Threat Awareness — Online (ZOOM)','desc'=>'3 zile x 2h. Phishing, voice cloning AI, GDPR, 2FA. Certificat + suport 30 zile.','unit'=>'participant','qty'=>10,'price'=>799.00],
        ['title'=>'Digital Threat Awareness — Fizic','desc'=>'2 zile x 3h. Conținut intensiv la sediu. Costuri deplasare separate.','unit'=>'participant','qty'=>10,'price'=>1240.00],
    ]);
    attachFile($pdo, $oid, 'Propunere Digital Threat Awareness.pdf', 'Propunere Digital Threat Awareness');
    $log[] = "OK: Ofertă Zbor.md";
} catch(Exception $e) { $log[] = "ERR Zbor.md: ".$e->getMessage(); }

// ── 3. AXFINA (profile WHT = 1, brand wht) ───────────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'AXFINA','city'=>'București','country'=>'România',
        'industry'=>'Servicii financiare',
        'notes'=>'Discount 15% prima colaborare. Agenți vocali AI, phishing simulat, cursuri, pentest. WHT + CyberShield (CSR).',
        'pipeline_status'=>'negotiation','estimated_value'=>85000.00,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: AXFINA (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Securitate & Agenți AI — AXFINA',
        'description'=>'Agenți vocali AI inbound+outbound, campanie phishing simulată, cursuri toate nivelurile, pentest complet.',
        'status'=>'active','estimated_budget'=>85000.00,'priority'=>'high','category'=>'audit',
        'brand'=>'wht','notes'=>'Discount 15% prima colaborare.','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'WHT-2026-001','offer_date'=>'2026-03-21','valid_until'=>'2026-04-20',
        'status'=>'sent','currency'=>'RON','vat_rate'=>19,'brand'=>'wht',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Pachet integrat securitate cibernetică: agenți vocali AI + simulare atac + educație + pentest. Discount 15% prima colaborare.',
        'terms_text'=>"Discount 15% prima colaborare\nTotal cu TVA: 85.000 RON\nPlată: 30% avans + 70% la livrare\nValabilitate: 30 zile",
        'created_at'=>$now,
    ], [
        ['title'=>'Agenți Vocali AI — Inbound + Outbound (română, GDPR)','desc'=>'Dialog natural română, scenarii aprobate, GDPR compliant. Implementare etapizată.','unit'=>'implementare','qty'=>1,'price'=>25000.00],
        ['title'=>'Campanie Phishing Simulată cu Raport','desc'=>'E-mailuri personalizate, monitorizare reacții, analiză pe departamente, debriefing.','unit'=>'campanie','qty'=>1,'price'=>8500.00],
        ['title'=>'Cursuri Securitate — Toate Nivelurile','desc'=>'Angajați, IT, management/CISO, developeri. Certificat + suport 30 zile.','unit'=>'training','qty'=>1,'price'=>18000.00],
        ['title'=>'Teste de Penetrare Complet','desc'=>'Rețea, web/API, servicii expuse, securitate fizică. OWASP/NIST. Raport CVSS.','unit'=>'pentest','qty'=>1,'price'=>19928.57],
    ]);
    attachFile($pdo, $oid, 'Oferta_AXFINA.pdf', 'Ofertă AXFINA PDF');
    attachFile($pdo, $oid, 'Servicii WHT.pdf', 'Lista servicii WHT');
    $log[] = "OK: Ofertă AXFINA";
} catch(Exception $e) { $log[] = "ERR AXFINA: ".$e->getMessage(); }

// ── 4. ANPCDDA (profile CyberShield = 1) ─────────────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'ANPCDDA','city'=>'București','country'=>'România',
        'industry'=>'Administrație publică',
        'notes'=>'Propunere parteneriat educație în securitate cibernetică.',
        'pipeline_status'=>'offer_sent','estimated_value'=>0,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: ANPCDDA (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Parteneriat Educație Digitală — ANPCDDA',
        'description'=>'Propunere parteneriat educație în securitate cibernetică.',
        'status'=>'active','priority'=>'medium','category'=>'consulting',
        'brand'=>'cybershield','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'CYB-2025-ANPCDDA','offer_date'=>'2025-01-01','valid_until'=>'2025-01-31',
        'status'=>'sent','currency'=>'RON','vat_rate'=>0,'brand'=>'cybershield',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Propunere parteneriat educație în securitate cibernetică — ANPCDDA.',
        'terms_text'=>"Parteneriat non-oneros: Asociația CyberShield oferă program educațional complet\nÎn schimbul: Cesiunea în folosință a unui spațiu de ~100mp\nUtilități (electricitate, apă, gaz, întreținere) rămân în sarcina ANPCDDA\nCyberShield asigură: dotarea integrală a spațiului, conectivitate internet proprie",
        'deliverables_text'=>"- Program educațional securitate cibernetică\n- Cursuri, workshopuri, podcast educațional\n- Laborator IT&C funcțional\n- Studio media / podcast\n- Rapoarte activitate periodice",
        'created_at'=>$now,
    ], [
        ['title'=>'Program Educațional Securitate Cibernetică — Centru CyberSkill','desc'=>'Cursuri, workshopuri și program educațional complet în schimbul cesiunii spațiu ~100mp. Include: sală curs 15-20 locuri, studio media, laborator IT&C, birou administrativ.','unit'=>'parteneriat','qty'=>1,'price'=>0],
    ]);
    attachFile($pdo, $oid, 'CyberShield_propunere_parteneriat_ANPCDDA.pdf', 'Propunere parteneriat ANPCDDA');
    $log[] = "OK: Ofertă ANPCDDA";
} catch(Exception $e) { $log[] = "ERR ANPCDDA: ".$e->getMessage(); }

// ── 5. DGASPC SECTOR 3 (profile CyberShield = 1) ─────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'DGASPC Sector 3','city'=>'București','country'=>'România',
        'industry'=>'Administrație publică / Asistență socială',
        'notes'=>'Agent vocal AI inbound (setup 3.850 lei + abonam. 24.500 lei/lună) + cursuri securitate cibernetică (1.000–1.250 lei/cursant).',
        'pipeline_status'=>'offer_sent','estimated_value'=>78350.00,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: DGASPC Sector 3 (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Agent Vocal AI + Cursuri CyberShield — DGASPC S3',
        'description'=>'Ofertă agent vocal AI (inbound/outbound) și cursuri securitate cibernetică pentru angajați.',
        'status'=>'active','priority'=>'medium','category'=>'training',
        'brand'=>'cybershield','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'CYB-2025-DGASPC','offer_date'=>'2025-03-24','valid_until'=>'2025-04-23',
        'status'=>'sent','currency'=>'RON','vat_rate'=>19,'brand'=>'cybershield',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Ofertă agent vocal AI (inbound) și cursuri securitate cibernetică — DGASPC Sector 3.',
        'terms_text'=>"Setup & configurare agent vocal: 3.850 lei (o singură dată)\nAbonament lunar serviciu complet (10.000 min incluse): 24.500 lei/lună\nDurată minimă contract: 3 luni calendaristice\nPachet minute suplimentare: 1.000 lei / 500 min\nCursuri: 1.000 lei/cursant (standard) | 1.250 lei/cursant (avansat)\nReducere 10% la min. 3 grupe | 15% la min. 5 grupe simultane\nPrețuri exprimate fără TVA",
        'deliverables_text'=>"Agent Vocal:\n- Acces platformă telefonie cu Agent Vocal activ\n- Rapoarte zilnice automate (volum, tematici, rate transfer)\n- Raport lunar consolidat cu analiză tendințe\n- Transcrieri și înregistrări 90 zile\n- Actualizări bază cunoștințe la cerere\nCursuri:\n- Acces platformă e-learning 1 an\n- Certificate digitale de absolvire\n- Rapoarte progres per angajat\n- Materiale suport digitale",
        'created_at'=>$now,
    ], [
        ['title'=>'Agent Vocal AI — Setup & Configurare','desc'=>'Configurare inițială agent vocal: integrare telefonie, antrenare pe proceduri DGASPC, testare, instruire personal, lansare producție.','unit'=>'robot','qty'=>1,'price'=>3850.00],
        ['title'=>'Agent Vocal AI — Abonament Lunar (include 10.000 min)','desc'=>'Serviciu complet inbound: preluare apeluri, răspunsuri calificate, transfer inteligent, rapoarte zilnice. Disponibil 24/7. Durată minimă 3 luni.','unit'=>'lună','qty'=>3,'price'=>24500.00],
        ['title'=>'Curs Securitate Cibernetică Esențială (Standard)','desc'=>'3 zile × 3h (9h total). Phishing, parole, GDPR, incidente. Platformă e-learning, certificate, suport. Per cursant.','unit'=>'cursant','qty'=>1,'price'=>1000.00],
        ['title'=>'Curs Securitate Avansată pentru Administratori IT','desc'=>'5 zile × 3h (15h total). Configurare securizată, patch management, răspuns la incidente. Nivel avansat. Per cursant.','unit'=>'cursant','qty'=>1,'price'=>1250.00],
        ['title'=>'Curs Inginerie Socială și Manipulare Digitală','desc'=>'3 zile × 3h. Tehnici de manipulare, deepfake, voice cloning AI, vishing, smishing. Certificate incluse. Per cursant.','unit'=>'cursant','qty'=>1,'price'=>1000.00],
    ]);
    attachFile($pdo, $oid, 'Oferta_Agent_Vocal_DGASPC_Sector3.pdf', 'Ofertă agent vocal DGASPC');
    attachFile($pdo, $oid, 'Propunere_Cursuri_CyberShield_DGASPC_S3.pdf', 'Propunere cursuri CyberShield DGASPC S3');
    $log[] = "OK: Ofertă DGASPC Sector 3";
} catch(Exception $e) { $log[] = "ERR DGASPC S3: ".$e->getMessage(); }

// ── 6. TCM ROMÂNIA (profile WHT = 1, brand wht) ──────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'TCM România','city'=>'București','country'=>'România',
        'industry'=>'Construcții / Administrare Clădiri',
        'notes'=>'Ofertă servicii securitate cibernetică WHT. Compania Municipală Trustul de Clădiri Metropolitane S.A.',
        'pipeline_status'=>'offer_sent','estimated_value'=>93000.00,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: TCM România (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Servicii Securitate Cibernetică — TCM România',
        'description'=>'Ofertă servicii securitate cibernetică White Hat Technology.',
        'status'=>'active','priority'=>'medium','category'=>'consulting',
        'brand'=>'wht','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'WHT-2025-TCM','offer_date'=>'2026-03-19','valid_until'=>'2026-04-18',
        'status'=>'sent','currency'=>'RON','vat_rate'=>21,'brand'=>'wht',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Pachet integrat servicii profesionale de securitate cibernetică pentru TCM România.',
        'methodology_text'=>"- OWASP Testing Guide — testarea aplicațiilor web și API\n- NIST SP 800-115 — standard pentru testele de penetrare\n- PTES (Penetration Testing Execution Standard)\n- ISO/IEC 27001 — cadru de referință pentru auditul de securitate\n- GDPR (UE) 2016/679 și NIS2 (UE) 2022/2555\n- ENISA guidelines — securitate cibernetică europeană",
        'deliverables_text'=>"- Raport de audit detaliat cu constatări, niveluri de risc și recomandări prioritizate\n- Raport tehnic de pentest cu vulnerabilități PoC, scoruri CVSS și plan de remediere\n- Raport de vulnerabilități cu hartă rețea actualizată și plan patch management\n- Raport campanie phishing (rata de click, departamente vulnerabile) + certificate participare\n- Versiune executivă a rapoartelor accesibilă managementului",
        'terms_text'=>"Valabilitate ofertă: 30 zile de la data emiterii\nTermen de execuție: se stabilește la semnarea contractului\nModalitate de plată: în tranșe, conform clauzelor contractuale\nConfidențialitate: NDA semnat anterior oricărei activități\nTVA: 21% conform legislației fiscale",
        'created_at'=>$now,
    ], [
        ['title'=>'Audit de Securitate Cibernetică','desc'=>'Evaluare comprehensivă a infrastructurii IT, politicilor și conformității (GDPR, NIS2, ISO/IEC 27001). Constatări, niveluri de risc, recomandări prioritizate.','unit'=>'serviciu','qty'=>1,'price'=>22000.00],
        ['title'=>'Test de Penetrare (intern, extern, web, VPN, wireless)','desc'=>'Pentest conform PTES, OWASP Testing Guide și NIST SP 800-115. Raport cu vulnerabilități identificate, probe PoC și plan de remediere.','unit'=>'serviciu','qty'=>1,'price'=>28000.00],
        ['title'=>'Evaluare Securitate Fizică și Analiză Riscuri Organizaționale','desc'=>'Evaluare la față locului: controlul accesului, camere CCTV, echipamente expuse, arhive. Scenarii de compromitere fizică. Raport cu recomandări.','unit'=>'serviciu','qty'=>1,'price'=>18000.00],
        ['title'=>'Evaluare Vulnerabilități (VA Scan + Analiză Manuală)','desc'=>'Scanare activă automată + analiză manuală, clasificare CVSS v3.1, corelare CVE, network mapping complet, plan de remediere prioritizat.','unit'=>'serviciu','qty'=>1,'price'=>12000.00],
        ['title'=>'Campanie Simulare Phishing + Training de Conștientizare','desc'=>'Minimum 2 campanii etice spear-phishing, monitorizare reacții, analiză pe departamente. Training interactiv 2-3h cu materiale și certificate.','unit'=>'campanie','qty'=>1,'price'=>13000.00],
    ]);
    attachFile($pdo, $oid, 'Oferta_WHT_TCM.pdf', 'Ofertă WHT TCM PDF');
    $log[] = "OK: Ofertă TCM";
} catch(Exception $e) { $log[] = "ERR TCM: ".$e->getMessage(); }

// ── 7. FUNCȚIONARI S2 (cursuri) ───────────────────────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'Primăria Sectorului 2 — Funcționari Publici','city'=>'București','country'=>'România',
        'industry'=>'Administrație publică',
        'notes'=>'Proiect cursuri funcționari S2. Analiză inițială: 54.000 lei. Cursuri online: 520 lei/cursant. Cursuri fizice: 1.000 lei/cursant.',
        'pipeline_status'=>'offer_sent','estimated_value'=>54000.00,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: Funcționari S2 (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Cursuri Funcționari Publici — Semestrul 2',
        'description'=>'Proiect cursuri de securitate cibernetică pentru funcționari publici, semestrul 2.',
        'status'=>'active','priority'=>'medium','category'=>'training',
        'brand'=>'cybershield','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'CYB-2025-FUNC-S2','offer_date'=>'2025-01-01','valid_until'=>'2025-01-31',
        'status'=>'sent','currency'=>'RON','vat_rate'=>19,'brand'=>'cybershield',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Proiect cursuri securitate cibernetică pentru funcționari publici Sectorul 2, semestrul 2. Platforma e-learning validată, cursuri online și fizice, certificate de absolvire.',
        'terms_text'=>"Cursuri online: 520 lei + TVA / cursant (16h pregătire, acces platformă 1 an)\nCursuri fizice (conducere): 1.000 lei + TVA / cursant (2 zile intensive, max. 20 pers/grupă)\nAnaliza inițială + personalizare conținut + configurare: 54.000 lei + TVA (o singură dată)\nLivrate în max. 10 zile lucrătoare de la semnarea contractului",
        'deliverables_text'=>"- Analiză preliminară și personalizare conținut per instituție\n- Acces platformă e-learning cu funcționalitate completă\n- Module practice adaptate funcționarilor publici\n- Certificat digital de absolvire per participant\n- Rapoarte centralizate progres (HR, IT/DPO)\n- Diplomă digitală de absolvire",
        'created_at'=>$now,
    ], [
        ['title'=>'Analiză Inițială + Personalizare Conținut + Configurare Platformă','desc'=>'Evaluare specifică fiecărei instituții subordonate, personalizare module educaționale, configurare conturi și alocare cursuri. Cost unic la începutul proiectului.','unit'=>'proiect','qty'=>1,'price'=>54000.00],
        ['title'=>'Licență Cursuri Online — Platformă E-Learning','desc'=>'Acces individual 1 an, 16 ore de pregătire, testare automată, raportare progres, reluare lecții, certificate digitale. Adaptat funcționarilor publici.','unit'=>'cursant','qty'=>1,'price'=>520.00],
        ['title'=>'Cursuri Fizice (Personal cu Funcții de Conducere)','desc'=>'Cursuri intensive 2 zile la sediu. Maxim 20 persoane/grupă. Include formatori, materiale și certificate. Conținut adaptat conducerii instituționale.','unit'=>'cursant','qty'=>1,'price'=>1000.00],
    ]);
    attachFile($pdo, $oid, 'Proiect cursuri functionari S2.pdf', 'Proiect cursuri funcționari S2');
    $log[] = "OK: Ofertă Funcționari S2";
} catch(Exception $e) { $log[] = "ERR FunctionariS2: ".$e->getMessage(); }

// ── 8. P10LED ─────────────────────────────────────────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'P10LED','city'=>'București','country'=>'România',
        'industry'=>'Tehnologie / LED / Afișaj',
        'notes'=>'Client potențial WHT / CyberShield.',
        'pipeline_status'=>'lead','estimated_value'=>0,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: P10LED (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Securitate Cibernetică — P10LED',
        'description'=>'Potențial proiect securitate cibernetică P10LED.',
        'status'=>'draft','priority'=>'low','category'=>'consulting',
        'brand'=>'wht','created_at'=>$now,
    ]);
    $log[] = "OK: Proiect P10LED";
} catch(Exception $e) { $log[] = "ERR P10LED: ".$e->getMessage(); }

// ── 9. DGITL (WHT) ───────────────────────────────────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'DGITL — Direcția Generală de Impozite și Taxe Locale','city'=>'București','country'=>'România',
        'industry'=>'Administrație publică / Fiscalitate',
        'notes'=>'Propunere audit și securitate cibernetică WHT.',
        'pipeline_status'=>'lead','estimated_value'=>130000.00,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: DGITL (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Propunere Audit PenTest Securitate — DGITL Sector 2',
        'description'=>'Audit PenTest complet (intern + extern + fizic) la nivelul DGITL Sector 2. Durată estimată 30 zile lucrătoare.',
        'status'=>'draft','priority'=>'medium','category'=>'audit',
        'brand'=>'wht','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'WHT-2025-DGITL','offer_date'=>'2025-01-01','valid_until'=>'2025-01-31',
        'status'=>'draft','currency'=>'RON','vat_rate'=>21,'brand'=>'wht',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Propunere audit PenTest (intern + extern + fizic) securitate cibernetică — DGITL White Hat Technology.',
        'methodology_text'=>"- ISO/IEC 27001, NIST SP 800-115, OWASP Web Security Testing Guide\n- Ghiduri ENISA și HG 406/2023 (sisteme informatice administrație publică locală)\n- Principii de ethical hacking cu acord prealabil, fără impact asupra funcționării serviciilor",
        'deliverables_text'=>"- Raport tehnic complet cu vulnerabilitățile identificate\n- Raport de sinteză managerială\n- Recomandări clare de remediere și prioritizare\n- Plan de acțiune pe termen scurt/mediu/lung",
        'terms_text'=>"Durată estimată: 30 zile lucrătoare\nCostul poate varia în funcție de complexitatea infrastructurii\nNDA semnat anterior oricărei activități\nTVA: 21%",
        'created_at'=>$now,
    ], [
        ['title'=>'Audit Pentest Complet — Intern + Extern + Fizic (DGITL Sector 2)','desc'=>'Testare externă (black-box), testare internă (gray-box), evaluare rețea internă, audit fizic securitate, verificare GDPR/NIS2, simulare chain attack, raport tehnic + managerial.','unit'=>'proiect','qty'=>1,'price'=>130000.00],
    ]);
    attachFile($pdo, $oid, 'Propunere DGITL - WHT.pdf', 'Propunere DGITL WHT');
    $log[] = "OK: Ofertă DGITL";
} catch(Exception $e) { $log[] = "ERR DGITL: ".$e->getMessage(); }

// ── 10. PREFECTURA BIHOR (WHT) ────────────────────────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'Prefectura Bihor','city'=>'Oradea','county'=>'Bihor','country'=>'România',
        'industry'=>'Administrație publică',
        'notes'=>'Propunere audit pentest securitate cibernetică WHT. Instituția Prefectului Județul Bihor. ~40 stații de lucru.',
        'pipeline_status'=>'lead','estimated_value'=>70000.00,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: Prefectura Bihor (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Audit PenTest Securitate — Prefectura Bihor',
        'description'=>'Audit tehnic, procedural și fizic. ~40 stații de lucru. Campanie phishing 40 angajați. Audit fizic simulare pătrundere neautorizată. Durată 15 zile.',
        'status'=>'draft','priority'=>'medium','category'=>'audit',
        'brand'=>'wht','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'WHT-2025-PH-BH','offer_date'=>'2025-01-01','valid_until'=>'2025-01-31',
        'status'=>'draft','currency'=>'RON','vat_rate'=>21,'brand'=>'wht',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Propunere audit combinat (tehnic, procedural, fizic) — Instituția Prefectului Județul Bihor. White Hat Technology.',
        'methodology_text'=>"- ISO/IEC 27001, NIST SP 800-115, OWASP Web Security Testing Guide\n- Ghiduri ENISA și HG 406/2023\n- Principii ethical hacking cu acord prealabil",
        'deliverables_text'=>"- Raport tehnic complet cu vulnerabilitățile identificate\n- Raport de sinteză managerială\n- Recomandări clare de remediere și prioritizare\n- Plan de măsuri prioritizat pe termen scurt/mediu/lung",
        'terms_text'=>"Durată estimată: 15 zile lucrătoare\nCostul poate varia în funcție de complexitate și cerințe suplimentare\nTVA: 21%",
        'created_at'=>$now,
    ], [
        ['title'=>'Audit PenTest Intern + Extern — Instituția Prefectului Bihor','desc'=>'Audit tehnic sondaj ~40 stații, auditare comunicare internă/externă, campanie phishing controlată 40 angajați, audit fizic simulare pătrundere neautorizată, raport + plan măsuri.','unit'=>'proiect','qty'=>1,'price'=>70000.00],
    ]);
    attachFile($pdo, $oid, 'Propunere Prefectura BH - WHT .pdf', 'Propunere Prefectura Bihor WHT');
    $log[] = "OK: Ofertă Prefectura Bihor";
} catch(Exception $e) { $log[] = "ERR PrefBH: ".$e->getMessage(); }

// ── 11. PREFECTURA SUCEAVA (WHT) ─────────────────────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'Prefectura Suceava','city'=>'Suceava','county'=>'Suceava','country'=>'România',
        'industry'=>'Administrație publică',
        'notes'=>'Propunere audit securitate cibernetică WHT. Primăria Municipiului Suceava. ~350 stații de lucru + 15 servere.',
        'pipeline_status'=>'lead','estimated_value'=>208000.00,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: Prefectura Suceava (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Audit Securitate Cibernetică — Primăria Municipiului Suceava',
        'description'=>'Audit complet: securitate cibernetică, pentest, evaluare vulnerabilități (350 stații + 15 servere), securitate fizică, campanie phishing + training.',
        'status'=>'draft','priority'=>'high','category'=>'audit',
        'brand'=>'wht','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'WHT-2025-PH-SV','offer_date'=>'2026-03-23','valid_until'=>'2026-04-22',
        'status'=>'draft','currency'=>'RON','vat_rate'=>21,'brand'=>'wht',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Pachet integrat audit securitate cibernetică pentru Primăria Municipiului Suceava (350 stații de lucru + 15 servere). White Hat Technology.',
        'methodology_text'=>"- OWASP Testing Guide v4.2 (testare aplicații web și API)\n- NIST SP 800-115 (testele de penetrare)\n- PTES (Penetration Testing Execution Standard)\n- ISO/IEC 27001:2022 (audit sistem management securitate)\n- GDPR (UE) 2016/679 și NIS2 (UE) 2022/2555\n- ENISA Guidelines\n- CVSS v3.1 (scorare și prioritizare vulnerabilități)",
        'deliverables_text'=>"- Raport audit detaliat (constatări, niveluri risc Critic/Ridicat/Mediu/Scăzut, recomandări prioritizate)\n- Raport tehnic pentest (vulnerabilități, probe PoC, scoruri CVSS, plan remediere)\n- Raport evaluare vulnerabilități (inventar CVE, hartă rețea, matrice risc, plan patch management)\n- Raport securitate fizică (acces fizic, recomandări tehnice, dovezi)\n- Raport campanie phishing (rata click pe departamente, analiza comportament)\n- Materiale training + certificate nominalizate\n- Versiune executivă a rapoartelor (5-10 pag/componentă)",
        'terms_text'=>"Valabilitate ofertă: 30 zile (până la 22.04.2026)\nTermen execuție: ~4 săptămâni, definitiv la semnare\nPlată în tranșe: 40% avans la semnare, 60% la finalizare\nActivități exclusiv cu acordul scris al beneficiarului\nTVA: 21%\nNota: Servicii suplimentare (SIEM, consultanță NIS2 extinsă) ofertate separat la cerere",
        'created_at'=>$now,
    ], [
        ['title'=>'Audit de Securitate Cibernetică (conformitate GDPR, NIS2, ISO/IEC 27001)','desc'=>'Analiza politicilor, configurații securitate stații/servere/echipamente rețea, conformitate GDPR/NIS2, gestiunea conturilor, backup, loguri, inventar active IT.','unit'=>'serviciu','qty'=>1,'price'=>42000.00],
        ['title'=>'Test de Penetrare — Intern, Extern, Web, VPN, Wireless','desc'=>'Pentest extern (black-box), intern (angajat malițios), aplicații web (OWASP Top 10), rețea wireless, VPN. Probe PoC, escaladare privilegii, lateral movement. Raport CVSS.','unit'=>'serviciu','qty'=>1,'price'=>55000.00],
        ['title'=>'Evaluare Securitate Fizică și Analiză Riscuri Organizaționale','desc'=>'Inspecție fizică sedii, controlul accesului, CCTV, echipamente expuse, insider threat, clear-desk policy, documente sensibile. Scenarii simulare pătrundere neautorizată.','unit'=>'serviciu','qty'=>1,'price'=>27000.00],
        ['title'=>'Evaluare Vulnerabilități — VA Scan + Analiză Manuală (350 stații + 15 servere)','desc'=>'Scanare activă automată (Nessus/OpenVAS), analiză manuală, eliminare false pozitive, clasificare CVSS v3.1, corelare CVE, network mapping complet, plan patch management.','unit'=>'serviciu','qty'=>1,'price'=>49000.00],
        ['title'=>'Campanie Simulare Phishing (min. 2 campanii) + Training Conștientizare','desc'=>'Min. 2 campanii etice spear-phishing (300 emailuri x 2), pagini captură realiste, analiză pe departamente. Training interactiv sefii directiilor + materiale suport + certificate.','unit'=>'campanie','qty'=>1,'price'=>35000.00],
    ]);
    attachFile($pdo, $oid, 'Propunere SV - WHT .pdf', 'Propunere Prefectura Suceava WHT');
    $log[] = "OK: Ofertă Prefectura Suceava";
} catch(Exception $e) { $log[] = "ERR PrefSV: ".$e->getMessage(); }

// ── 12. ȘCOALA NR.1 BERCENI ───────────────────────────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'Școala Gimnazială Nr.1 Berceni','city'=>'Berceni','county'=>'Ilfov','country'=>'România',
        'industry'=>'Educație',
        'notes'=>'Workshop 2 module × 3h pentru max. 25 cadre didactice. Preț: 6.200 lei total.',
        'pipeline_status'=>'lead','estimated_value'=>6200.00,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: Școala Nr.1 Berceni (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Workshop Cadre Didactice — Securitate Digitală',
        'description'=>'Workshop securitate cibernetică pentru cadre didactice Școala Nr.1 Berceni.',
        'status'=>'draft','priority'=>'low','category'=>'workshop',
        'brand'=>'cybershield','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'CYB-2025-BERCENI','offer_date'=>'2025-01-01','valid_until'=>'2025-01-31',
        'status'=>'draft','currency'=>'RON','vat_rate'=>0,'brand'=>'cybershield',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Propunere workshop dezvoltarea competențelor digitale și utilizarea responsabilă a tehnologiei pentru cadre didactice din învățământul primar — Școala Gimnazială Nr.1 Berceni.',
        'terms_text'=>"Grup recomandat: 15-25 cadre didactice\nLocație: la sediul școlii (sală cu proiector + internet)\nConfirmare dată: minimum 5 zile înainte\nDiscuție preliminară 30 min la school pentru clarificarea obiectivelor\nPrețul include: pregătire materiale, susținere sesiuni, emitere certificate, suporturi curs",
        'deliverables_text'=>"- Suport de curs în format digital\n- Checklist de implementare pentru fiecare modul\n- Model de reguli digitale pentru clasă\n- Certificat de participare pentru fiecare cadru didactic",
        'created_at'=>$now,
    ], [
        ['title'=>'Workshop Complet — 2 Module × 3h (max. 25 participanți)','desc'=>'Modul 1 (3h): Siguranță digitală și responsabilitate profesională — protejarea conturilor, platforme educaționale, GDPR elevi, comunicare online cu părinții. Modul 2 (3h): Inteligența artificială și integrarea responsabilă a tehnologiei la clasă.','unit'=>'program','qty'=>1,'price'=>6200.00],
    ]);
    attachFile($pdo, $oid, 'Propunere_Workshop_Cadre_Didactice_Scoala_1_Berceni_CyberShield.pdf', 'Propunere workshop cadre didactice Berceni');
    $log[] = "OK: Ofertă Berceni";
} catch(Exception $e) { $log[] = "ERR Berceni: ".$e->getMessage(); }

// ── 13. ANP ───────────────────────────────────────────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'ANP — Administrația Națională a Penitenciarelor','city'=>'București','country'=>'România',
        'industry'=>'Administrație publică / Penitenciare',
        'notes'=>'Program Digital Threat Awareness. Online: 799 lei/pers, Fizic: 1.240 lei/pers. Minim 10 participanți.',
        'pipeline_status'=>'offer_sent','estimated_value'=>12400.00,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: ANP (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Formare Competențe Digitale — ANP',
        'description'=>'Program formare competențe digitale pentru personalul ANP.',
        'status'=>'active','priority'=>'high','category'=>'training',
        'brand'=>'cybershield','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'CYB-2025-ANP','offer_date'=>'2025-01-01','valid_until'=>'2025-01-31',
        'status'=>'sent','currency'=>'RON','vat_rate'=>0,'brand'=>'cybershield',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Program Digital Threat Awareness pentru ANP — formare competențe digitale și conștientizare amenințări cibernetice pentru personalul ANP.',
        'terms_text'=>"Online: 799 lei/participant (ZOOM, 3 zile × 2h)\nFizic: 1.240 lei/participant (2 zile × 3h, la sediu)\nGrup minim: 10 participanți\nCosturi deplasare (sesiunile fizice): separate\nValabilitate: 30 zile de la transmiterea ofertei",
        'deliverables_text'=>"- Sesiuni live interactive (ZOOM sau fizic)\n- Materiale suport digitale\n- Certificat de participare per angajat\n- Suport post-training 30 zile",
        'created_at'=>$now,
    ], [
        ['title'=>'Digital Threat Awareness — Online (ZOOM)','desc'=>'3 zile × 2h. Phishing, voice cloning AI, deepfake, GDPR, 2FA, inginerie socială. Certificat + suport 30 zile. Minim 10 participanți.','unit'=>'participant','qty'=>10,'price'=>799.00],
        ['title'=>'Digital Threat Awareness — Fizic (la sediu)','desc'=>'2 zile × 3h. Conținut intensiv la sediul instituției. Include cazuri reale, exerciții practice. Costuri deplasare separate. Minim 10 participanți.','unit'=>'participant','qty'=>10,'price'=>1240.00],
    ]);
    attachFile($pdo, $oid, 'Propunere Digital Threat Awareness.pdf', 'Digital Threat Awareness ANP');
    $log[] = "OK: Ofertă ANP";
} catch(Exception $e) { $log[] = "ERR ANP: ".$e->getMessage(); }

// ── 14. AUDIT PREZENȚĂ ONLINE (WHT, client generic) ──────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'P10Led.ro','city'=>'București','country'=>'România',
        'industry'=>'Comerț / LED / Afișaj',
        'notes'=>'Propunere audit prezență online și marketing digital WHT. Client: P10Led.ro.',
        'pipeline_status'=>'lead','estimated_value'=>5000.00,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: Audit Online / P10Led (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Audit Prezență Online și Marketing Digital — P10Led.ro',
        'description'=>'Audit complet prezență online P10Led.ro: website, SEO, social media, campanii marketing digital. Livrare 4 zile lucrătoare.',
        'status'=>'draft','priority'=>'low','category'=>'audit',
        'brand'=>'wht','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'WHT-2025-ONLINE','offer_date'=>'2025-01-01','valid_until'=>'2025-01-31',
        'status'=>'draft','currency'=>'RON','vat_rate'=>0,'brand'=>'wht',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Audit de prezență online și marketing digital pentru P10Led.ro — evaluare website, SEO, social media și campanii. Perspectivă integrată: analiză tehnică, marketing digital și experiența utilizatorului.',
        'terms_text'=>"Durată realizare: 4 zile lucrătoare de la confirmarea comenzii și furnizarea informațiilor\nValabilitate ofertă: 15 zile calendaristice de la data transmiterii\nLivrabil: raport PDF structurat\nAnaliza realizată exclusiv de specialiști cu experiență, fără modele automate AI",
        'deliverables_text'=>"- Analiză website (structură, UX, viteză, mobil, mesaje comerciale, conversii)\n- Audit SEO (vizibilitate, cuvinte cheie, meta taguri, indexare, oportunități creștere organică)\n- Analiza prezenței social media (canale, coerență, conținut, interacțiune)\n- Audit campanii marketing digital (eficiența mesajelor, direcții promovare)\n- Raport PDF: probleme identificate, impact, recomandări concrete și prioritizate",
        'created_at'=>$now,
    ], [
        ['title'=>'Audit Complet Prezență Online și Marketing Digital','desc'=>'Audit website (UX, viteză, mobile, conversii), SEO (cuvinte cheie, indexare, meta), social media (canale active, conținut), campanii marketing digital. Raport PDF complet cu recomandări prioritizate. Livrare 4 zile lucrătoare.','unit'=>'serviciu','qty'=>1,'price'=>5000.00],
    ]);
    attachFile($pdo, $oid, 'Propunere audit prezenta online si marketing digital.pdf', 'Propunere audit prezență online');
    $log[] = "OK: Ofertă Audit Online";
} catch(Exception $e) { $log[] = "ERR AuditOnline: ".$e->getMessage(); }

// ── 15. DOSAR LICITAȚIE ADP (DOCUMENTE CONTRACT) ─────────────
try {
    $cid = insertClient($pdo, [
        'profile_id'=>1,'company_name'=>'ADP — Administrația Domeniului Public','city'=>'București','country'=>'România',
        'industry'=>'Administrație publică',
        'notes'=>'Dosar licitație achiziție 2026. Formulare + documentație completă.',
        'pipeline_status'=>'lead','estimated_value'=>0,'source'=>'manual','created_at'=>$now,
    ]);
    $log[] = "OK: ADP (ID=$cid)";

    $pid = insertProject($pdo, [
        'profile_id'=>1,'client_id'=>$cid,'name'=>'Licitație ADP 2026 — Securitate Cibernetică',
        'description'=>'Dosar licitație achiziție 2026: documentație, formulare, propunere tehnică.',
        'status'=>'draft','priority'=>'high','category'=>'audit',
        'brand'=>'wht','created_at'=>$now,
    ]);

    $oid = insertOffer($pdo, [
        'profile_id'=>1,'project_id'=>$pid,'client_id'=>$cid,
        'offer_number'=>'WHT-2026-ADP','offer_date'=>'2026-01-01','valid_until'=>'2026-02-01',
        'status'=>'draft','currency'=>'RON','vat_rate'=>0,'brand'=>'wht',
        'subtotal'=>0,'vat_amount'=>0,'total'=>0,
        'intro_text'=>'Dosar licitație securitate cibernetică ADP 2026.',
        'created_at'=>$now,
    ]);
    // Atașăm fișierele din DOCUMENTE CONTRACT
    $docFiles = [
        'DOCUMENTE CONTRACT/Documentatie de achizitie 2026 ADP.docx' => 'Documentație achiziție 2026',
        'DOCUMENTE CONTRACT/propunere tehnica.docx' => 'Propunere tehnică',
        'DOCUMENTE CONTRACT/Formular 1.docx' => 'Formular 1',
        'DOCUMENTE CONTRACT/Formular 2.docx' => 'Formular 2',
        'DOCUMENTE CONTRACT/Formular 3.docx' => 'Formular 3',
        'DOCUMENTE CONTRACT/Formular 4.docx' => 'Formular 4',
        'DOCUMENTE CONTRACT/Formular 5.docx' => 'Formular 5',
        'DOCUMENTE CONTRACT/Formular 6.docx' => 'Formular 6',
        'DOCUMENTE CONTRACT/Formular 8.docx' => 'Formular 8',
    ];
    foreach ($docFiles as $path => $desc) {
        $fname = basename($path);
        $full = __DIR__.'/../oferte/'.$path;
        $size = file_exists($full) ? filesize($full) : 0;
        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $pdo->prepare("INSERT INTO offer_attachments (offer_id,file_name,file_path,file_size,file_type,description,uploaded_at) VALUES (?,?,?,?,?,?,?)")
            ->execute([$oid,$fname,'oferte/'.$path,$size,$mime,$desc,$now]);
    }
    $log[] = "OK: Ofertă + dosar ADP";
} catch(Exception $e) { $log[] = "ERR ADP: ".$e->getMessage(); }

// ══════════════════════════════════════════════════════════════
// STEP 3 — CONTRACT VOLUNTARIAT TEMPLATE (fara client specific)
// ══════════════════════════════════════════════════════════════
try {
    // Atasam templateul la primul client existent
    $firstClient = $pdo->query("SELECT id FROM clients WHERE profile_id=1 LIMIT 1")->fetchColumn();
    if ($firstClient) {
        insertDoc($pdo, 1, $firstClient, null, 'voluntariat', 'VOLUNTARIAT-TPL',
            '[TEMPLATE] Contract de Voluntariat CyberShield',
            '<p>Template contract voluntariat — completează {client_name}, {client_address}, {doc_number}, {date}.</p>',
            'draft', null, null, 'Template 8 capitole complet (Legea 78/2014).'
        );
        $log[] = "OK: Template Contract Voluntariat";
    }
} catch(Exception $e) { $log[] = "ERR VoluntariatTpl: ".$e->getMessage(); }

// ══════════════════════════════════════════════════════════════
// OUTPUT
// ══════════════════════════════════════════════════════════════
$errs = array_filter($log, fn($l) => str_starts_with($l,'ERR'));
echo "<!DOCTYPE html><html><body style='font-family:monospace;padding:30px;background:#080c18;color:#4ade80;max-width:960px;'>";
echo "<h2 style='color:#f0f2f8;margin-bottom:20px;'>CyberCRM Reimport FULL — Rezultat</h2><ul style='list-style:none;padding:0;'>";
foreach ($log as $l) {
    $c = str_starts_with($l,'ERR') ? '#f87171' : (str_starts_with($l,'INFO') ? '#94a3b8' : '#4ade80');
    echo "<li style='margin:4px 0;padding:5px 8px;border-bottom:1px solid #1e293b;color:$c;'>$l</li>";
}
echo empty($errs)
    ? "<br><div style='background:#14532d;padding:16px;border-radius:8px;border-left:4px solid #4ade80;'><p style='color:#4ade80;font-weight:bold;font-size:14px;'>✅ Import complet cu succes!</p></div>"
    : "<br><div style='background:#7f1d1d;padding:16px;border-radius:8px;border-left:4px solid #f87171;'><p style='color:#f87171;font-weight:bold;'>⚠️ ".count($errs)." erori. Verifică mai sus.</p></div>";
echo "<br><div style='background:#1e293b;padding:14px;border-radius:8px;margin-top:12px;'><p style='color:#f87171;font-weight:bold;font-size:13px;'>ȘTERGE IMEDIAT după rulare: /public/reimport.php și /public/import_clients.php</p></div>";
echo "</body></html>";

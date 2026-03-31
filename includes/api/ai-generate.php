<?php
/**
 * AI Generate API — trimite prompt la Claude, parseaza JSON, creeaza tot in DB
 */
if (!Auth::check()) jsonResponse(['error' => 'Unauthorized'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST required'], 405);
Security::requireCSRF();

$profileId = Auth::profileId();
$profile = Auth::getProfile();
$prompt = trim($_POST['prompt'] ?? '');
$existingClientId = (int)($_POST['client_id'] ?? 0);
$existingProjectId = (int)($_POST['project_id'] ?? 0);

if (empty($prompt)) jsonResponse(['error' => 'Descrie proiectul!'], 400);

$vatRate = (float)($profile['default_vat_rate'] ?? 0);
$profileName = $profile['name'] ?? '';
$currency = $profile['default_currency'] ?? 'RON';

// Build system prompt for Claude
$systemPrompt = "Esti un asistent CRM specializat in securitate cibernetica si consultanta IT. Utilizatorul iti descrie un proiect/client si tu trebuie sa returnezi STRICT un JSON valid (fara markdown, fara ```json, fara alte caractere) cu urmatoarea structura exacta:

{
  \"client\": {
    \"company_name\": \"Denumirea firmei\",
    \"cui\": \"CUI/CIF\",
    \"reg_com\": \"Nr Reg Com\",
    \"address\": \"Adresa\",
    \"city\": \"Oras\",
    \"county\": \"Judet\",
    \"country\": \"Romania\",
    \"phone\": \"Telefon firma\",
    \"email\": \"Email firma\",
    \"website\": \"Website\",
    \"industry\": \"Industrie\",
    \"notes\": \"Note despre client\",
    \"pipeline_status\": \"offer_sent\",
    \"estimated_value\": 0
  },
  \"contact\": {
    \"full_name\": \"Nume Prenume\",
    \"position\": \"Functie\",
    \"email\": \"email@firma.ro\",
    \"phone\": \"+40 xxx xxx xxx\"
  },
  \"project\": {
    \"name\": \"Denumirea proiectului\",
    \"description\": \"Descriere detaliata a proiectului (2-4 fraze)\",
    \"category\": \"audit|pentest|training|workshop|consulting|development|other\",
    \"priority\": \"low|medium|high|critical\",
    \"estimated_budget\": 0,
    \"notes\": \"Note\"
  },
  \"services\": [
    {
      \"title\": \"Denumire serviciu\",
      \"description\": \"Descriere detaliata serviciu: ce include, metodologia aplicata, standardele folosite\",
      \"quantity\": 1,
      \"unit\": \"serviciu\",
      \"unit_price\": 0
    }
  ],
  \"offer\": {
    \"status\": \"draft\",
    \"intro_text\": \"Text introductiv complet (3-5 fraze): contextul clientului, de ce are nevoie de servicii, cum ii pot ajuta, valoarea adaugata\",
    \"methodology_text\": \"Metodologie detaliata cu standarde relevante (OWASP, NIST, ISO 27001, PTES, OSSTMM etc.) si abordarea tehnica concreta - minim 5 puncte cu - la inceput\",
    \"deliverables_text\": \"Lista completa de livrabile cu descriere - minim 4-6 puncte cu - la inceput, fiecare cu detalii despre ce contine\",
    \"terms_text\": \"Conditii comerciale detaliate in format Cheie: Valoare (cate un rand per conditie), incluzand: Valabilitate oferta, Termen de executie, Modalitate de plata, Avans, Deplasare, Garantii, Confidentialitate\"
  }
}

REGULI CRITICE:
- Preturile sunt FARA TVA. TVA-ul de $vatRate% se adauga automat de sistem.
- Moneda: $currency. Preturi rezonabile pentru piata din Romania.
- Profilul activ: $profileName (organizatie de securitate cibernetica)
- estimated_value = estimated_budget = suma totala servicii (fara TVA)
- intro_text: 3-5 fraze profesionale despre contextul clientului si valoarea serviciilor propuse
- methodology_text: minim 5 puncte tehnice cu standarde internationale relevante
- deliverables_text: minim 5 livrabile concrete cu descriere detaliata
- terms_text: minim 6 conditii comerciale in format 'Cheie: Valoare'
- Descrierile serviciilor trebuie sa fie detaliate si profesionale (2-3 fraze fiecare)
- Raspunde DOAR cu JSON valid, nimic altceva. Fara explicatii, fara markdown, fara comentarii.";

// Call Claude API
$apiPayload = json_encode([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 2000,
    'messages' => [
        ['role' => 'user', 'content' => $prompt]
    ],
    'system' => $systemPrompt,
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $apiPayload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . (defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : ''),
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
]);

$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$apiResponse) {
    $err = json_decode($apiResponse, true);
    jsonResponse(['error' => 'Claude API error (HTTP ' . $httpCode . ')', 'debug' => $err['error']['message'] ?? $apiResponse], 500);
}

$apiData = json_decode($apiResponse, true);
$content = '';
foreach ($apiData['content'] ?? [] as $block) {
    if ($block['type'] === 'text') $content .= $block['text'];
}

// Clean JSON (remove markdown fences if any)
$content = trim($content);
$content = preg_replace('/^```json\s*/i', '', $content);
$content = preg_replace('/\s*```$/i', '', $content);
$content = trim($content);

$parsed = json_decode($content, true);
if (!$parsed) {
    jsonResponse(['error' => 'Nu am putut parsa raspunsul AI', 'debug' => substr($content, 0, 500)], 500);
}

// ============ CREATE IN DB ============
DB::get()->exec("SET NAMES utf8mb4");

$result = ['success' => true];

// 1. Client
$clientId = $existingClientId;
if (!$clientId && isset($parsed['client'])) {
    $c = $parsed['client'];
    $clientId = DB::insert('clients', [
        'profile_id' => $profileId,
        'company_name' => $c['company_name'] ?? 'Client nou',
        'cui' => $c['cui'] ?? '',
        'reg_com' => $c['reg_com'] ?? '',
        'address' => $c['address'] ?? '',
        'city' => $c['city'] ?? '',
        'county' => $c['county'] ?? '',
        'country' => $c['country'] ?? 'Romania',
        'phone' => $c['phone'] ?? '',
        'email' => $c['email'] ?? '',
        'website' => $c['website'] ?? '',
        'industry' => $c['industry'] ?? '',
        'notes' => $c['notes'] ?? '',
        'source' => 'ai_assistant',
        'pipeline_status' => $c['pipeline_status'] ?? 'offer_sent',
        'estimated_value' => (float)($c['estimated_value'] ?? 0),
    ]);
    $result['client'] = ['id' => $clientId, 'name' => $c['company_name'] ?? 'Client nou'];
} elseif ($clientId) {
    $existing = DB::fetchOne("SELECT company_name FROM clients WHERE id = ?", [$clientId]);
    $result['client'] = ['id' => $clientId, 'name' => $existing['company_name'] ?? ''];
}

// 2. Contact
if (isset($parsed['contact']) && $clientId) {
    $ct = $parsed['contact'];
    if (!empty($ct['full_name'])) {
        DB::insert('client_contacts', [
            'client_id' => $clientId,
            'full_name' => $ct['full_name'],
            'position' => $ct['position'] ?? '',
            'email' => $ct['email'] ?? '',
            'phone' => $ct['phone'] ?? '',
            'is_primary' => 1,
        ]);
    }
}

// 3. Project
$projectId = $existingProjectId;
if (!$projectId && isset($parsed['project']) && $clientId) {
    $p = $parsed['project'];
    $projectId = DB::insert('projects', [
        'profile_id' => $profileId,
        'client_id' => $clientId,
        'name' => $p['name'] ?? 'Proiect nou',
        'description' => $p['description'] ?? '',
        'status' => 'active',
        'estimated_budget' => (float)($p['estimated_budget'] ?? 0),
        'priority' => $p['priority'] ?? 'medium',
        'category' => $p['category'] ?? 'other',
        'notes' => $p['notes'] ?? '',
    ]);
    $result['project'] = ['id' => $projectId, 'name' => $p['name'] ?? 'Proiect nou'];
} elseif ($projectId) {
    $existing = DB::fetchOne("SELECT name FROM projects WHERE id = ?", [$projectId]);
    $result['project'] = ['id' => $projectId, 'name' => $existing['name'] ?? ''];
}

// 4. Services
$subtotal = 0;
if (isset($parsed['services']) && $projectId) {
    foreach ($parsed['services'] as $i => $s) {
        $qty = (float)($s['quantity'] ?? 1);
        $price = (float)($s['unit_price'] ?? 0);
        DB::insert('services', [
            'project_id' => $projectId,
            'title' => $s['title'] ?? 'Serviciu',
            'description' => $s['description'] ?? '',
            'unit' => $s['unit'] ?? 'serviciu',
            'quantity' => $qty,
            'unit_price' => $price,
            'vat_rate' => $vatRate,
            'sort_order' => $i + 1,
        ]);
        $subtotal += $qty * $price;
    }
}

// 5. Offer
if (isset($parsed['offer']) && $projectId && $clientId) {
    $o = $parsed['offer'];
    $vatAmount = $subtotal * ($vatRate / 100);
    $total = $subtotal + $vatAmount;

    $offerNumber = generateOfferNumber($profileId);
    $offerId = DB::insert('offers', [
        'profile_id' => $profileId,
        'project_id' => $projectId,
        'client_id' => $clientId,
        'offer_number' => $offerNumber,
        'offer_date' => date('Y-m-d'),
        'valid_until' => date('Y-m-d', strtotime('+30 days')),
        'status' => $o['status'] ?? 'draft',
        'currency' => $currency,
        'vat_rate' => $vatRate,
        'subtotal' => $subtotal,
        'vat_amount' => $vatAmount,
        'total' => $total,
        'intro_text' => $o['intro_text'] ?? '',
        'methodology_text' => $o['methodology_text'] ?? '',
        'deliverables_text' => $o['deliverables_text'] ?? '',
        'terms_text' => $o['terms_text'] ?? '',
        'sent_at' => ($o['status'] ?? '') === 'sent' ? date('Y-m-d H:i:s') : null,
    ]);

    // Offer items from services
    if (isset($parsed['services'])) {
        foreach ($parsed['services'] as $i => $s) {
            $qty = (float)($s['quantity'] ?? 1);
            $price = (float)($s['unit_price'] ?? 0);
            DB::insert('offer_items', [
                'offer_id' => $offerId,
                'title' => $s['title'] ?? 'Serviciu',
                'description' => $s['description'] ?? '',
                'unit' => $s['unit'] ?? 'serviciu',
                'quantity' => $qty,
                'unit_price' => $price,
                'total_price' => $qty * $price,
                'sort_order' => $i + 1,
            ]);
        }
    }

    $result['offer'] = [
        'id' => $offerId,
        'number' => $offerNumber,
        'total' => number_format($total, 2, ',', '.') . ' ' . $currency,
    ];
}

jsonResponse($result);

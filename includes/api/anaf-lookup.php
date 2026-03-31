<?php
$cui = preg_replace('/[^0-9]/', '', $_GET['cui'] ?? '');
if (empty($cui)) jsonResponse(['success' => false, 'error' => 'CUI lipsă'], 400);
if (!Security::rateLimit('anaf_' . Security::getIP(), 10, 60)) jsonResponse(['success' => false, 'error' => 'Rate limited'], 429);
$data = lookupCUI($cui);
jsonResponse($data ? ['success' => true, 'data' => $data] : ['success' => false, 'error' => 'CUI negăsit']);

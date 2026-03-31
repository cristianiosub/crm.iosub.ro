<?php
/**
 * API: Get service catalog for current profile
 */
if (!Auth::check()) jsonResponse(['error' => 'Unauthorized'], 401);
$profileId = Auth::profileId();
$services = DB::fetchAll("SELECT * FROM service_catalog WHERE profile_id = ? AND is_active = 1 ORDER BY category, sort_order", [$profileId]);
jsonResponse(['success' => true, 'services' => $services]);

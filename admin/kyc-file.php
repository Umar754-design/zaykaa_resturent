<?php
// ===================================================
// Serves a private rider KYC document — admins only.
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rider-kyc.php';

require_role(['admin']);
ensure_rider_kyc_schema();

$stmt = $pdo->prepare("SELECT * FROM rider_kyc_documents WHERE id = ?");
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$doc = $stmt->fetch();

$path = $doc ? KYC_STORAGE_DIR . '/' . basename($doc['file_name']) : '';
if (!$doc || !is_file($path) || !isset(KYC_ALLOWED_MIME[$doc['mime_type']])) {
    http_response_code(404);
    exit('Document not found.');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $doc['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $doc['doc_type'] . '.' . KYC_ALLOWED_MIME[$doc['mime_type']]) . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
readfile($path);

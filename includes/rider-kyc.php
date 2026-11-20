<?php
// ===================================================
// Rider KYC documents — schema, rules, validation & private storage
// Documents live in /storage/kyc (blocked from the web) and are only
// served to admins through admin/kyc-file.php.
// ===================================================
require_once __DIR__ . '/functions.php';

define('KYC_STORAGE_DIR', dirname(__DIR__) . '/storage/kyc');
define('KYC_MAX_FILE_BYTES', 5 * 1024 * 1024);
define('KYC_ALLOWED_MIME', [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'application/pdf' => 'pdf',
]);

/**
 * Document slots shown on the KYC step. 'required' => must be uploaded before submitting.
 */
function kyc_document_types() {
    return [
        'aadhaar_front' => ['label' => 'Aadhaar Card — Front', 'group' => 'aadhaar', 'required' => true],
        'aadhaar_back'  => ['label' => 'Aadhaar Card — Back', 'group' => 'aadhaar', 'required' => true],
        'pan'           => ['label' => 'PAN Card', 'group' => 'pan', 'required' => true],
        'dl_front'      => ['label' => 'Driving Licence — Front', 'group' => 'dl', 'required' => true],
        'dl_back'       => ['label' => 'Driving Licence — Back', 'group' => 'dl', 'required' => false],
        'bank_proof'    => ['label' => 'Bank Passbook / Cancelled Cheque', 'group' => 'bank', 'required' => true],
        'vehicle_rc'    => ['label' => 'Vehicle RC (Registration Certificate)', 'group' => 'vehicle', 'required' => false],
        'qualification' => ['label' => 'Highest Qualification Certificate', 'group' => 'qualification', 'required' => false],
    ];
}

function kyc_qualification_options() {
    return ['Below 10th', '10th Pass', '12th Pass', 'Diploma / ITI', 'Graduate', 'Post Graduate'];
}

/**
 * Creates the KYC table and columns on first use.
 */
function ensure_rider_kyc_schema() {
    global $pdo;
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `rider_kyc_documents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `rider_id` INT NOT NULL,
        `doc_type` VARCHAR(30) NOT NULL,
        `file_name` VARCHAR(120) NOT NULL,
        `original_name` VARCHAR(255) DEFAULT NULL,
        `mime_type` VARCHAR(60) NOT NULL,
        `file_size` INT NOT NULL,
        `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        `remarks` VARCHAR(255) DEFAULT NULL,
        `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_rider_doc` (`rider_id`, `doc_type`),
        INDEX `idx_rider` (`rider_id`)
    ) ENGINE=InnoDB");

    $columns = [
        'aadhaar_last4'       => "VARCHAR(4) DEFAULT NULL",
        'pan_number'          => "VARCHAR(10) DEFAULT NULL",
        'dl_expiry'           => "DATE DEFAULT NULL",
        'bank_account_holder' => "VARCHAR(120) DEFAULT NULL",
        'bank_account_number' => "VARCHAR(20) DEFAULT NULL",
        'bank_ifsc'           => "VARCHAR(11) DEFAULT NULL",
        'qualification'       => "VARCHAR(40) DEFAULT NULL",
        'kyc_submitted_at'    => "DATETIME DEFAULT NULL",
        'kyc_remarks'         => "VARCHAR(255) DEFAULT NULL",
    ];
    $existing = $pdo->query("SHOW COLUMNS FROM `delivery_partners`")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $col => $def) {
        if (!in_array($col, $existing, true)) {
            $pdo->exec("ALTER TABLE `delivery_partners` ADD COLUMN `$col` $def");
        }
    }
    $done = true;
}

/** Aadhaar numbers carry a Verhoeff checksum — catches typos. */
function kyc_valid_aadhaar($number) {
    if (!preg_match('/^[2-9][0-9]{11}$/', $number)) {
        return false;
    }
    $d = [
        [0,1,2,3,4,5,6,7,8,9],[1,2,3,4,0,6,7,8,9,5],[2,3,4,0,1,7,8,9,5,6],[3,4,0,1,2,8,9,5,6,7],[4,0,1,2,3,9,5,6,7,8],
        [5,9,8,7,6,0,4,3,2,1],[6,5,9,8,7,1,0,4,3,2],[7,6,5,9,8,2,1,0,4,3],[8,7,6,5,9,3,2,1,0,4],[9,8,7,6,5,4,3,2,1,0],
    ];
    $p = [
        [0,1,2,3,4,5,6,7,8,9],[1,5,7,6,2,8,3,0,9,4],[5,8,0,3,7,9,6,1,4,2],[8,9,1,6,0,4,3,5,2,7],
        [9,4,5,3,1,2,6,8,7,0],[4,2,8,6,5,7,3,9,0,1],[2,7,9,3,8,0,6,4,1,5],[7,0,4,6,9,1,3,2,5,8],
    ];
    $c = 0;
    $digits = array_reverse(str_split($number));
    foreach ($digits as $i => $digit) {
        $c = $d[$c][$p[$i % 8][(int) $digit]];
    }
    return $c === 0;
}

function kyc_valid_pan($pan) {
    return (bool) preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan);
}

function kyc_valid_ifsc($ifsc) {
    return (bool) preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc);
}

function kyc_mask_account($number) {
    $number = (string) $number;
    return strlen($number) > 4 ? str_repeat('•', strlen($number) - 4) . substr($number, -4) : $number;
}

/**
 * Validates and stores one uploaded file. Returns [ok, error, row].
 */
function kyc_store_upload($riderId, $docType, $file) {
    global $pdo;
    if (!isset(kyc_document_types()[$docType])) {
        return [false, 'Unknown document type.', null];
    }
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [true, null, null]; // nothing uploaded for this slot
    }
    $label = kyc_document_types()[$docType]['label'];
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [false, "$label: upload failed. Please try again.", null];
    }
    if ($file['size'] > KYC_MAX_FILE_BYTES) {
        return [false, "$label: file is larger than 5 MB.", null];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(KYC_ALLOWED_MIME[$mime])) {
        return [false, "$label: only JPG, PNG, WEBP or PDF files are allowed.", null];
    }
    if (strpos($mime, 'image/') === 0 && !@getimagesize($file['tmp_name'])) {
        return [false, "$label: the image file is damaged.", null];
    }

    if (!is_dir(KYC_STORAGE_DIR)) {
        mkdir(KYC_STORAGE_DIR, 0750, true);
    }
    $name = 'r' . (int) $riderId . '_' . $docType . '_' . bin2hex(random_bytes(8)) . '.' . KYC_ALLOWED_MIME[$mime];
    if (!move_uploaded_file($file['tmp_name'], KYC_STORAGE_DIR . '/' . $name)) {
        return [false, "$label: could not be saved.", null];
    }

    // Replace any earlier file for this slot
    $old = $pdo->prepare("SELECT file_name FROM rider_kyc_documents WHERE rider_id = ? AND doc_type = ?");
    $old->execute([$riderId, $docType]);
    if ($oldName = $old->fetchColumn()) {
        @unlink(KYC_STORAGE_DIR . '/' . basename($oldName));
    }
    $pdo->prepare("INSERT INTO rider_kyc_documents (rider_id, doc_type, file_name, original_name, mime_type, file_size, status, remarks, uploaded_at)
                   VALUES (?, ?, ?, ?, ?, ?, 'pending', NULL, NOW())
                   ON DUPLICATE KEY UPDATE file_name = VALUES(file_name), original_name = VALUES(original_name),
                       mime_type = VALUES(mime_type), file_size = VALUES(file_size), status = 'pending', remarks = NULL, uploaded_at = NOW()")
        ->execute([$riderId, $docType, $name, substr((string) $file['name'], 0, 255), $mime, (int) $file['size']]);

    return [true, null, ['doc_type' => $docType, 'file_name' => $name]];
}

function kyc_documents_for($riderId) {
    global $pdo;
    $s = $pdo->prepare("SELECT * FROM rider_kyc_documents WHERE rider_id = ?");
    $s->execute([$riderId]);
    $out = [];
    foreach ($s->fetchAll() as $row) {
        $out[$row['doc_type']] = $row;
    }
    return $out;
}

/** Required slots that still have no file. */
function kyc_missing_documents($riderId) {
    $have = kyc_documents_for($riderId);
    $missing = [];
    foreach (kyc_document_types() as $type => $def) {
        if ($def['required'] && (empty($have[$type]) || $have[$type]['status'] === 'rejected')) {
            $missing[] = $def['label'];
        }
    }
    return $missing;
}

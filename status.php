<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$uuid = trim($_GET['uuid'] ?? '');

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing UUID.']);
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM submissions WHERE uuid = ? LIMIT 1");
$stmt->execute([$uuid]);
$sub  = $stmt->fetch();

if (!$sub) {
    http_response_code(404);
    echo json_encode(['error' => 'Submission not found.']);
    exit;
}

// Fetch page results
$pStmt = $pdo->prepare("SELECT * FROM page_results WHERE submission_id = ? ORDER BY page_number ASC");
$pStmt->execute([$sub['id']]);
$pageRows = $pStmt->fetchAll();

$pages = array_map(function($row) {
    return [
        'page'   => $row['page_number'],
        'type'   => $row['page_type'],
        'valid'  => (bool) $row['is_valid'],
        'issues' => json_decode($row['issues'], true) ?? [],
    ];
}, $pageRows);

echo json_encode([
    'uuid'         => $sub['uuid'],
    'filename'     => $sub['original_filename'],
    'total_pages'  => (int) $sub['total_pages'],
    'status'       => $sub['status'],
    'missing'      => json_decode($sub['missing_pages'], true) ?? [],
    'metadata'     => json_decode($sub['metadata'], true) ?? null,
    'pages'        => $pages,
    'uploaded_at'  => $sub['uploaded_at'],
    'processed_at' => $sub['processed_at'],
]);

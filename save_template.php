<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['fields']) || !is_array($input['fields'])) {
    echo json_encode(['error' => 'Invalid data format.']);
    exit;
}

$pdo = getDB();

try {
    $pdo->beginTransaction();

    // Clear existing templates for the given page (if any)
    // For now, we assume page 1 but you can expand this
    $page = isset($input['page']) ? (int)$input['page'] : 1;
    
    $stmtDelete = $pdo->prepare("DELETE FROM field_templates WHERE page_number = ?");
    $stmtDelete->execute([$page]);

    $stmtInsert = $pdo->prepare("
        INSERT INTO field_templates (field_key, label, x1, y1, x2, y2, page_number)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($input['fields'] as $f) {
        $stmtInsert->execute([
            $f['key'],
            $f['label'],
            $f['x1'],
            $f['y1'],
            $f['x2'],
            $f['y2'],
            $page
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

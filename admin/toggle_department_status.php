<?php
include __DIR__ . '/../database/database.php';

if (empty($conn)) {
    header('Location: departments.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: departments.php');
    exit;
}

try {
    $stmt = $conn->prepare('SELECT status FROM department WHERE department_id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        header('Location: departments.php');
        exit;
    }

    $current = strtolower(trim($row['status'] ?? ''));
    $new = ($current === 'active') ? 'inactive' : 'active';

    $update = $conn->prepare('UPDATE department SET status = :status WHERE department_id = :id');
    $update->execute([':status' => $new, ':id' => $id]);

} catch (Exception $e) {
    error_log('toggle_department_status error: ' . $e->getMessage());
}

header('Location: departments.php');
exit;

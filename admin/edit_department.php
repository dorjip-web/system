<?php
include __DIR__ . '/../database/database.php';

if (empty($conn)) {
    echo '<p>Database connection not available.</p>';
    exit;
}

$department_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($department_id <= 0) {
    echo '<p>Invalid department id.</p>';
    exit;
}

// Fetch existing data
try {
    $stmt = $conn->prepare('SELECT * FROM department WHERE department_id = :id LIMIT 1');
    $stmt->execute([':id' => $department_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('edit_department.php fetch: ' . $e->getMessage());
    $department = false;
}

if (!$department) {
    echo '<p>Department not found.</p>';
    exit;
}

$error = '';
if (isset($_POST['submit'])) {
    $department_name = trim($_POST['department_name'] ?? '');
    $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

    if ($department_name === '') {
        $error = 'Department name is required.';
    } else {
        try {
            $update = $conn->prepare('UPDATE department SET department_name = :name, status = :status WHERE department_id = :id');
            $update->execute([':name' => $department_name, ':status' => $status, ':id' => $department_id]);
            if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'department_id' => $department_id, 'department_name' => $department_name, 'status' => $status]);
                exit;
            }
            header('Location: departments.php');
            exit;
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
            error_log('edit_department.php update: ' . $e->getMessage());
        }
    }
}

// Provide JSON response for AJAX GET requests to fetch department data
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    if ($department) {
        echo json_encode(['success' => true, 'department' => ['department_id' => (int)$department['department_id'], 'department_name' => $department['department_name'], 'status' => $department['status']]]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Department not found']);
    }
    exit;
}
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit Department</title>
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body style="padding:18px;font-family:Inter,Arial,Helvetica,sans-serif">

<h2>Edit Department</h2>

<?php if ($error): ?>
    <div style="color:#d0342a;margin-bottom:12px"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">

    <label>Department Name:</label><br>
    <input type="text" name="department_name" value="<?php echo htmlspecialchars($department['department_name'] ?? ''); ?>" required style="padding:8px;width:320px"><br><br>

    <label>Status:</label><br>
    <select name="status" style="padding:8px">
        <option value="active" <?php if (($department['status'] ?? '') === 'active') echo 'selected'; ?>>Active</option>
        <option value="inactive" <?php if (($department['status'] ?? '') === 'inactive') echo 'selected'; ?>>Inactive</option>
    </select>

    <br><br>

    <button type="submit" name="submit" style="padding:8px 14px">Update Department</button>

</form>

</body>
</html>

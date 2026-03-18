<?php
include __DIR__ . '/../../database/database.php';

if (empty($conn)) {
    echo '<p>Database connection not available.</p>';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$name = "";
$status = "active";
$error = '';

if ($id) {
    try {
        $stmt = $conn->prepare('SELECT * FROM permission WHERE permission_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $name = $data['permission_name'];
            $status = $data['status'];
        } else {
            echo '<p>Permission not found.</p>';
            exit;
        }
    } catch (Exception $e) {
        error_log('permission_form fetch error: ' . $e->getMessage());
        echo '<p>Unable to load permission.</p>';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['permission_name'] ?? '');
    $status = (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'inactive' : 'active';

    if ($name === '') {
        $error = 'Permission name is required.';
    } else {
        try {
            if ($id) {
                $update = $conn->prepare('UPDATE permission SET permission_name = :name, status = :status WHERE permission_id = :id');
                $update->execute([':name' => $name, ':status' => $status, ':id' => $id]);
            } else {
                $insert = $conn->prepare('INSERT INTO permission (permission_name, status) VALUES (:name, :status)');
                $insert->execute([':name' => $name, ':status' => $status]);
            }
            header('Location: /attendanceleave/admin/roles/permission.php');
            exit;
        } catch (Exception $e) {
            error_log('permission_form save error: ' . $e->getMessage());
            $error = 'Database error. Try again.';
        }
    }
}
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo $id ? 'Edit' : 'Add'; ?> Permission</title>
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:Inter,Arial,Helvetica,sans-serif;padding:18px}</style>
</head>
<body>

<h2><?php echo $id ? 'Edit' : 'Add'; ?> Permission</h2>

<?php if ($error): ?>
    <div style="color:#d0342a;margin-bottom:12px"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
    <div style="margin-bottom:8px">
        <label>Permission Name</label><br>
        <input type="text" name="permission_name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Permission Name" required style="padding:8px;width:320px">
    </div>

    <div style="margin-bottom:12px">
        <label>Status</label><br>
        <select name="status" style="padding:8px">
            <option value="active" <?php if ($status === 'active') echo 'selected'; ?>>Active</option>
            <option value="inactive" <?php if ($status === 'inactive') echo 'selected'; ?>>Inactive</option>
        </select>
    </div>

    <button type="submit" name="save" class="btn"><?php echo $id ? 'Update' : 'Save'; ?></button>
    <a href="/attendanceleave/admin/roles/permission.php" class="btn">Cancel</a>
</form>

</body>
</html>

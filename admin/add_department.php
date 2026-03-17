<?php
include __DIR__ . '/../database/database.php';

$error = '';
if (isset($_POST['submit'])) {
    $department_name = trim($_POST['department_name'] ?? '');
    if ($department_name === '') {
        $error = 'Department name is required.';
    } else {
        try {
            $stmt = $conn->prepare('INSERT INTO departments (department_name, status) VALUES (:name, :status)');
            $stmt->execute([':name' => $department_name, ':status' => 'active']);
            header('Location: departments.php');
            exit;
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Add Department</title>
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body style="padding:18px;font-family:Inter,Arial,Helvetica,sans-serif">

<h2>Add Department</h2>

<?php if ($error): ?>
    <div style="color:#d0342a;margin-bottom:12px"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
    <label>Department Name:</label><br>
    <input type="text" name="department_name" required style="padding:8px;width:320px"><br><br>
    <button type="submit" name="submit" style="padding:8px 14px">Add Department</button>
</form>

</body>
</html>

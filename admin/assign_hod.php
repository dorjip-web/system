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

// Fetch department info
try {
    $deptStmt = $conn->prepare('SELECT * FROM department WHERE department_id = :id LIMIT 1');
    $deptStmt->execute([':id' => $department_id]);
    $department = $deptStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('assign_hod.php dept fetch: ' . $e->getMessage());
    $department = false;
}

if (!$department) {
    echo '<p>Department not found.</p>';
    exit;
}

// Fetch current HoD mapping for this department (if any)
try {
    $currStmt = $conn->prepare('SELECT employee_id FROM department_hod WHERE department_id = :dept LIMIT 1');
    $currStmt->execute([':dept' => $department_id]);
    $currRow = $currStmt->fetch(PDO::FETCH_ASSOC);
    $current_hod_id = $currRow ? (int)$currRow['employee_id'] : 0;
} catch (Exception $e) {
    error_log('assign_hod.php current hod fetch: ' . $e->getMessage());
    $current_hod_id = 0;
}

// Fetch employees (all)
try {
    $empStmt = $conn->query("SELECT employee_id, employee_name FROM tab1 ORDER BY employee_name ASC");
    $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('assign_hod.php employees fetch: ' . $e->getMessage());
    $employees = [];
}

// Handle form submit
$error = '';
if (isset($_POST['submit'])) {
    $employee_id = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
    if ($employee_id <= 0) {
        $error = 'Please select a valid HoD.';
    } else {
        try {
            // Check if mapping exists
            $checkStmt = $conn->prepare('SELECT COUNT(*) FROM department_hod WHERE department_id = :dept');
            $checkStmt->execute([':dept' => $department_id]);
            $exists = $checkStmt->fetchColumn() > 0;

            if ($exists) {
                $update = $conn->prepare('UPDATE department_hod SET employee_id = :emp WHERE department_id = :dept');
                $update->execute([':emp' => $employee_id, ':dept' => $department_id]);
            } else {
                $insert = $conn->prepare('INSERT INTO department_hod (department_id, employee_id) VALUES (:dept, :emp)');
                $insert->execute([':dept' => $department_id, ':emp' => $employee_id]);
            }

            header('Location: departments.php');
            exit;
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
            error_log('assign_hod.php submit: ' . $e->getMessage());
        }
    }
}
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Assign HoD</title>
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body style="padding:18px;font-family:Inter,Arial,Helvetica,sans-serif">

<h2>Assign HoD to <?php echo htmlspecialchars($department['department_name']); ?></h2>

<?php if ($error): ?>
    <div style="color:#d0342a;margin-bottom:12px"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
    <label>Select HoD:</label><br>
    <select name="employee_id" required style="padding:8px;width:320px">
        <option value="">-- Select HoD --</option>
        <?php foreach ($employees as $row): ?>
            <option value="<?php echo (int)$row['employee_id']; ?>" <?php echo ((int)$row['employee_id'] === $current_hod_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($row['employee_name']); ?></option>
        <?php endforeach; ?>
    </select>

    <br><br>
    <button type="submit" name="submit" style="padding:8px 14px">Assign HoD</button>
</form>

</body>
</html>

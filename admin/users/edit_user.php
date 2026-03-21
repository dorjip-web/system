<?php
include __DIR__ . '/../../database/database.php';

if (empty($conn)) {
    echo '<p>Database connection not available.</p>';
    exit;
}

$employee_id = $_GET['id'] ?? null;
if (!$employee_id) {
    header('Location: users.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM tab1 WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo '<p>User not found.</p>';
    exit;
}

if (isset($_POST['update'])){

    $eid = $_POST['eid'];
    $name = $_POST['employee_name'];
    $username = $_POST['username'];
    $designation = $_POST['designation'];
    $department_id = $_POST['department_id'] ?? null;
    $role_id = $_POST['role_id'] ?? null;
    $status = $_POST['status'];
    $id = $_POST['employee_id'];

    $sql = "UPDATE tab1 SET eid = ?, employee_name = ?, username = ?, designation = ?, department_id = ?, role_id = ?, status = ? WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$eid, $name, $username, $designation, $department_id, $role_id, $status, $id]);

    header("Location: users.php");
    exit;
}
?>

<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$username = 'NTMH';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin - Edit User</title>
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="profile">
            <div class="avatar">NT</div>
            <div class="username"><?php echo htmlspecialchars($username); ?></div>
        </div>
        <nav class="menu">
            <a href="../../admin_dashboard.php">Admin Dashboard</a>
            <div class="menu-item">
                <a href="users.php" <?php if(basename($_SERVER['PHP_SELF']) === 'users.php') echo 'class="active"'; ?>>User Management</a>
                <div class="submenu">
                    <a href="add_user.php">➕ Add New User</a>
                </div>
            </div>
            <a href="../departments/departments.php" <?php if(basename($_SERVER['PHP_SELF']) === 'departments.php') echo 'class="active"'; ?>>Department &amp; HoD Management</a>
            <a href="../roles/roles.php" <?php if(basename($_SERVER['PHP_SELF']) === 'roles.php') echo 'class="active"'; ?>>Roles & Permissions</a>
            <a href="../leave_types/index.php" <?php if(basename($_SERVER['PHP_SELF']) === 'index.php' && basename(dirname($_SERVER['PHP_SELF'])) === 'leave_types') echo 'class="active"'; ?>>Leave Types</a>
            <a href="../leave_balances/leave_balance.php" <?php if(basename($_SERVER['PHP_SELF']) === 'leave_balance.php') echo 'class="active"'; ?>>Leave Balance</a>
            <a href="/attendanceleave/attendance_logs.php" <?php if(basename($_SERVER['PHP_SELF']) === 'attendance_logs.php') echo 'class="active"'; ?>>Attendance Logs</a>
            <a href="../leave_records.php" <?php if(basename($_SERVER['PHP_SELF']) === 'leave_records.php') echo 'class="active"'; ?>>Leave Records</a>
            <a href="../reports.php" <?php if(basename($_SERVER['PHP_SELF']) === 'reports.php') echo 'class="active"'; ?>>Reports</a>
            <a href="../settings.php" <?php if(basename($_SERVER['PHP_SELF']) === 'settings.php') echo 'class="active"'; ?>>Settings</a>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="search"> <input placeholder="Search..."> </div>
            <div class="logout"><a href="../../login.php">Logout</a></div>
        </header>

        <section>

<!-- page content starts here -->

<div style="padding:18px">

<h2>Edit User</h2>

<form method="post">
    <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($user['employee_id'] ?? ''); ?>">

    <div class="leave-form" style="padding:0">
        <div class="row-grid-3">
            <div class="col">
                <label>EID</label>
                <input type="text" name="eid" value="<?php echo htmlspecialchars($user['eid'] ?? ''); ?>" required class="form-control">
            </div>

            <div class="col">
                <label>Employee Name</label>
                <input type="text" name="employee_name" value="<?php echo htmlspecialchars($user['employee_name'] ?? $user['name'] ?? ''); ?>" required class="form-control">
            </div>

            <div class="col">
                <label>Designation</label>
                <input type="text" name="designation" value="<?php echo htmlspecialchars($user['designation'] ?? ''); ?>" required class="form-control">
            </div>

            <div class="col">
                <label>Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" class="form-control">
            </div>

            <div class="col">
                <label>Password (leave blank to keep current)</label>
                <input type="password" name="password" class="form-control">
            </div>

            <div class="col">
                <label>Department</label>
                <select name="department_id" class="form-control">
                    <option value="1" <?php if (($user['department_id'] ?? '') == '1') echo 'selected'; ?>>Administration</option>
                    <option value="2" <?php if (($user['department_id'] ?? '') == '2') echo 'selected'; ?>>Acupuncture</option>
                    <option value="3" <?php if (($user['department_id'] ?? '') == '3') echo 'selected'; ?>>IPD</option>
                    <option value="4" <?php if (($user['department_id'] ?? '') == '4') echo 'selected'; ?>>Jamched</option>
                    <option value="5" <?php if (($user['department_id'] ?? '') == '5') echo 'selected'; ?>>OPD</option>
                    <option value="6" <?php if (($user['department_id'] ?? '') == '6') echo 'selected'; ?>>Tsubched</option>
                </select>
            </div>

            <div class="col">
                <label>Role</label>
                <select name="role_id" class="form-control">
                    <option value="1" <?php if (($user['role_id'] ?? '') == '1') echo 'selected'; ?>>Medical Superintendent</option>
                    <option value="2" <?php if (($user['role_id'] ?? '') == '2') echo 'selected'; ?>>HoD</option>
                    <option value="3" <?php if (($user['role_id'] ?? '') == '3') echo 'selected'; ?>>Employee</option>
                </select>
            </div>

            <div class="col">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="Active" <?php if (($user['status'] ?? '') === 'Active') echo 'selected'; ?>>Active</option>
                    <option value="Inactive" <?php if (($user['status'] ?? '') === 'Inactive') echo 'selected'; ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div style="margin-top:12px">
            <button type="submit" name="update" class="btn">Update User</button>
        </div>
    </div>

</form>

<br>

    
<!-- page content ends here -->

        </section>
    </main>
</div>
</body>
</html>

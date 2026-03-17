<?php
session_start();
require_once "database/database.php";

// Basic admin access check — adapt to your auth flow as needed
$role = $_SESSION['role'] ?? '';
$adminFlag = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) || (is_string($role) && strtolower($role) === 'admin');
if (!$adminFlag) {
    header('Location: login.php');
    exit;
}

$username = 'NTMH';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/dashboard.css">
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
            <a href="admin_dashboard.php" class="active">Admin Dashboard</a>
            <a href="admin/users/users.php">User Management</a>
            <a href="admin/departments/departments.php">Department &amp; HoD Management</a>
            <a href="roles_permissions.php">Roles & Permissions</a>
            <a href="leave_types.php">Leave Types</a>
            <a href="leave_balance.php">Leave Balance</a>
            <a href="attendance_logs.php">Attendance Logs</a>
            <a href="leave_records.php">Leave Records</a>
            <a href="reports.php">Reports</a>
            <a href="settings.php">Settings</a>
            <a href="admin_logout.php">Logout</a>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="search"> <input placeholder="Search..."> </div>
            <div class="logout"><a href="login.php">Logout</a></div>
        </header>

        <section class="grid">
            <div class="card">
                <h2>Admin Dashboard</h2>
                <p>Welcome, <?php echo htmlspecialchars($username); ?>. Use the links on the left to manage the system.</p>
            </div>

            <div class="card">
                <h3>Main Features</h3>
                <ul>
                    <li><strong>User & Role Management</strong> — add/edit/delete employees; assign roles (employee, HOD, MS, Admin).</li>
                    <li><strong>Department Management</strong> — add/edit/delete departments; map HODs to departments.</li>
                    <li><strong>Leave Type Management</strong> — add/edit/delete leave types.</li>
                    <li><strong>System-wide Leave Reports</strong> — generate by employee, department, role, month, year; export to PDF/Excel.</li>
                    <li><strong>Settings</strong> — application-wide configuration.</li>
                    <li><strong>Full Attendance Log</strong> — view and export attendance records.</li>
                </ul>
            </div>

            <div class="card">
                <h3>Quick Actions</h3>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
                    <a class="btn" href="admin/users/users.php">Manage Users</a>
                    <a class="btn" href="manage_departments.php">Manage Departments</a>
                    <a class="btn" href="manage_leave_types.php">Leave Types</a>
                    <a class="btn" href="reports.php">Reports</a>
                    <a class="btn" href="attendance_log.php">Attendance Log</a>
                    <a class="btn" href="settings.php">Settings</a>
                </div>
            </div>

            <div class="card">
                <h3>Notes</h3>
                <p>This page is a scaffold. Implement the backend pages linked above to enable the full features listed.</p>
            </div>
        </section>
    </main>
</div>
</body>
</html>

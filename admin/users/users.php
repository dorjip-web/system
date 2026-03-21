<?php
include __DIR__ . '/../../database/database.php';

if (empty($conn)) {
	echo '<p>Database connection not available.</p>';
	exit;
}

try {
	$sql = "SELECT 
		t.employee_id,
		t.eid,
		t.employee_name,
		t.designation,
		d.department_name,
		r.role_name,
		t.status
	FROM tab1 t
	LEFT JOIN department d ON t.department_id = d.department_id
	LEFT JOIN role r ON t.role_id = r.role_id";
	$stmt = $conn->query($sql);
	$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	echo '<p>Error fetching users: ' . htmlspecialchars($e->getMessage()) . '</p>';
	$users = [];
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
	<title>Admin - Users</title>
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

<h1>User Management</h1>

<?php if (count($users) === 0): ?>

<p>No users found.</p>

<?php else: ?>

<div class="leave-history">
	<div class="table-wrap">
		<table class="users">
			<thead>
				<tr>
					<th>Employee ID</th>
					<th>EID</th>
					<th>Employee Name</th>
					<th>Designation</th>
					<th>Department</th>
					<th>Role</th>
					<th>Status</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($users as $row): ?>
				<tr>
					<td><?php echo htmlspecialchars($row['employee_id']); ?></td>
					<td><?php echo htmlspecialchars($row['eid']); ?></td>
					<td><?php echo htmlspecialchars($row['employee_name']); ?></td>
					<td><?php echo htmlspecialchars($row['designation'] ?? ''); ?></td>
					<td><?php echo htmlspecialchars($row['department_name'] ?? ''); ?></td>
					<td><?php echo htmlspecialchars($row['role_name'] ?? ''); ?></td>
					<td>
						<?php
						$status = trim($row['status'] ?? '');
						$lower = strtolower($status);
						if ($lower === 'active') {
							echo '<span class="status-active">' . htmlspecialchars($status) . '</span>';
						} elseif ($lower === 'inactive') {
							echo '<span class="status-inactive">' . htmlspecialchars($status) . '</span>';
						} else {
							echo htmlspecialchars($status);
						}
						?>
					</td>
					<td>
						<a class="action-orange" href="edit_user.php?id=<?php echo $row['employee_id']; ?>">Edit</a> |
						<a class="action-orange" href="toggle_user_status.php?id=<?php echo $row['employee_id']; ?>">Toggle Status</a>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<?php endif; ?>

    
<!-- page content ends here -->

		</section>
	</main>
</div>
</body>
</html>

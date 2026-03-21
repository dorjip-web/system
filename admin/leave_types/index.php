<?php
include __DIR__ . '/../../database/database.php';

if (empty($conn)) {
	echo '<p>Database connection not available.</p>';
	exit;
}

// make sure a $pdo variable exists for PDO-style snippets
if (!isset($pdo) && isset($conn)) {
    $pdo = $conn;
}

if (isset($_POST['save'])) {
	$stmt = $pdo->prepare("INSERT INTO leave_type (leave_name, leave_code, description, max_per_year, status)
	VALUES (?, ?, ?, ?, ?)");
	$stmt->execute([
		$_POST['leave_name'] ?? null,
		$_POST['leave_code'] ?? null,
		$_POST['description'] ?? null,
		$_POST['max_per_year'] ?? null,
		$_POST['status'] ?? null
	]);

	header("Location: index.php");
	exit;
}

if (isset($_POST['update'])) {
	$stmt = $pdo->prepare("UPDATE leave_type SET leave_name=?, leave_code=?, description=?, max_per_year=?, status=? WHERE leave_type_id=?");
	$stmt->execute([
		$_POST['leave_name'] ?? null,
		$_POST['leave_code'] ?? null,
		$_POST['description'] ?? null,
		$_POST['max_per_year'] ?? null,
		$_POST['status'] ?? null,
		$_POST['id'] ?? null
	]);

	header("Location: index.php"); // 👈 back to ADD mode
	exit;
}

/* ===== TOGGLE STATUS ===== */
if (isset($_GET['toggle'])) {
	$id = (int)$_GET['toggle'];

	$stmt = $conn->prepare('SELECT status FROM leave_type WHERE leave_type_id = :id LIMIT 1');
	$stmt->execute([':id' => $id]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($row) {
		$newStatus = (strtolower($row['status']) === 'active') ? 'inactive' : 'active';
		$u = $conn->prepare('UPDATE leave_type SET status = :status WHERE leave_type_id = :id');
		$u->execute([':status' => $newStatus, ':id' => $id]);
	}

	header('Location: index.php');
	exit;
}

/* ===== EDIT MODE ===== */
$edit_data = null;
if (isset($_GET['edit'])) {
	$id = $_GET['edit'];

	// If the codebase uses $conn as the PDO instance, make $pdo point to it
	if (!isset($pdo) && isset($conn)) {
		$pdo = $conn;
	}

	$stmt = $pdo->prepare("SELECT * FROM leave_type WHERE leave_type_id = ?");
	$stmt->execute([$id]);
	$edit_data = $stmt->fetch();
}

// keep backward compatibility for templates using $editData
$editData = $edit_data;

if (session_status() === PHP_SESSION_NONE) session_start();
$username = 'NTMH';

// show form automatically when add or edit present in URL
$showForm = (isset($_GET['edit']) || isset($_GET['add']));
?>

<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Admin - Leave Types</title>
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
			<a href="../users/users.php">User Management</a>
			<a href="../departments/departments.php">Department &amp; HoD Management</a>
			<a href="../roles/roles.php">Roles & Permissions</a>
			<a href="index.php" <?php if(basename($_SERVER['PHP_SELF']) === 'index.php') echo 'class="active"'; ?>>Leave Types</a>
			<a href="../leave_balances/leave_balance.php">Leave Balance</a>
			<a href="/attendanceleave/attendance_logs.php">Attendance Logs</a>
			<a href="../leave_records.php">Leave Records</a>
			<a href="../reports.php">Reports</a>
			<a href="../settings.php">Settings</a>
		</nav>
	</aside>

	<main class="main">
		<header class="topbar">
			<div class="search"> <input placeholder="Search..."> </div>
			<div class="logout"><a href="../../login.php">Logout</a></div>
		</header>

		<section>

			<div style="padding:18px">

				<h1>Leave Types</h1>

				<a href="#" onclick="toggleForm();return false;">+ Add Leave Type</a>

				<div class="leave-form" id="formDiv" style="display:<?= ($showForm? 'block':'none') ?>; padding:18px">
					<h3><?= $edit_data ? 'Edit Leave Type' : 'Add Leave Type' ?></h3>

					<form method="POST" id="leaveForm">

					<input type="hidden" name="id" value="<?= $edit_data['leave_type_id'] ?? '' ?>">

					<div class="row-grid-4">
						<div class="col">
							<label>Leave Name</label>
							<input type="text" name="leave_name" class="form-control" placeholder="Leave Name" value="<?= htmlspecialchars($edit_data['leave_name'] ?? '') ?>">
						</div>

						<div class="col">
							<label>Leave Code</label>
							<input type="text" name="leave_code" class="form-control" placeholder="Leave Code" value="<?= htmlspecialchars($edit_data['leave_code'] ?? '') ?>">
						</div>

						<div class="col">
							<label>Description</label>
							<textarea name="description" class="form-control" placeholder="Enter description"><?= htmlspecialchars($edit_data['description'] ?? '') ?></textarea>
						</div>

						<div class="col">
							<label>Max Per Year</label>
							<input type="number" name="max_per_year" class="form-control" placeholder="Max Per Year" value="<?= htmlspecialchars($edit_data['max_per_year'] ?? '') ?>">
						</div>
					</div>

					<div style="margin-top:12px">
						<label>Status</label>
						<select name="status" class="form-control">
							<option value="active" <?= (isset($edit_data) && $edit_data['status']=='active') ? 'selected' : '' ?>>Active</option>
							<option value="inactive" <?= (isset($edit_data) && $edit_data['status']=='inactive') ? 'selected' : '' ?>>Inactive</option>
						</select>
					</div>

					<div style="margin-top:12px;display:flex;gap:12px;align-items:center">
						<button type="submit" name="<?= $edit_data ? 'update' : 'save' ?>" class="btn"><?= $edit_data ? 'Update' : 'Save' ?></button>
						<a href="index.php" class="btn-cancel">Cancel</a>
					</div>

					</form>
				</div>

				<div class="leave-history" style="margin-top:18px">
					<div class="table-wrap">
						<table class="users">
							<thead>
								<tr>
									<th>ID</th>
									<th>Name</th>
									<th>Code</th>
									<th>Description</th>
									<th>Max/Year</th>
									<th>Status</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$query = $conn->query("SELECT * FROM leave_type");
								while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
								?>
								<tr>
									<td><?= htmlspecialchars($row['leave_type_id']) ?></td>
									<td><?= htmlspecialchars($row['leave_name']) ?></td>
									<td><?= htmlspecialchars($row['leave_code']) ?></td>
									<td><?= $row['description'] ?></td>
									<td><?= htmlspecialchars($row['max_per_year']) ?></td>
									<td>
										<?php
										$s = strtolower(trim($row['status'] ?? ''));
										if ($s === 'active') echo '<span class="status-active">Active</span>'; else echo '<span class="status-inactive">Inactive</span>';
										?>
									</td>
									<td>
										<?php
										echo "<a href='?edit=" . (int)$row['leave_type_id'] . "'>Edit</a> | ";
										echo "<a href='?toggle=" . (int)$row['leave_type_id'] . "'>Toggle Status</a>";
										?>
									</td>
								</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				</div>

			</div>

		</section>
	</main>
</div>

<script>
function toggleForm(){
	let f = document.getElementById("formDiv");
	f.style.display = (f.style.display === "none") ? "block" : "none";
}

function showForm(){
	document.getElementById("formDiv").style.display = "block";
}

function cancelForm(){
	const form = document.getElementById('leaveForm');
	if (form) {
		form.reset();
		// clear non-hidden inputs/textarea values
		Array.from(form.querySelectorAll('input, textarea, select')).forEach(el => {
			if (el.type !== 'hidden') {
				if (el.tagName.toLowerCase() === 'select') el.selectedIndex = 0;
				else el.value = '';
			}
		});
	}

	// Remove edit/add from URL without reloading, but do not hide the form
	try {
		const url = new URL(window.location.href);
		url.searchParams.delete('edit');
		url.searchParams.delete('add');
		history.replaceState(null, '', url.pathname + url.search);
	} catch (e) {
		// ignore
	}
}
</script>
</body>
</html>


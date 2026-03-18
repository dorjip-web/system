<?php
include __DIR__ . '/../../database/database.php';

if (empty($conn)) {
	echo '<p>Database connection not available.</p>';
	exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role_name = "";
$status = "active";
$error = '';

if ($id) {
	try {
		$stmt = $conn->prepare('SELECT * FROM role WHERE role_id = :id LIMIT 1');
		$stmt->execute([':id' => $id]);
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($data) {
			$role_name = $data['role_name'];
			$status = $data['status'];
		} else {
			echo '<p>Role not found.</p>';
			exit;
		}
	} catch (Exception $e) {
		error_log('role_form fetch error: ' . $e->getMessage());
		echo '<p>Unable to load role.</p>';
		exit;
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$role_name = trim($_POST['role_name'] ?? '');
	$status = (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'inactive' : 'active';

	if ($role_name === '') {
		$error = 'Role name is required.';
	} else {
		try {
			if ($id) {
				$update = $conn->prepare('UPDATE role SET role_name = :name, status = :status WHERE role_id = :id');
				$update->execute([':name' => $role_name, ':status' => $status, ':id' => $id]);
			} else {
				$insert = $conn->prepare('INSERT INTO role (role_name, status) VALUES (:name, :status)');
				$insert->execute([':name' => $role_name, ':status' => $status]);
			}
			header('Location: /attendanceleave/admin/roles/roles.php');
			exit;
		} catch (Exception $e) {
			error_log('role_form save error: ' . $e->getMessage());
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
	<title><?php echo $id ? 'Edit' : 'Add'; ?> Role</title>
	<link rel="stylesheet" href="../../css/dashboard.css">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
	<style>body{font-family:Inter,Arial,Helvetica,sans-serif;padding:18px}</style>
</head>
<body>

<h2><?php echo $id ? 'Edit' : 'Add'; ?> Role</h2>

<?php if ($error): ?>
	<div style="color:#d0342a;margin-bottom:12px"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
	<div style="margin-bottom:8px">
		<label>Role Name</label><br>
		<input type="text" name="role_name" value="<?php echo htmlspecialchars($role_name); ?>" placeholder="Role Name" required style="padding:8px;width:320px">
	</div>

	<div style="margin-bottom:12px">
		<label>Status</label><br>
		<select name="status" style="padding:8px">
			<option value="active" <?php if ($status === 'active') echo 'selected'; ?>>Active</option>
			<option value="inactive" <?php if ($status === 'inactive') echo 'selected'; ?>>Inactive</option>
		</select>
	</div>

	<button type="submit" name="save" class="btn"><?php echo $id ? 'Update' : 'Save'; ?></button>
	<a href="/attendanceleave/admin/roles/roles.php" class="btn">Cancel</a>
</form>

</body>
</html>


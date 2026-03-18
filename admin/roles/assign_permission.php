<?php
include __DIR__ . '/../../database/database.php';

if (empty($conn)) {
	echo '<p>Database connection not available.</p>';
	return;
}

// roles
try {
	$rolesStmt = $conn->query("SELECT * FROM role WHERE status='active' ORDER BY role_name ASC");
	$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	error_log('assign_permission roles fetch: ' . $e->getMessage());
	$roles = [];
}

// selected role
$selected_role = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;

// permissions
try {
	$permStmt = $conn->query("SELECT * FROM permission WHERE status='active' ORDER BY permission_name ASC");
	$permissions = $permStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	error_log('assign_permission permissions fetch: ' . $e->getMessage());
	$permissions = [];
}

// assigned permissions
$assigned = [];
if ($selected_role) {
	try {
		$aStmt = $conn->prepare('SELECT permission_id FROM role_permission WHERE role_id = :rid');
		$aStmt->execute([':rid' => $selected_role]);
		$rows = $aStmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $r) $assigned[] = (int)$r['permission_id'];
	} catch (Exception $e) {
		error_log('assign_permission assigned fetch: ' . $e->getMessage());
		$assigned = [];
	}
}

// save
if (isset($_POST['save'])) {
	$role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
	$perms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? array_map('intval', $_POST['permissions']) : [];

	if ($role_id) {
		try {
			$conn->beginTransaction();
			$del = $conn->prepare('DELETE FROM role_permission WHERE role_id = :rid');
			$del->execute([':rid' => $role_id]);

			if (count($perms) > 0) {
				$ins = $conn->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (:rid, :pid)');
				foreach ($perms as $pid) {
					$ins->execute([':rid' => $role_id, ':pid' => $pid]);
				}
			}

			$conn->commit();
			$message = 'Updated!';
			// reload assigned for display
			$assigned = $perms;
		} catch (Exception $e) {
			$conn->rollBack();
			error_log('assign_permission save error: ' . $e->getMessage());
			$message = 'Save failed.';
		}
	} else {
		$message = 'Please select a role.';
	}
}
?>

<h2>Assign Permissions</h2>

<?php if (!empty($message)): ?>
	<div style="color:green;margin-bottom:8px"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<form method="POST">

<select name="role_id" required>
<option value="">Select Role</option>

<?php foreach ($roles as $r): ?>
	<option value="<?php echo (int)$r['role_id']; ?>" <?php if ($selected_role === (int)$r['role_id']) echo 'selected'; ?>>
		<?php echo htmlspecialchars($r['role_name']); ?>
	</option>
<?php endforeach; ?>

</select>

<button type="submit" name="load">Load Permissions</button>

<br><br>

<?php foreach ($permissions as $p): $pid = (int)$p['permission_id']; ?>
	<label>
		<input type="checkbox" name="permissions[]" value="<?php echo $pid; ?>" <?php if (in_array($pid, $assigned, true)) echo 'checked'; ?>>
		<?php echo htmlspecialchars($p['permission_name']); ?>
	</label><br>
<?php endforeach; ?>

<br>
<button name="save">Save</button>

</form>


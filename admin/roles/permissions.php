<?php
include __DIR__ . '/../../database/database.php';

if (empty($conn)) {
	echo '<p>Database connection not available.</p>';
	return;
}

try {
	$stmt = $conn->query("SELECT * FROM permission");
	$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	error_log('permissions.php query error: ' . $e->getMessage());
	$permissions = [];
}
?>

<h2>Permissions</h2>

<a href="/attendanceleave/admin/roles/permission_form.php">+ Add Permission</a>

<div class="leave-history" style="margin-top:10px;">
	<div class="table-wrap">
		<table class="users">
			<thead>
				<tr>
					<th>ID</th>
					<th>Permission Name</th>
					<th>Status</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php if (count($permissions) === 0): ?>
					<tr><td colspan="4">No permissions found.</td></tr>
				<?php else: ?>
					<?php foreach ($permissions as $row): ?>
						<tr>
							<td><?php echo htmlspecialchars($row['permission_id']); ?></td>
							<td><?php echo htmlspecialchars($row['permission_name']); ?></td>
							<td><?php echo htmlspecialchars($row['status']); ?></td>
							<td>
								<a class="action-orange" href="/attendanceleave/admin/roles/permission_form.php?id=<?php echo urlencode($row['permission_id']); ?>">Edit</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>


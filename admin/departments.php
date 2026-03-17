<?php
include __DIR__ . '/../database/database.php';

if (empty($conn)) {
	echo '<p>Database connection not available.</p>';
	exit;
}

try {
	$query = "
SELECT 
	d.department_id,
	d.department_name,
	d.status,
	u.employee_name AS hod_name
FROM department d
LEFT JOIN department_hod m 
	ON d.department_id = m.department_id
LEFT JOIN tab1 u 
	ON m.employee_id = u.employee_id
";

	$stmt = $conn->query($query);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	error_log('departments.php error: ' . $e->getMessage());
	$rows = [];
}
?>

<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Department & HoD Management</title>
	<link rel="stylesheet" href="../css/dashboard.css">
</head>
<body style="padding:18px;font-family:Inter,Arial,Helvetica,sans-serif">

<h2>Department & HoD Management</h2>

<p><a href="add_department.php">+ Add Department</a></p>

<table border="1" cellpadding="10" style="width:100%;border-collapse:collapse">
<tr>
	<th>Department</th>
	<th>HOD</th>
	<th>Status</th>
	<th>Actions</th>
</tr>

<?php
if (count($rows) > 0) {

	foreach ($rows as $row) {
?>
<tr>
	<td><?php echo htmlspecialchars($row['department_name']); ?></td>

	<td>
		<?php echo $row['hod_name'] ? htmlspecialchars($row['hod_name']) : '<span style="color:red;">Not Assigned</span>'; ?>
	</td>

	<td><?php echo htmlspecialchars($row['status']); ?></td>

	<td>
		<a href="edit_department.php?id=<?php echo urlencode($row['department_id']); ?>">Edit</a> |
		<a href="assign_hod.php?id=<?php echo urlencode($row['department_id']); ?>">Assign HOD</a>
	</td>
</tr>

<?php
	}

} else {
	echo "<tr><td colspan='4'>No departments found.</td></tr>";
}
?>
</table>

</body>
</html>

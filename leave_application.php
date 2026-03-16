<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "database/database.php";

$eid = $_SESSION['eid'];

/* fetch employee info using eid */
$stmt = $conn->prepare("
SELECT employee_id, department_id
FROM tab1
WHERE eid = :eid
");

$stmt->execute([':eid' => $eid]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

$employee_id = $emp['employee_id'] ?? null;
$department_id = $emp['department_id'] ?? null;


/* fetch department HoD name */
$stmt = $conn->prepare("
SELECT e.employee_name
FROM department_hod dh
JOIN tab1 e ON dh.employee_id = e.employee_id
WHERE dh.department_id = :dept
");

$stmt->execute([':dept' => $department_id]);

$hod = $stmt->fetch(PDO::FETCH_ASSOC);

$hod_name = $hod['employee_name'] ?? 'HoD';


/* fetch leave balances for the current year */
$stmt = $conn->prepare("
SELECT 
lb.leave_type_id,
lt.leave_name,
lb.remaining_leave
FROM leave_balance lb
JOIN leave_type lt ON lb.leave_type_id = lt.leave_type_id
WHERE lb.employee_id = :empid
AND lb.year = YEAR(CURDATE())
");

$stmt->execute([':empid' => $employee_id]);
$balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// create map of balances by leave_type_id for JS
$balance_map = [];
foreach ($balances as $b) {
	$balance_map[(int)$b['leave_type_id']] = $b['remaining_leave'];
}


/* fetch active leave types */
$stmt = $conn->prepare("
SELECT leave_type_id, leave_name
FROM leave_type
WHERE status = 'active'
ORDER BY leave_name
");

$stmt->execute();
$leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* handle form submission */
if(isset($_POST['apply_leave'])){

	$leave_type_id = $_POST['leave_type_id'];
	$submit_to = $_POST['submit_to'];
	$from_date = $_POST['from_date'];
	$to_date = $_POST['to_date'];
	$reason = $_POST['reason'];

	if($to_date < $from_date){
		echo "<script>alert('End date cannot be before start date');</script>";
		exit;
	}

	// allow manual entry of total days (supports decimals e.g. 0.5)
	$total_days = null;
	if (isset($_POST['total_days']) && trim($_POST['total_days']) !== '') {
		$td_raw = str_replace(',', '.', trim($_POST['total_days']));
		if (!is_numeric($td_raw) || floatval($td_raw) <= 0) {
			echo "<script>alert('Total days must be a positive number (e.g. 1 or 0.5)');</script>";
			exit;
		}
		$total_days = floatval($td_raw);
	} else {
		$total_days = (strtotime($to_date) - strtotime($from_date)) / 86400 + 1;
	}

	$hod_status = null;
	$ms_status = null;

	// Normalize status values to lowercase for consistent DB queries
	if ($submit_to === 'hod') {
		$hod_status = 'pending';
	} else {
		$hod_status = 'skipped';
		$ms_status = 'pending';
	}

	$stmt = $conn->prepare("
	INSERT INTO leave_application 
	(employee_id, leave_type_id, from_date, to_date, total_days, reason, applied_at, HoD_status, medical_superintendent_status) 
	VALUES (:empid, :ltid, :from_date, :to_date, :days, :reason, NOW(), :hod_status, :ms_status)
	");

	$stmt->execute([
		':empid' => $employee_id,
		':ltid' => $leave_type_id,
		':from_date' => $from_date,
		':to_date' => $to_date,
		':days' => $total_days,
		':reason' => $reason,
		':hod_status' => $hod_status,
		':ms_status' => $ms_status
	]);

	$_SESSION['leave_submitted'] = true;

	// Redirect back to the dashboard but stay on the Leave section
	header('Location: employee_dashboard.php#leave');
	exit;
}
?>

<form method="POST">

<label>Type</label>

<div class="row-grid-3">
	<div class="col">
		<label>Type</label>
		<select name="leave_type_id" class="form-control">
			<?php foreach($leave_types as $lt): ?>
				<option value="<?= $lt['leave_type_id'] ?>"><?= str_replace('_',' ',$lt['leave_name']) ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="col">
		<label>Submit To</label>
		<select name="submit_to" class="form-control">
			<option value="hod" selected><?= $hod_name ?> (HoD)</option>
			<option value="ms">Medical Superintendent</option>
		</select>
	</div>

	<div class="col">
		<label>Balance</label>
		<input type="text" id="leave-balance" readonly class="form-control" style="width:100%;padding:8px;border-radius:6px;border:1px solid #e6eef0;background:#fff;">
	</div>

	<div class="col">
		<label>Start Date</label>
		<input type="date" name="from_date" class="form-control" required>
	</div>

	<div class="col">
		<label>End Date</label>
		<input type="date" name="to_date" class="form-control" required>
	</div>

	<div class="col">
		<label>Total Days</label>
		<input name="total_days" type="number" step="0.5" min="0" class="form-control" placeholder="e.g. 1 or 0.5">
	</div>

</div>

<button type="submit" name="apply_leave" class="btn btn-primary">
Apply Leave
</button>

</form>

<?php
/* fetch leave history for this employee */
$stmt = $conn->prepare(" 
SELECT
	la.application_id,
	lt.leave_name AS type,
	la.from_date AS start_date,
	la.to_date AS end_date,
	la.reason,
	la.total_days AS days,
	la.HoD_status AS hod_status,
	la.medical_superintendent_status AS ms_status
FROM leave_application la
JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
WHERE la.employee_id = :empid
ORDER BY la.applied_at DESC
");

$stmt->execute([':empid' => $employee_id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h3>Leave History</h3>
<table class="table">
<thead>
<tr>
<th>Type</th>
<th>Start Date</th>
<th>End Date</th>
<th>Reason</th>
<th>Days</th>
<th>HoD</th>
<th>HoD Status</th>
<th>MS Status</th>
</tr>
</thead>
<tbody>

<?php
if(count($applications) > 0){
		foreach($applications as $row){
		echo "<tr>";
		echo "<td>".htmlspecialchars($row['type'])."</td>";
		echo "<td>".htmlspecialchars($row['start_date'])."</td>";
		echo "<td>".htmlspecialchars($row['end_date'])."</td>";
		echo "<td>".htmlspecialchars($row['reason'])."</td>";
			echo "<td>".htmlspecialchars($row['days'])."</td>";
			echo "<td>".htmlspecialchars($hod_name)."</td>";
			echo "<td>".htmlspecialchars($row['hod_status'] ?? '-')."</td>";
			echo "<td>".htmlspecialchars($row['ms_status'] ?? '-')."</td>";
		echo "</tr>";
	}
}else{
	echo "<tr><td colspan='9'>No leave records</td></tr>";
}
?>

</tbody>
</table>

<script>
// balance mapping for leave_application form
var leaveBalances = <?php echo json_encode($balance_map); ?> || {};
var sel = document.querySelector('select[name="leave_type_id"]');
var balInput = document.getElementById('leave-balance');
if (sel && balInput) {
	function updateBalance(){
		var id = parseInt(sel.value,10);
		var v = leaveBalances[id];
		balInput.value = (typeof v !== 'undefined') ? v : '-';
	}
	sel.addEventListener('change', updateBalance);
	updateBalance();
}
</script>
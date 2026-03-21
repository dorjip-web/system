<?php
$conn = mysqli_connect("localhost", "root", "", "attendance_db");

// Get filters
$filter = $_GET['filter'] ?? '';
$date = $_GET['date'] ?? '';
$dept = $_GET['department_id'] ?? '';

// ================= DASHBOARD COUNTS =================

$present_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance WHERE checkout_status='Complete'");
$present = mysqli_fetch_assoc($present_q)['total'];

$missing_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance WHERE checkout_status='Missing'");
$missing = mysqli_fetch_assoc($missing_q)['total'];

$late_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance WHERE checkin_status='Late'");
$late = mysqli_fetch_assoc($late_q)['total'];

$absent_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance WHERE checkin_time IS NULL");
$absent = mysqli_fetch_assoc($absent_q)['total'];

// ================= MAIN QUERY =================

$query = "SELECT 
            t.employee_id,
            t.employee_name,
            a.department_id,
            a.attendance_date,
            a.checkin_time,
            a.checkin_address,
            a.checkin_status,
            a.checkout_time,
            a.checkout_address,
            a.checkout_status
          FROM attendance a
          JOIN tab1 t ON t.employee_id = a.employee_id
          WHERE 1";

// Filters
if (!empty($date)) {
    $query .= " AND a.attendance_date = '$date'";
}

if (!empty($dept)) {
    $query .= " AND a.department_id = '$dept'";
}

if ($filter == "absent") {
    $query .= " AND a.checkin_time IS NULL";
}

if ($filter == "late") {
    $query .= " AND a.checkin_status = 'Late'";
}

if ($filter == "missing_checkout") {
    $query .= " AND a.checkout_status = 'Missing'";
}

if ($filter == "present") {
    $query .= " AND a.checkout_status = 'Complete'";
}

$query .= " ORDER BY a.attendance_date DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html>
<head>
<title>Attendance Dashboard</title>

<style>
body { font-family: Arial; background:#0f172a; color:white; }
.container { width:90%; margin:auto; }

/* Cards */
.cards { display:flex; gap:15px; margin-bottom:20px; }
.card { flex:1; padding:20px; border-radius:10px; text-align:center; }
.card p { font-size:24px; }

.present { background:#22c55e; }
.missing { background:#f59e0b; }
.late { background:#ef4444; }
.absent { background:#64748b; }

/* Filter */
.filter-box {
    background:#1e293b;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
}

select, input, button {
    padding:8px;
    margin:5px;
    border-radius:5px;
    border:none;
}

button {
    background:#6366f1;
    color:white;
}

/* Table */
table { width:100%; border-collapse:collapse; background:white; color:black; }
th, td { padding:10px; border:1px solid #ddd; text-align:center; }
th { background:#6366f1; color:white; }

.row-late { background:#ffcccc; }
.row-missing { background:#fff3cd; }
.row-present { background:#d4edda; }
</style>

</head>

<body>

<div class="container">

<h2>📊 Staff Attendance Dashboard</h2>

<!-- ✅ DASHBOARD CARDS -->
<div class="cards">
    <div class="card present">
        <h3>Present</h3>
        <p><?= $present ?></p>
    </div>

    <div class="card missing">
        <h3>Missing</h3>
        <p><?= $missing ?></p>
    </div>

    <div class="card late">
        <h3>Late</h3>
        <p><?= $late ?></p>
    </div>

    <div class="card absent">
        <h3>Absent</h3>
        <p><?= $absent ?></p>
    </div>
</div>

<!-- ✅ FILTER -->
<div class="filter-box">
<form method="GET">

    <select name="filter">
        <option value="">All</option>
        <option value="absent">Absent</option>
        <option value="late">Late</option>
        <option value="missing_checkout">Missing Checkout</option>
        <option value="present">Present</option>
    </select>

    <input type="text" name="department_id" placeholder="Dept ID">
    <input type="date" name="date">

    <button type="submit">Apply</button>

</form>
</div>

<!-- ✅ TABLE -->
<table>
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Dept</th>
    <th>Date</th>
    <th>Check-in</th>
    <th>Address</th>
    <th>Status</th>
    <th>Check-out</th>
    <th>Address</th>
    <th>Status</th>
</tr>

<?php while($row = mysqli_fetch_assoc($result)) {

    $class = "";
    if ($row['checkin_status'] == 'Late') $class = "row-late";
    elseif ($row['checkout_status'] == 'Missing') $class = "row-missing";
    elseif ($row['checkout_status'] == 'Complete') $class = "row-present";
?>

<tr class="<?= $class ?>">
    <td><?= $row['employee_id'] ?></td>
    <td><?= $row['employee_name'] ?></td>
    <td><?= $row['department_id'] ?></td>
    <td><?= $row['attendance_date'] ?></td>

    <td><?= $row['checkin_time'] ?? '-' ?></td>
    <td><?= $row['checkin_address'] ?? '-' ?></td>
    <td><?= $row['checkin_status'] ?? '-' ?></td>

    <td><?= $row['checkout_time'] ?? 'Not Yet' ?></td>
    <td><?= $row['checkout_address'] ?? '-' ?></td>
    <td><?= $row['checkout_status'] ?? 'Pending' ?></td>
</tr>

<?php } ?>

</table>

</div>

</body>
</html>

<?php
// DB connection
$pdo = new PDO("mysql:host=localhost;dbname=attendance_db", "root", "");

// session + username for sidebar
if (session_status() === PHP_SESSION_NONE) session_start();
$username = 'NTMH';

// ================= SET LEAVE BALANCE =================
if (isset($_POST['set_balance'])) {

    $emp  = $_POST['employee_id'];
    $type = $_POST['leave_type_id'];
    $max  = $_POST['max_leave'];

    // check duplicate
    $check = $pdo->prepare("SELECT * FROM leave_balance WHERE employee_id=? AND leave_type_id=? AND year=YEAR(CURDATE())");
    $check->execute([$emp, $type]);

    if ($check->rowCount() == 0) {
        $stmt = $pdo->prepare("INSERT INTO leave_balance (employee_id, leave_type_id, year, max_leave_per_year, used_leave, remaining_leave) VALUES (?, ?, YEAR(CURDATE()), ?, 0, ?)");
        $stmt->execute([$emp, $type, $max, $max]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ================= ADJUST BALANCE =================
if (isset($_POST['adjust_balance'])) {
    $emp  = $_POST['employee_id'];
    $type = $_POST['leave_type_id'];
    $adj  = $_POST['adjustment'];

    $check = $pdo->prepare("SELECT * FROM leave_balance WHERE employee_id=? AND leave_type_id=? AND year=YEAR(CURDATE())");
    $check->execute([$emp, $type]);
    $data = $check->fetch();

    if ($data) {
        $new_used = $data['used_leave'] + $adj;
        $new_remaining = $data['max_leave_per_year'] - $new_used;
        if ($new_remaining >= 0 && $new_used >= 0) {
            $stmt = $pdo->prepare("UPDATE leave_balance SET used_leave=?, remaining_leave=? WHERE employee_id=? AND leave_type_id=? AND year=YEAR(CURDATE())");
            $stmt->execute([$new_used, $new_remaining, $emp, $type]);
        } else {
            $error_message = 'Invalid adjustment!';
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ================= RESET YEAR =================
if (isset($_POST['reset_year'])) {
    $pdo->query("UPDATE leave_balance SET used_leave = 0, remaining_leave = max_leave_per_year");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ================= FETCH DATA =================
$employees = $pdo->query("SELECT * FROM tab1 WHERE status='active'")->fetchAll();
$leave_types = $pdo->query("SELECT * FROM leave_type WHERE status='active'")->fetchAll();
$balances = $pdo->query("SELECT lb.*, t.employee_name, lt.leave_name FROM leave_balance lb JOIN tab1 t ON lb.employee_id = t.employee_id JOIN leave_type lt ON lb.leave_type_id = lt.leave_type_id")->fetchAll();
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin - Leave Balance</title>
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
            <a href="../leave_types/index.php">Leave Types</a>
            <a href="../leave_balances/leave_balance.php" class="active">Leave Balance</a>
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

                <h1>Leave Balance Management</h1>

                <?php if (!empty($error_message)): ?>
                    <div style="color:#d0342a;margin-bottom:10px"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <!-- Set Balance -->
                <div class="leave-form" style="margin-bottom:18px">
                    <h3>Set Leave Balance</h3>
                    <form method="POST">
                        <div class="row-grid-3">
                            <div class="col">
                                <label>Employee</label>
                                <select name="employee_id" required class="form-control">
                                    <option value="">Select Employee</option>
                                    <?php foreach($employees as $e){ ?>
                                        <option value="<?= htmlspecialchars($e['employee_id']) ?>"><?= htmlspecialchars($e['employee_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col">
                                <label>Leave Type</label>
                                <select name="leave_type_id" required class="form-control">
                                    <option value="">Select Leave Type</option>
                                    <?php foreach($leave_types as $l){ ?>
                                        <option value="<?= htmlspecialchars($l['leave_type_id']) ?>"><?= htmlspecialchars($l['leave_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col">
                                <label>Max Per Year</label>
                                <input type="number" name="max_leave" required class="form-control">
                            </div>
                        </div>
                        <div style="margin-top:12px">
                            <button type="submit" name="set_balance" class="btn">Save</button>
                        </div>
                    </form>
                </div>

                <!-- Adjust Balance -->
                <div class="leave-form" style="margin-bottom:18px">
                    <h3>Adjust Leave Balance</h3>
                    <form method="POST">
                        <div class="row-grid-3">
                            <div class="col">
                                <label>Employee</label>
                                <select name="employee_id" required class="form-control">
                                    <option value="">Select Employee</option>
                                    <?php foreach($employees as $e){ ?>
                                        <option value="<?= htmlspecialchars($e['employee_id']) ?>"><?= htmlspecialchars($e['employee_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col">
                                <label>Leave Type</label>
                                <select name="leave_type_id" required class="form-control">
                                    <option value="">Select Leave Type</option>
                                    <?php foreach($leave_types as $l){ ?>
                                        <option value="<?= htmlspecialchars($l['leave_type_id']) ?>"><?= htmlspecialchars($l['leave_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col">
                                <label>Adjustment (+/-)</label>
                                <input type="number" name="adjustment" required class="form-control">
                            </div>
                        </div>
                        <div style="margin-top:12px">
                            <button type="submit" name="adjust_balance" class="btn">Apply</button>
                        </div>
                    </form>
                </div>

                <!-- Reset -->
                <div style="margin-bottom:18px">
                    <h3>Reset Yearly</h3>
                    <form method="POST">
                        <button type="submit" name="reset_year" class="btn" onclick="return confirm('Reset all balances?')">Reset All</button>
                    </form>
                </div>

                <!-- Table -->
                <div class="leave-history">
                    <div class="table-wrap">
                        <h3>Leave Balance List</h3>
                        <table class="users">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Max</th>
                                    <th>Used</th>
                                    <th>Remaining</th>
                                    <th>Year</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($balances as $b){ ?>
                                <tr>
                                    <td><?= htmlspecialchars($b['employee_name']) ?></td>
                                    <td><?= htmlspecialchars($b['leave_name']) ?></td>
                                    <td><?= htmlspecialchars($b['max_leave_per_year']) ?></td>
                                    <td><?= htmlspecialchars($b['used_leave']) ?></td>
                                    <td><?= htmlspecialchars($b['remaining_leave']) ?></td>
                                    <td><?= htmlspecialchars($b['year']) ?></td>
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
</body>
</html>
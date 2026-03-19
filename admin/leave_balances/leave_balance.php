<?php
$pdo = new PDO("mysql:host=localhost;dbname=attendance_db", "root", "");

// ================= SET LEAVE BALANCE =================
if (isset($_POST['set_balance'])) {

    $emp  = $_POST['employee_id'];
    $type = $_POST['leave_type_id'];
    $max  = $_POST['max_leave'];

    // check duplicate
    $check = $pdo->prepare("
        SELECT * FROM leave_balance 
        WHERE employee_id=? AND leave_type_id=? AND year=YEAR(CURDATE())
    ");
    $check->execute([$emp, $type]);

    if ($check->rowCount() == 0) {

        $stmt = $pdo->prepare("
            INSERT INTO leave_balance 
            (employee_id, leave_type_id, year, max_leave_per_year, used_leave, remaining_leave)
            VALUES (?, ?, YEAR(CURDATE()), ?, 0, ?)
        ");

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

    // get current data
    $check = $pdo->prepare("
        SELECT * FROM leave_balance 
        WHERE employee_id=? AND leave_type_id=? AND year=YEAR(CURDATE())
    ");
    $check->execute([$emp, $type]);
    $data = $check->fetch();

    if ($data) {

        $new_used = $data['used_leave'] + $adj;
        $new_remaining = $data['max_leave_per_year'] - $new_used;

        if ($new_remaining >= 0 && $new_used >= 0) {

            $stmt = $pdo->prepare("
                UPDATE leave_balance 
                SET used_leave=?, remaining_leave=?
                WHERE employee_id=? AND leave_type_id=? AND year=YEAR(CURDATE())
            ");

            $stmt->execute([$new_used, $new_remaining, $emp, $type]);
        } else {
            echo "<p style='color:red;'>Invalid adjustment!</p>";
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ================= RESET YEAR =================
if (isset($_POST['reset_year'])) {

    $pdo->query("
        UPDATE leave_balance 
        SET used_leave = 0,
            remaining_leave = max_leave_per_year
    ");

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ================= FETCH DATA =================
$employees = $pdo->query("SELECT * FROM tab1 WHERE status='active'")->fetchAll();
$leave_types = $pdo->query("SELECT * FROM leave_type WHERE status='active'")->fetchAll();

$balances = $pdo->query("
    SELECT lb.*, t.employee_name, lt.leave_name 
    FROM leave_balance lb
    JOIN tab1 t ON lb.employee_id = t.employee_id
    JOIN leave_type lt ON lb.leave_type_id = lt.leave_type_id
")->fetchAll();
?>

<h1>Leave Balance Management</h1>

<!-- ================= SET BALANCE ================= -->
<h3>Set Leave Balance</h3>
<form method="POST">
    <select name="employee_id" required>
        <option value="">Select Employee</option>
        <?php foreach($employees as $e){ ?>
            <option value="<?= $e['employee_id'] ?>">
                <?= $e['employee_name'] ?>
            </option>
        <?php } ?>
    </select>

    <select name="leave_type_id" required>
        <option value="">Select Leave Type</option>
        <?php foreach($leave_types as $l){ ?>
            <option value="<?= $l['leave_type_id'] ?>">
                <?= $l['leave_name'] ?>
            </option>
        <?php } ?>
    </select>

    <input type="number" name="max_leave" placeholder="Max Per Year" required>

    <button type="submit" name="set_balance">Save</button>
</form>

<hr>

<!-- ================= ADJUST BALANCE ================= -->
<h3>Adjust Leave Balance</h3>
<form method="POST">
    <select name="employee_id" required>
        <option value="">Select Employee</option>
        <?php foreach($employees as $e){ ?>
            <option value="<?= $e['employee_id'] ?>">
                <?= $e['employee_name'] ?>
            </option>
        <?php } ?>
    </select>

    <select name="leave_type_id" required>
        <option value="">Select Leave Type</option>
        <?php foreach($leave_types as $l){ ?>
            <option value="<?= $l['leave_type_id'] ?>">
                <?= $l['leave_name'] ?>
            </option>
        <?php } ?>
    </select>

    <input type="number" name="adjustment" placeholder="+ / - value" required>

    <button type="submit" name="adjust_balance">Apply</button>
</form>

<hr>

<!-- ================= RESET ================= -->
<h3>Reset Yearly</h3>
<form method="POST">
    <button type="submit" name="reset_year"
        onclick="return confirm('Reset all balances?')">
        Reset All
    </button>
</form>

<hr>

<!-- ================= TABLE ================= -->
<h3>Leave Balance List</h3>

<table border="1" cellpadding="8">
    <tr>
        <th>Employee</th>
        <th>Leave Type</th>
        <th>Max</th>
        <th>Used</th>
        <th>Remaining</th>
        <th>Year</th>
    </tr>

    <?php foreach($balances as $b){ ?>
    <tr>
        <td><?= $b['employee_name'] ?></td>
        <td><?= $b['leave_name'] ?></td>
        <td><?= $b['max_leave_per_year'] ?></td>
        <td><?= $b['used_leave'] ?></td>
        <td><?= $b['remaining_leave'] ?></td>
        <td><?= $b['year'] ?></td>
    </tr>
    <?php } ?>
</table>
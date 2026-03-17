<?php
session_start();

require_once "database/database.php";

// Optional role check: only allow users with role 'MS'
$eid = $_SESSION['eid'] ?? '';
$stmt = $conn->prepare("SELECT r.role_name FROM tab1 t LEFT JOIN role r ON r.role_id = t.role_id WHERE t.eid = :eid");
$stmt->execute([':eid'=>$eid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Allow common role-name variants for MS (case-insensitive)
$roleName = strtolower(trim($user['role_name'] ?? ''));
$isMS = ($roleName === 'ms' || strpos($roleName, 'medical') !== false || strpos($roleName, 'superintendent') !== false);
if (!$user || !$isMS) {
    echo "
    <div style='padding:40px;text-align:center;font-family:Arial'>
        <h2 style='color:red;'>Access Denied</h2>
        <p>You are not assigned as MS.</p>
        <a href='employee_dashboard.php'>Return to Dashboard</a>
    </div>
    ";
    exit;
}

// Resolve MS employee_id from session eid so we can record who approved/rejected
$ms_employee_id = null;
if (!empty($eid)) {
    $mstmt = $conn->prepare("SELECT employee_id FROM tab1 WHERE eid = :eid");
    $mstmt->execute([':eid' => $eid]);
    $mrow = $mstmt->fetch(PDO::FETCH_ASSOC);
    $ms_employee_id = $mrow['employee_id'] ?? null;
}

// Summary (case-insensitive) for MS statuses
$stmt = $conn->query("SELECT 
SUM(CASE WHEN LOWER(medical_superintendent_status) = 'pending' THEN 1 ELSE 0 END) AS pending,
SUM(CASE WHEN LOWER(medical_superintendent_status) = 'approved' THEN 1 ELSE 0 END) AS approved,
SUM(CASE WHEN LOWER(medical_superintendent_status) = 'rejected' THEN 1 ELSE 0 END) AS rejected
FROM leave_application
");
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt_forwarded = $conn->prepare("SELECT 
la.application_id,
e.employee_name,
lt.leave_name AS leave_type,
la.from_date,
la.to_date,
la.total_days,
la.hod_note
FROM leave_application la
JOIN tab1 e ON la.employee_id = e.employee_id
JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
WHERE LOWER(la.HoD_status) = 'forwarded'
AND LOWER(la.medical_superintendent_status) = 'pending'
ORDER BY la.applied_at DESC
");

$stmt_forwarded->execute();
$forwarded_requests = $stmt_forwarded->fetchAll(PDO::FETCH_ASSOC);

// Debug helper: when visiting MS_dashboard.php?dbg=1 this will print DB counts and recent rows
// debug panel removed

// Handle Approve/Reject actions -> update DB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // accept either 'request_id' or 'application_id' from the form
    $rawId = $_POST['request_id'] ?? $_POST['application_id'] ?? null;
    if ($rawId === null) {
        header('Location: MS_dashboard.php');
        exit;
    }

    $id = (int)$rawId;
    $actionRaw = $_POST['action'];
    $action = strtolower(trim($actionRaw)); // expect 'approve' or 'reject'

    $newStatus = ($action === 'approve' || $action === 'approved') ? 'approved' : 'rejected';

    // If the table has a column to record MS action time, set it and record who acted; otherwise only update status
    $col = $conn->query("SHOW COLUMNS FROM leave_application LIKE 'medical_superintendent_action_at'")->fetch(PDO::FETCH_ASSOC);
    if ($col) {
        $ustmt = $conn->prepare("\n    UPDATE leave_application\n    SET medical_superintendent_status = :status,\n        medical_superintendent_action_by = :ms,\n        medical_superintendent_action_at = NOW()\n    WHERE application_id = :id\n    ");

        $ustmt->execute([
            ':status' => $newStatus,
            ':ms' => $ms_employee_id,
            ':id' => $id
        ]);
    } else {
        $ustmt = $conn->prepare("UPDATE leave_application SET medical_superintendent_status = :status WHERE application_id = :id");
        $ustmt->execute([':status' => $newStatus, ':id' => $id]);
    }

    // If approved, deduct leave balance
    if ($newStatus === 'approved') {
        $stmtLeave = $conn->prepare("SELECT employee_id, leave_type_id, total_days FROM leave_application WHERE application_id = :id");
        $stmtLeave->execute([':id' => $id]);
        $leave = $stmtLeave->fetch(PDO::FETCH_ASSOC);

        if ($leave) {
            $employee_id = $leave['employee_id'];
            $leave_type_id = $leave['leave_type_id'];
            $days = $leave['total_days'];
            $year = date("Y");

            $updateBalance = $conn->prepare(
                "UPDATE leave_balance
                SET used_leave = used_leave + :days
                WHERE employee_id = :emp
                AND leave_type_id = :type
                AND year = :year"
            );

            $updateBalance->execute([
                ':days' => $days,
                ':emp' => $employee_id,
                ':type' => $leave_type_id,
                ':year' => $year
            ]);
        }
    }

    $_SESSION['message'] = "Request processed: " . ucfirst($newStatus);

    header('Location: MS_dashboard.php');
    exit;
}

// Keep the DB-driven $summary fetched above. Ensure session arrays exist
$pending = $_SESSION['ms_pending'] ?? [];
$recent = $_SESSION['ms_recent'] ?? [];

$stmt_direct = $conn->prepare("SELECT
la.application_id,
e.employee_name,
lt.leave_name AS leave_type,
la.from_date,
la.to_date,
la.total_days,
la.reason
FROM leave_application la
JOIN tab1 e ON la.employee_id = e.employee_id
JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
WHERE (la.HoD_status IS NULL OR la.HoD_status = '' OR LOWER(la.HoD_status) = 'skipped')
AND LOWER(la.medical_superintendent_status) = 'pending'
ORDER BY la.applied_at DESC
");

$stmt_direct->execute();
$direct_requests = $stmt_direct->fetchAll(PDO::FETCH_ASSOC);

$stmt_recent = $conn->prepare("SELECT 
e.employee_name,
lt.leave_name,
la.medical_superintendent_status,
la.medical_superintendent_action_at
FROM leave_application la
JOIN tab1 e ON la.employee_id = e.employee_id
JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
WHERE la.medical_superintendent_status IN ('approved','rejected')
ORDER BY la.medical_superintendent_action_at DESC
LIMIT 5
");

$stmt_recent->execute();
$recent_decisions = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>MS Dashboard</title>
    <link rel="stylesheet" href="css/MS_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="profile">
            <div class="avatar">M</div>
            <div class="username">@MS</div>
        </div>
        <?php $__current = strtolower(basename($_SERVER['PHP_SELF'])); ?>
        <nav class="menu">
            <a href="employee_dashboard.php" <?php if ($__current === 'employee_dashboard.php') echo 'class="active"'; ?>>Employee Dashboard</a>
            <a href="hod_dashboard.php" <?php if ($__current === 'hod_dashboard.php') echo 'class="active"'; ?>>HoD Dashboard</a>
            <a href="MS_dashboard.php" <?php if ($__current === 'ms_dashboard.php') echo 'class="active"'; ?>>MS Dashboard</a>
            <a href="#pending-approvals">Pending Approvals</a>
            <a href="#recent-decisions">Recent Decisions</a>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="search"><input placeholder="Search..."></div>
            <div class="logout"><a href="login.php">Logout</a></div>
        </header>

        <div class="container">
            <header>
                <h1>MS Dashboard</h1>
            </header>

            <?php if (!empty($_SESSION['message'])): ?>
                <div class="notice"><?=htmlspecialchars($_SESSION['message']); unset($_SESSION['message']);?></div>
            <?php endif; ?>

            

            <section class="cards">
                <div class="card">
                    <div class="card-title">Pending Approvals</div>
                    <div class="card-value"><?=htmlspecialchars($summary['pending']);?></div>
                </div>
                <div class="card">
                    <div class="card-title">Approved Leaves</div>
                    <div class="card-value"><?=htmlspecialchars($summary['approved']);?></div>
                </div>
                <div class="card">
                    <div class="card-title">Rejected</div>
                    <div class="card-value"><?=htmlspecialchars($summary['rejected']);?></div>
                </div>
            </section>

            <section class="panel">
                <h2 id="pending-approvals">Leaves Forwarded by HoD</h2>
                <table class="requests">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Days</th>
                            <th>HoD Note</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($forwarded_requests)): ?>
                        <tr><td colspan="7" class="empty">No pending approvals</td></tr>
                    <?php else: ?>
                        <?php foreach($forwarded_requests as $req): ?>

<tr>

<td><?=htmlspecialchars($req['employee_name']);?></td>

<td><?=htmlspecialchars($req['leave_type']);?></td>

<td><?=date('d M',strtotime($req['from_date']));?></td>

<td><?=date('d M',strtotime($req['to_date']));?></td>

<td><?=htmlspecialchars($req['total_days']);?></td>

<td><?=htmlspecialchars($req['hod_note']);?></td>

<td class="actions">

<form method="post" class="inline-form">

<input type="hidden" name="request_id" value="<?= $req['application_id']; ?>">

<button type="submit" name="action" value="approve" class="btn btn-approve">
Approve
</button>

<button type="submit" name="action" value="reject" class="btn btn-reject">
Reject
</button>

</form>

</td>

</tr>

<?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="panel">
                <h2>Direct Leave Requests</h2>

                <table class="requests">
                <thead>
                <tr>
                <th>Employee</th>
                <th>Leave</th>
                <th>From</th>
                <th>To</th>
                <th>Days</th>
                <th>Reason</th>
                <th>Action</th>
                </tr>
                </thead>

                <tbody>

                <?php if(empty($direct_requests)): ?>

                <tr>
                <td colspan="7" class="empty">No direct requests</td>
                </tr>

                <?php else: ?>

                <?php foreach($direct_requests as $req): ?>

                <tr>
                <td><?=htmlspecialchars($req['employee_name']);?></td>
                <td><?=htmlspecialchars($req['leave_type']);?></td>
                <td><?=date('d M',strtotime($req['from_date']));?></td>
                <td><?=date('d M',strtotime($req['to_date']));?></td>
                <td><?=htmlspecialchars($req['total_days']);?></td>
                <td><?=htmlspecialchars($req['reason']);?></td>

                <td class="actions">

                <form method="post" class="inline-form">

                <input type="hidden" name="application_id" value="<?= $req['application_id']; ?>">

                <button type="submit" name="action" value="approve" class="btn btn-approve">
                Approve
                </button>

                <button type="submit" name="action" value="reject" class="btn btn-reject">
                Reject
                </button>

                </form>

                </td>

                </tr>

                <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
                </table>

            </section>

            <section class="panel">
                <h2 id="recent-decisions">Recent Decisions</h2>
                <table class="recent">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave</th>
                            <th>MS Action</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>

<?php if(empty($recent_decisions)): ?>

<tr>
<td colspan="4" class="empty">No recent decisions</td>
</tr>

<?php else: ?>

<?php foreach ($recent_decisions as $r): ?>

<tr>

<td><?= htmlspecialchars($r['employee_name']); ?></td>

<td><?= htmlspecialchars($r['leave_name']); ?></td>

<td>
    <?php
        $s = strtolower(trim($r['medical_superintendent_status'] ?? ''));
        if ($s === 'pending') {
            echo '<span class="status-pending">' . htmlspecialchars(ucfirst($s)) . '</span>';
        } elseif ($s === 'forwarded') {
            echo '<span class="status-forwarded">' . htmlspecialchars(ucfirst($s)) . '</span>';
        } elseif ($s === 'approved') {
            echo '<span class="status-approved">' . htmlspecialchars(ucfirst($s)) . '</span>';
        } elseif ($s === 'rejected') {
            echo '<span class="status-rejected">' . htmlspecialchars(ucfirst($s)) . '</span>';
        } else {
            echo htmlspecialchars($r['medical_superintendent_status']);
        }
    ?>
</td>

<td><?= date('d M', strtotime($r['medical_superintendent_action_at'])); ?></td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

                    </tbody>
                </table>
            </section>

        </div>
    </main>
</div>
<script>
// Highlight clicked sidebar link immediately (handles in-page anchors)
document.querySelectorAll('.sidebar .menu a').forEach(function(a){
    a.addEventListener('click', function(){
        document.querySelectorAll('.sidebar .menu a').forEach(function(x){ x.classList.remove('active'); });
        this.classList.add('active');
    });
});
</script>
</body>
</html>
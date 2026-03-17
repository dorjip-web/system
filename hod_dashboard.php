<?php
session_start();

if (!isset($_SESSION['eid'])) {
    header("Location: login.php");
    exit;
}

require_once "database/database.php";

// Ensure the user is HoD — otherwise show Access Denied message
$eid = $_SESSION['eid'] ?? '';

// fetch internal employee_id for this HoD (from tab1)
$stmt = $conn->prepare("SELECT employee_id FROM tab1 WHERE eid = :eid");
$stmt->execute([':eid' => $_SESSION['eid']]);
$hod = $stmt->fetch(PDO::FETCH_ASSOC);
$hod_id = $hod['employee_id'] ?? null;

// also fetch role name to verify access
$rstmt = $conn->prepare("SELECT r.role_name FROM tab1 t LEFT JOIN role r ON r.role_id = t.role_id WHERE t.eid = :eid");
$rstmt->execute([':eid'=>$eid]);
$user = $rstmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['role_name'] ?? '') !== 'HoD') {

    echo "
    <div style='padding:40px;text-align:center;font-family:Arial'>
        <h2 style='color:red;'>Access Denied</h2>
        <p>You are not assigned as HoD.</p>
        <a href='employee_dashboard.php'>Return to Dashboard</a>
    </div>
    ";

    exit;
}



// Handle Forward / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {

    $id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'Forward') {

        $sql = "UPDATE leave_application 
                SET HoD_status = 'forwarded',
                    HoD_action_by = :hod,
                    HoD_action_at = NOW(),
                    medical_superintendent_status = 'pending'
                WHERE application_id = :id";

    } else {

        $sql = "UPDATE leave_application 
                SET HoD_status = 'rejected',
                    HoD_action_by = :hod,
                    HoD_action_at = NOW()
                WHERE application_id = :id";
    }

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':hod' => $hod_id,
        ':id' => $id
    ]);

    $_SESSION['message'] = "Request processed successfully";

    header("Location: hod_dashboard.php");
    exit;
}


// fetch pending leave applications assigned to this HoD from DB
$stmt = $conn->prepare("SELECT 
la.application_id,
e.employee_name,
lt.leave_name,
la.from_date,
la.to_date,
la.total_days,
la.reason
FROM leave_application la
JOIN tab1 e ON la.employee_id = e.employee_id
JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
JOIN department_hod dh ON e.department_id = dh.department_id
WHERE dh.employee_id = :hod_id
AND la.HoD_status = 'pending'
ORDER BY la.applied_at DESC
");

$stmt->execute([':hod_id' => $hod_id]);
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

$summary = $_SESSION['summary'] ?? null;

// fetch summary counts for this HoD (pending / forwarded / rejected)
$stmt_summary = $conn->prepare("SELECT 
SUM(CASE WHEN HoD_status = 'pending' THEN 1 ELSE 0 END) AS pending,
SUM(CASE WHEN HoD_status = 'forwarded' THEN 1 ELSE 0 END) AS forwarded,
SUM(CASE WHEN HoD_status = 'rejected' THEN 1 ELSE 0 END) AS rejected
FROM leave_application la
JOIN tab1 e ON la.employee_id = e.employee_id
JOIN department_hod dh ON e.department_id = dh.department_id
WHERE dh.employee_id = :hod_id");

$stmt_summary->execute([':hod_id' => $hod_id]);
$fetched = $stmt_summary->fetch(PDO::FETCH_ASSOC);
if ($fetched) {
    $summary = [
        'pending' => (int)($fetched['pending'] ?? 0),
        'forwarded' => (int)($fetched['forwarded'] ?? 0),
        'rejected' => (int)($fetched['rejected'] ?? 0)
    ];
} else {
    $summary = ['pending' => count($pending), 'forwarded' => 0, 'rejected' => 0];
}

// fetch recent actions (forwarded or rejected) performed by this HoD
$stmt_recent = $conn->prepare("SELECT 
e.employee_name,
lt.leave_name,
la.HoD_status,
la.HoD_action_at
FROM leave_application la
JOIN tab1 e ON la.employee_id = e.employee_id
JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
WHERE la.HoD_action_by = :hod_id
AND la.HoD_status IN ('forwarded','rejected')
ORDER BY la.HoD_action_at DESC
LIMIT 10");

$stmt_recent->execute([':hod_id' => $hod_id]);
$recent_rows = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
$recent = [];
foreach ($recent_rows as $rr) {
    $recent[] = [
        'employee' => $rr['employee_name'] ?? '',
        'leave' => $rr['leave_name'] ?? '',
        'action' => $rr['HoD_status'] ?? '',
        'date' => $rr['HoD_action_at'] ?? ''
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>HoD Dashboard</title>
    <link rel="stylesheet" href="css/hod_dashboard.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="profile">
            <div class="avatar">H</div>
            <div class="username">@HoD</div>
        </div>
        <?php $__current_page = basename($_SERVER['PHP_SELF']); ?>
        <nav class="menu">
            <a href="employee_dashboard.php" <?php if ($__current_page === 'employee_dashboard.php') echo 'class="active"'; ?>>Employee Dashboard</a>
            <a href="hod_dashboard.php" <?php if ($__current_page === 'hod_dashboard.php') echo 'class="active"'; ?>>HoD Dashboard</a>
            <a href="#pending-requests">Pending Leave Requests</a>
            <a href="#recent-actions">Recent Leave Actions</a>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="search"><input placeholder="Search..."></div>
            <div class="logout"><a href="login.php">Logout</a></div>
        </header>

        <div class="container">
            <header>
                <h1>HoD Dashboard</h1>
            </header>

            <?php if (!empty($_SESSION['message'])): ?>
                <div class="notice"><?=htmlspecialchars($_SESSION['message']); unset($_SESSION['message']);?></div>
            <?php endif; ?>

            <section class="cards">
        <div class="card">
            <div class="card-title">Pending</div>
            <div class="card-value"><?=htmlspecialchars($summary['pending']);?></div>
        </div>
        <div class="card">
            <div class="card-title">Forwarded to MS</div>
            <div class="card-value"><?=htmlspecialchars($summary['forwarded']);?></div>
        </div>
        <div class="card">
            <div class="card-title">Rejected</div>
            <div class="card-value"><?=htmlspecialchars($summary['rejected']);?></div>
        </div>
    </section>

    <section class="panel">
        <h2 id="pending-requests">Pending Leave Requests</h2>
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
            <?php if (empty($pending)): ?>
                <tr><td colspan="7" class="empty">No pending requests</td></tr>
            <?php else: ?>
                <?php foreach ($pending as $req): ?>
<tr>
<td><?=htmlspecialchars($req['employee_name']);?></td>
<td><?=htmlspecialchars($req['leave_name']);?></td>
<td><?=htmlspecialchars(date('d M', strtotime($req['from_date'])));?></td>
<td><?=htmlspecialchars(date('d M', strtotime($req['to_date'])));?></td>
<td><?=htmlspecialchars($req['total_days']);?></td>
<td><?=htmlspecialchars($req['reason']);?></td>
<td class="actions">
<form method="post" class="inline-form">
<input type="hidden" name="request_id" value="<?=htmlspecialchars($req['application_id']);?>">
<button type="submit" name="action" value="Forward" class="btn btn-forward">Forward</button>
<button type="submit" name="action" value="Reject" class="btn btn-reject">Reject</button>
</form>
</td>
</tr>
<?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2 id="recent-actions">Recent Leave Actions</h2>
        <table class="recent">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave</th>
                    <th>HoD Action</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?=htmlspecialchars($r['employee']);?></td>
                        <td><?=htmlspecialchars($r['leave']);?></td>
                        <td>
                            <?php
                                $act = strtolower(trim($r['action'] ?? ''));
                                if ($act === 'pending') {
                                    echo '<span class="status-pending">' . htmlspecialchars(ucfirst($act)) . '</span>';
                                } elseif ($act === 'forwarded') {
                                    echo '<span class="status-forwarded">' . htmlspecialchars(ucfirst($act)) . '</span>';
                                } elseif ($act === 'approved') {
                                    echo '<span class="status-approved">' . htmlspecialchars(ucfirst($act)) . '</span>';
                                } elseif ($act === 'rejected') {
                                    echo '<span class="status-rejected">' . htmlspecialchars(ucfirst($act)) . '</span>';
                                } else {
                                    echo htmlspecialchars($r['action']);
                                }
                            ?>
                        </td>
                        <td><?=htmlspecialchars(date('d M', strtotime($r['date'])));?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

</div>
<script>
// Sidebar active-link helper: highlight clicked link immediately (also works for in-page anchors)
document.querySelectorAll('.sidebar .menu a').forEach(function(a){
    a.addEventListener('click', function(){
        document.querySelectorAll('.sidebar .menu a').forEach(function(x){ x.classList.remove('active'); });
        this.classList.add('active');
    });
});
</script>
</body>
</html>


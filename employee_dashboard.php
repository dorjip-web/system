<?php
session_start();
require_once "database/database.php";

if(!isset($_SESSION['eid'])){
    header("Location: login.php");
    exit;
}

$eid = $_SESSION['eid'];

$stmt = $conn->prepare("SELECT 
    t.employee_id,
    t.employee_name,
    t.eid,
    t.department_id,
    t.designation,
    t.status,
    d.department_name,
    r.role_name
FROM tab1 t
LEFT JOIN department d ON d.department_id = t.department_id
LEFT JOIN role r ON r.role_id = t.role_id
WHERE t.eid = :eid
");

$stmt->execute([':eid'=>$eid]);

$employee = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$employee) {
    echo "Employee not found";
    exit;
}

$employee_id = $employee['employee_id'] ?? null;

// get department_id from employee
$department_id = $employee['department_id'] ?? null;

// optional override from POST
$dept_from_post = $_POST['department_id'] ?? null;

// decide final dept_id
$dept_id = $dept_from_post !== null ? $dept_from_post : $department_id;

// shift logic
$shift = NULL;

if ((int)$dept_id === 3) {

    date_default_timezone_set('Asia/Thimphu');
    $current_time = date('H:i:s');

    if ($current_time >= '08:00:00' && $current_time < '14:00:00') {
        $shift = 'morning';
    } elseif ($current_time >= '14:00:00' && $current_time < '20:00:00') {
        $shift = 'evening';
    } else {
        $shift = 'night';
    }
}

// fetch department HoD name
$hod_name = 'HoD';
if ($department_id) {
    $hodStmt = $conn->prepare("SELECT e.employee_name FROM department_hod dh JOIN tab1 e ON dh.employee_id = e.eid WHERE dh.department_id = :dept LIMIT 1");
    $hodStmt->execute([':dept' => $department_id]);
    $hod = $hodStmt->fetch(PDO::FETCH_ASSOC);
    $hod_name = $hod['employee_name'] ?? 'HoD';
}

// fetch active leave types
$ltStmt = $conn->prepare("SELECT leave_type_id, leave_name FROM leave_type WHERE status = 'active' ORDER BY leave_name");
$ltStmt->execute();
$leave_types = $ltStmt->fetchAll(PDO::FETCH_ASSOC);

// fetch leave balances for current employee (map by leave_type_id)
$balStmt = $conn->prepare("SELECT lb.leave_type_id, lb.remaining_leave FROM leave_balance lb WHERE lb.employee_id = :empid AND lb.year = YEAR(CURDATE())");
$balStmt->execute([':empid' => $employee_id]);
$leave_balances = [];
foreach ($balStmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $leave_balances[(int)$b['leave_type_id']] = $b['remaining_leave'];
}

// fetch leave history for this employee
// resolve internal employee id for leave queries
if (empty($employee_id)) {
    $idStmt = $conn->prepare("SELECT employee_id FROM tab1 WHERE eid = :eid LIMIT 1");
    $idStmt->execute([':eid' => $eid]);
    $row = $idStmt->fetch(PDO::FETCH_ASSOC);
    $employee_id = $row['employee_id'] ?? null;
}

$appStmt = $conn->prepare(
    "SELECT
        la.application_id,
        lt.leave_name AS type,
        la.from_date AS sdate,
        la.to_date AS edate,
        la.reason,
        (
            SELECT lb.remaining_leave
            FROM leave_balance lb
            WHERE lb.employee_id = :empid
            AND lb.leave_type_id = la.leave_type_id
            AND lb.year = YEAR(CURDATE())
        ) AS leave_balance,
        la.total_days AS days,
        la.HoD_status AS hod_status,
        la.medical_superintendent_status AS ms_status
    FROM leave_application la
    JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
    WHERE la.employee_id = :empid
    ORDER BY la.applied_at DESC"
);
$appStmt->execute([':empid' => $employee_id]);
$leave_applications = $appStmt->fetchAll(PDO::FETCH_ASSOC);

// detect recent leave submission (flash) and clear flag
$leaveSubmitted = false;
if (!empty($_SESSION['leave_submitted'])) {
    $leaveSubmitted = true;
    unset($_SESSION['leave_submitted']);
}

// ensure attendance/leaves storage and helper vars exist
if (!isset($_SESSION['attendance']) || !is_array($_SESSION['attendance'])) {
    $_SESSION['attendance'] = [];
}
if (!isset($_SESSION['leaves']) || !is_array($_SESSION['leaves'])) {
    $_SESSION['leaves'] = [];
}

$tz = new DateTimeZone('Asia/Thimphu');
$nowTz = new DateTime('now', $tz);
$today = $nowTz->format('Y-m-d');

// Helper: reverse-geocode lat/lon using Nominatim
function reverseGeocode($lat, $lon) {
    if (!is_numeric($lat) || !is_numeric($lon)) {
        return '';
    }

    $url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat=" . urlencode($lat) . "&lon=" . urlencode($lon);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => "EmployeeAttendanceSystem/1.0",
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return '';
    }

    $data = json_decode($response, true);

    if (!empty($data['address'])) {

        // Building / place name
        $building =
            $data['name']
            ?? $data['address']['hospital']
            ?? $data['address']['amenity']
            ?? $data['address']['building']
            ?? '';

        // Road
        $road = $data['address']['road'] ?? '';

        // City
        $city =
            $data['address']['city']
            ?? $data['address']['town']
            ?? $data['address']['village']
            ?? '';

        // Country
        $country = $data['address']['country'] ?? '';

        // Build formatted address
        $parts = [];

        if ($building) $parts[] = $building;
        if ($road) $parts[] = $road;
        if ($city) $parts[] = $city;
        if ($country) $parts[] = $country;

        return implode(', ', $parts);
    }

    return '';
}

// Return a human-readable address for display. If stored value is a Nominatim reverse URL,
// extract lat/lon and re-run reverseGeocode to get the place name.
function displayAddressForUI($stored) {
    $stored = trim((string)$stored);
    if ($stored === '') return '';
    // detect nominatim reverse URL
    if (strpos($stored, 'nominatim.openstreetmap.org/reverse') !== false) {
        $parts = parse_url($stored);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (!empty($q['lat']) && !empty($q['lon'])) {
                $name = reverseGeocode($q['lat'], $q['lon']);
                if (!empty($name)) return $name;
                // if reverse failed, return a shortened URL to keep it clickable
                return $stored;
            }
        }
    }
    // otherwise return stored value as-is
    return $stored;
}

// Handle attendance POST actions (DB-backed) and accept lat/lon from client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $lat = $_POST['lat'] ?? '';
    $lon = $_POST['lon'] ?? '';

    $address = '';

    if ($lat && $lon) {
        $address = reverseGeocode($lat, $lon);
    }

    if ($_POST['action'] === 'checkin_morning') {
        // Determine employee and department (allow POST override), then compute shift using Asia/Thimphu timezone
        $dept_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : (int)$department_id;
        $emp_id = isset($_POST['employee_id']) ? $_POST['employee_id'] : $employee_id;

        $shift = NULL;
        $dt = new DateTime('now', new DateTimeZone('Asia/Thimphu'));
        $current_time = $dt->format('H:i:s');
        if ((int)$dept_id === 3) {
            if ($current_time >= '08:00:00' && $current_time < '14:00:00') {
                $shift = 'morning';
            } elseif ($current_time >= '14:00:00' && $current_time < '20:00:00') {
                $shift = 'evening';
            } else {
                $shift = 'night';
            }
        }

        // Insert using CURDATE() and NOW() on DB side; include address and status
        $stmt = $conn->prepare(
            "INSERT INTO attendance (employee_id, department_id, attendance_date, shift_type, checkin_time, checkin_address, status)
            VALUES (?, ?, CURDATE(), ?, NOW(), ?, 'Present')"
        );
        $stmt->execute([$emp_id, $dept_id, $shift, $address]);

        $timeStr = $dt->format('h:i:s A');
        $_SESSION['attendance'][$today]['morning'] = $address !== '' ? $timeStr . ' @ ' . $address : $timeStr;
    } elseif ($_POST['action'] === 'checkout_evening') {
        $dt = new DateTime('now', new DateTimeZone('Asia/Thimphu'));
        $nowStr = $dt->format('Y-m-d H:i:s');
        $attDate = $dt->format('Y-m-d');
        $timeStr = $dt->format('h:i:s A');

        $stmt = $conn->prepare(
            "UPDATE attendance
            SET checkout_time = :now, checkout_address = :addr
            WHERE employee_id = :emp AND attendance_date = CURDATE()"
        );
        $stmt->execute([':now' => $nowStr, ':addr' => $address, ':emp' => $employee_id]);
        $_SESSION['attendance'][$today]['evening'] = $address !== '' ? $timeStr . ' @ ' . $address : $timeStr;
    }
    // redirect to avoid form resubmission
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// load today's attendance from DB for this employee (use DB state, not session, to decide button state)
$attStmt = $conn->prepare(
    "SELECT a.*, t.employee_name, d.department_name
    FROM attendance a
    JOIN tab1 t ON a.employee_id = t.employee_id
    JOIN department d ON a.department_id = d.department_id
    WHERE a.employee_id = ? LIMIT 1"
);
$attStmt->execute([$employee_id]);
$attendanceToday = $attStmt->fetch(PDO::FETCH_ASSOC) ?: [];
 $hasMorning = !empty($attendanceToday['checkin_time']);
 $hasEvening = !empty($attendanceToday['checkout_time']);

$month = $nowTz->format('Y-m');
// Compute monthly summary from DB (avoids stale session data)
$monthly = ['morning' => 0, 'evening' => 0, 'days' => 0];
if (!empty($employee_id)) {
    $startOfMonth = $nowTz->format('Y-m-01');
    $endOfMonth = $nowTz->format('Y-m-t');
    $sumStmt = $conn->prepare(
        "SELECT
            COUNT(*) AS days,
            SUM(checkin_time IS NOT NULL) AS morning,
            SUM(checkout_time IS NOT NULL) AS evening
        FROM attendance
        WHERE employee_id = ?
        AND attendance_date BETWEEN ? AND ?"
    );
    $sumStmt->execute([$employee_id, $startOfMonth, $endOfMonth]);
    $res = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $monthly['days'] = (int)($res['days'] ?? 0);
    $monthly['morning'] = (int)($res['morning'] ?? 0);
    $monthly['evening'] = (int)($res['evening'] ?? 0);
}

$notifications = [];
if (empty($_SESSION['attendance'][$today]['morning'])) {
    $notifications[] = 'Reminder: Please check-in for morning.';
}
// compute avatar initials from employee name or username
$fullName = $employee['employee_name'] ?? $employee['name'] ?? $_SESSION['username'] ?? '';
$initials = '';
if ($fullName !== '') {
    $parts = preg_split('/\s+/', trim($fullName));
    if (count($parts) === 1) {
        $initials = strtoupper(substr($parts[0], 0, 2));
    } else {
        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
}
if (!empty($_SESSION['attendance'][$today]['morning']) && empty($_SESSION['attendance'][$today]['evening'])) {
    $notifications[] = 'Reminder: Evening check-out pending.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Employee Dashboard</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="app">
            <aside class="sidebar">
        <div class="profile">
            <div class="avatar"><?php echo htmlspecialchars($initials ?: 'U'); ?></div>
            <div class="username">@<?php echo htmlspecialchars($employee['employee_name'] ?? $employee['name'] ?? $_SESSION['username'] ?? 'User'); ?></div>
        </div>
        <?php $__current = strtolower(basename($_SERVER['PHP_SELF'])); ?>
        <nav class="menu">
            <a href="#employee" <?php if ($__current === 'employee_dashboard.php') echo 'class="active"'; ?>>My Dashboard</a>
            <a href="MS_dashboard.php" <?php if ($__current === 'ms_dashboard.php' || $__current === 'ms_dashboard.php') echo 'class="active"'; ?>>MS Dashboard</a>
            <a href="hod_dashboard.php" <?php if ($__current === 'hod_dashboard.php') echo 'class="active"'; ?>>HoD Dashboard</a>
            <a href="#attendance">Attendance</a>
            <a href="#leave">Leave</a>
            <a href="#leave-history">Leave History</a>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="search"> <input placeholder="Search..."> </div>
            <div class="logout"><a href="login.php">Logout</a></div>
        </header>

        <section class="grid">
            <div id="employee" class="card employee-card">
                <h2 style="margin-bottom:25px"><span class="welcome-orange">Welcome,</span> <span class="name-blue"><?php echo htmlspecialchars($employee['employee_name'] ?? $employee['name'] ?? 'User'); ?></span></h2>

                <h3>Employee Details</h3>
                <table style="width:100%;margin-top:8px;border-collapse:collapse;">
                    <tbody>
                        <tr>
                            <td style="width:160px;padding:6px 8px;font-weight:700;">Name:</td>
                            <td style="padding:6px 8px;"><?php echo htmlspecialchars($employee['employee_name'] ?? $employee['name'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 8px;font-weight:700;">EID:</td>
                            <td style="padding:6px 8px;"><?php echo htmlspecialchars($employee['eid'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 8px;font-weight:700;">Designation:</td>
                            <td style="padding:6px 8px;"><?php echo htmlspecialchars($employee['designation'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 8px;font-weight:700;">Department:</td>
                            <td style="padding:6px 8px;"><?php echo htmlspecialchars($employee['department_name'] ?? $employee['department'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 8px;font-weight:700;">Role:</td>
                            <td style="padding:6px 8px;"><?php echo htmlspecialchars($employee['role_name'] ?? $employee['role'] ?? ''); ?></td>
                        </tr>
                        
                        <tr>
                            <td style="padding:6px 8px;font-weight:700;">Status:</td>
                            <td style="padding:6px 8px;">
                                <?php
                                    $status = $employee['status'] ?? '';
                                    if (strtolower($status) === 'active') {
                                        echo '<span style="color:#1e73be;font-weight:600">' . htmlspecialchars($status) . '</span>';
                                    } else {
                                        echo htmlspecialchars($status);
                                    }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="attendance" class="card attendance-card">
                <h3>Attendance</h3>
                <div class="att-actions">
                    <div style="display:inline">
                        <button type="button" id="btn-checkin" class="btn" <?php echo ($hasMorning ?? false) ? 'disabled' : ''; ?>>Check-in</button>
                    </div>

                    <div style="display:inline">
                        <button type="button" id="btn-checkout" class="btn" <?php echo (!($hasMorning ?? false) || ($hasEvening ?? false)) ? 'disabled' : ''; ?>>Check-out</button>
                    </div>
                </div>

                <div class="today-status">
                    <strong>Today's Status:</strong>
                    <div>Morning: <?php
                        if (!empty($attendanceToday['checkin_time'])) {
                            // parse stored timestamp as Asia/Thimphu (stored in that timezone)
                            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $attendanceToday['checkin_time'], new DateTimeZone('Asia/Thimphu'));
                            if ($dt === false) {
                                try {
                                    $dt = new DateTime($attendanceToday['checkin_time']);
                                    $dt->setTimezone(new DateTimeZone('Asia/Thimphu'));
                                } catch (Exception $e) {
                                    $dt = null;
                                }
                            }
                            if ($dt) {
                                $t = $dt->format('h:i:s A');
                            } else {
                                $t = date('h:i:s A', strtotime($attendanceToday['checkin_time']));
                            }
                            echo htmlspecialchars($t);
                        } else {
                            echo 'Not Checked';
                        }
                    ?></div>
                    <div>Evening: <?php
                        if (!empty($attendanceToday['checkout_time'])) {
                            // parse stored timestamp as Asia/Thimphu (stored in that timezone)
                            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $attendanceToday['checkout_time'], new DateTimeZone('Asia/Thimphu'));
                            if ($dt === false) {
                                try {
                                    $dt = new DateTime($attendanceToday['checkout_time']);
                                    $dt->setTimezone(new DateTimeZone('Asia/Thimphu'));
                                } catch (Exception $e) {
                                    $dt = null;
                                }
                            }
                            if ($dt) {
                                $t = $dt->format('h:i:s A');
                            } else {
                                $t = date('h:i:s A', strtotime($attendanceToday['checkout_time']));
                            }
                            echo htmlspecialchars($t);
                        } else {
                            echo 'Not Checked';
                        }
                    ?></div>
                </div>

                <div class="monthly">
                    <strong style="display:block;margin-bottom:6px">Monthly Summary (<?php echo (new DateTime('now', new DateTimeZone('Asia/Thimphu')))->format('F Y'); ?>)</strong>
                    <div style="margin-bottom:6px">Days recorded: <?php echo $monthly['days']; ?></div>
                    <div style="margin-bottom:6px">Morning present: <?php echo $monthly['morning']; ?></div>
                    <div style="margin-bottom:6px">Evening present: <?php echo $monthly['evening']; ?></div>
                </div>

                <div style="height:16px;" aria-hidden="true"></div>

                <div class="notifications">
                    <strong>Notifications</strong>
                    <?php if (empty($notifications)): ?>
                        <div class="note">No notifications</div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="note"><?php echo htmlspecialchars($n); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="leave" class="card leave-card">
                <h3>Leave</h3>
                <form method="post" action="leave_application.php" class="leave-form">
                    <input type="hidden" name="apply_leave" value="1">

                    <div class="row-grid-3">
                        <div class="col">
                            <label>Type</label>
                            <select name="leave_type_id">
                                <?php foreach ($leave_types as $lt): ?>
                                    <option value="<?php echo htmlspecialchars($lt['leave_type_id']); ?>"><?php echo htmlspecialchars(str_replace('_',' ',$lt['leave_name'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col">
                            <label>Submit To</label>
                            <select name="submit_to">
                                <option value="hod"><?php echo htmlspecialchars($hod_name); ?> (HoD)</option>
                                <option value="ms">Medical Superintendent</option>
                            </select>
                        </div>

                        <div class="col">
                            <label>Balance</label>
                            <input type="text" id="leave-balance" readonly style="width:100%;padding:8px;border-radius:6px;border:1px solid #e6eef0;background:#fff;">
                        </div>

                        <div class="col">
                            <label>Start</label>
                            <input type="date" name="from_date" required>
                        </div>

                        <div class="col">
                            <label>End</label>
                            <input type="date" name="to_date" required>
                        </div>

                        <div class="col">
                            <label>Total Days</label>
                            <input name="total_days" type="number" step="0.5" min="0" placeholder="e.g. 1 or 0.5">
                        </div>
                    </div>

                    <div class="row">
                        <label>Reason</label>
                        <input name="reason" placeholder="Reason" required>
                    </div>

                    <div class="row">
                        <?php if ($leaveSubmitted): ?>
                            <button class="btn" type="button" disabled>Submitted</button>
                        <?php else: ?>
                            <button class="btn" type="submit">Submit</button>
                        <?php endif; ?>
                    </div>
                </form>

                <div id="leave-history" class="leave-history">
                    <h4>Leave History</h4>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th><th>S.date</th><th>E.date</th><th>Reason</th><th>Days</th><th>HoD</th><th>HoD Status</th><th>MS Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($leave_applications)): ?>
                                <?php foreach ($leave_applications as $lv): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($lv['type']); ?></td>
                                        <td><?php echo htmlspecialchars($lv['sdate']); ?></td>
                                        <td><?php echo htmlspecialchars($lv['edate']); ?></td>
                                        <td><?php echo htmlspecialchars($lv['reason']); ?></td>
                                        <td><?php echo htmlspecialchars($lv['days']); ?></td>
                                        <td><?php echo htmlspecialchars($hod_name); ?></td>
                                        <td>
                                            <?php
                                                $hod_s = trim($lv['hod_status'] ?? '');
                                                $hod_l = strtolower($hod_s);
                                                if ($hod_l === 'pending') {
                                                    $disp = ucfirst($hod_l);
                                                    echo '<span class="status-pending">' . htmlspecialchars($disp) . '</span>';
                                                } elseif ($hod_l === 'forwarded') {
                                                    $disp = ucfirst($hod_l);
                                                    echo '<span class="status-forwarded">' . htmlspecialchars($disp) . '</span>';
                                                } elseif ($hod_l === 'approved') {
                                                    $disp = ucfirst($hod_l);
                                                    echo '<span class="status-approved">' . htmlspecialchars($disp) . '</span>';
                                                } elseif ($hod_l === 'rejected') {
                                                    $disp = ucfirst($hod_l);
                                                    echo '<span class="status-rejected">' . htmlspecialchars($disp) . '</span>';
                                                } else {
                                                    echo htmlspecialchars($hod_s ?: '-');
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                                $ms_s = trim($lv['ms_status'] ?? '');
                                                $ms_l = strtolower($ms_s);
                                                if ($ms_l === 'pending') {
                                                    $disp = ucfirst($ms_l);
                                                    echo '<span class="status-pending">' . htmlspecialchars($disp) . '</span>';
                                                } elseif ($ms_l === 'forwarded') {
                                                    $disp = ucfirst($ms_l);
                                                    echo '<span class="status-forwarded">' . htmlspecialchars($disp) . '</span>';
                                                } elseif ($ms_l === 'approved') {
                                                    $disp = ucfirst($ms_l);
                                                    echo '<span class="status-approved">' . htmlspecialchars($disp) . '</span>';
                                                } elseif ($ms_l === 'rejected') {
                                                    $disp = ucfirst($ms_l);
                                                    echo '<span class="status-rejected">' . htmlspecialchars($disp) . '</span>';
                                                } else {
                                                    echo htmlspecialchars($ms_s ?: '-');
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9">No leave records</td></tr>
                            <?php endif; ?>
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

<script>
// Sidebar active-link handler: keep orange highlight in sync with clicked link / URL hash
(function(){
// Geolocation + attendance submission using Nominatim reverse-geocoding on server
(function(){
    function postAction(action, lat, lon){
        const body = new URLSearchParams();
        body.append('action', action);
        if (typeof lat !== 'undefined') body.append('lat', lat);
        if (typeof lon !== 'undefined') body.append('lon', lon);

        fetch(location.href, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: body.toString()
        }).then(() => location.reload()).catch(()=>location.reload());
    }

    function withGeo(action){
        if (!navigator.geolocation) {
            postAction(action);
            return;
        }
        navigator.geolocation.getCurrentPosition(function(pos){
            postAction(action, pos.coords.latitude, pos.coords.longitude);
        }, function(err){
            postAction(action);
        }, {timeout:10000});
    }

    document.getElementById('btn-checkin')?.addEventListener('click', function(){
        withGeo('checkin_morning');
    });
    document.getElementById('btn-checkout')?.addEventListener('click', function(){
        withGeo('checkout_evening');
    });
})();

    const links = document.querySelectorAll('.menu a');
    if (!links.length) return;

    function setActiveByHash(){
        const hash = location.hash || '#employee';
        links.forEach(a => a.classList.toggle('active', a.getAttribute('href') === hash));
    }

    links.forEach(a => {
        a.addEventListener('click', () => {
            links.forEach(x => x.classList.remove('active'));
            a.classList.add('active');
        });
    });

    window.addEventListener('hashchange', setActiveByHash);
    document.addEventListener('DOMContentLoaded', setActiveByHash);
    // also run immediately in case DOMContentLoaded already fired
    setActiveByHash();
})();

// leave balance mapping and form behavior
(function(){
    var balances = <?php echo json_encode($leave_balances); ?> || {};
    var sel = document.querySelector('form.leave-form select[name="leave_type_id"]');
    var balInput = document.getElementById('leave-balance');
    if (!sel || !balInput) return;
    function updateBalance(){
        var id = parseInt(sel.value,10);
        var v = balances[id];
        balInput.value = (typeof v !== 'undefined') ? v : '-';
    }
    sel.addEventListener('change', updateBalance);
    updateBalance();
})();
</script>

<script>
// Asynchronously resolve nominatim links to display names
(function(){
    const links = document.querySelectorAll('.loc-link');
    if (!links.length) return;
    links.forEach(a => {
        const lat = a.dataset.lat;
        const lon = a.dataset.lon;
        if (!lat || !lon) return;
        fetch('geocode_lookup.php?lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon))
            .then(r => r.json())
            .then(j => {
                if (j && j.name) {
                    a.textContent = j.name;
                }
            }).catch(()=>{});
    });
})();
</script>

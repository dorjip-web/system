<?php
session_start();
require_once "../../database/database.php";

// Messages
$message = '';

// --- Handle role create/update ---
if (isset($_POST['save_role'])) {
    $rid = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
    $rname = trim($_POST['role_name'] ?? '');
    $rstatus = (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'inactive' : 'active';
    if ($rname === '') {
        $message = 'Role name required.';
    } else {
        try {
            if ($rid) {
                $stmt = $conn->prepare('UPDATE role SET role_name = :name, status = :status WHERE role_id = :id');
                $stmt->execute([':name'=>$rname, ':status'=>$rstatus, ':id'=>$rid]);
                $message = 'Role updated.';
            } else {
                $stmt = $conn->prepare('INSERT INTO role (role_name, status) VALUES (:name, :status)');
                $stmt->execute([':name'=>$rname, ':status'=>$rstatus]);
                $message = 'Role added.';
            }
            header('Location: /attendanceleave/admin/roles/roles.php');
            exit;
        } catch (Exception $e) {
            error_log('roles save error: '.$e->getMessage());
            $message = 'DB error saving role.';
        }
    }
}

// --- Handle permission create/update ---
if (isset($_POST['save_perm'])) {
    $pid = isset($_POST['perm_id']) ? (int)$_POST['perm_id'] : 0;
    $pname = trim($_POST['permission_name'] ?? '');
    $pstatus = (isset($_POST['pstatus']) && $_POST['pstatus'] === 'inactive') ? 'inactive' : 'active';
    if ($pname === '') {
        $message = 'Permission name required.';
    } else {
        try {
            if ($pid) {
                $stmt = $conn->prepare('UPDATE permission SET permission_name = :name, status = :status WHERE permission_id = :id');
                $stmt->execute([':name'=>$pname, ':status'=>$pstatus, ':id'=>$pid]);
                $message = 'Permission updated.';
            } else {
                $stmt = $conn->prepare('INSERT INTO permission (permission_name, status) VALUES (:name, :status)');
                $stmt->execute([':name'=>$pname, ':status'=>$pstatus]);
                $message = 'Permission added.';
            }
            header('Location: /attendanceleave/admin/roles/roles.php');
            exit;
        } catch (Exception $e) {
            error_log('permission save error: '.$e->getMessage());
            $message = 'DB error saving permission.';
        }
    }
}

// --- Handle assign permissions save ---
if (isset($_POST['save_assign'])) {
    $role_id = isset($_POST['assign_role_id']) ? (int)$_POST['assign_role_id'] : 0;
    $perms = isset($_POST['assign_permissions']) && is_array($_POST['assign_permissions']) ? array_map('intval', $_POST['assign_permissions']) : [];
    if ($role_id) {
        try {
            $conn->beginTransaction();
            $del = $conn->prepare('DELETE FROM role_permission WHERE role_id = :rid');
            $del->execute([':rid'=>$role_id]);
            if (count($perms) > 0) {
                $ins = $conn->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (:rid, :pid)');
                foreach ($perms as $pid) $ins->execute([':rid'=>$role_id, ':pid'=>$pid]);
            }
            $conn->commit();
            $message = 'Assigned permissions updated.';
            header('Location: /attendanceleave/admin/roles/roles.php');
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            error_log('assign save error: '.$e->getMessage());
            $message = 'Error saving assignments.';
        }
    } else {
        $message = 'Select a role to assign permissions.';
    }
}

// fetch roles & permissions
try {
    $rstmt = $conn->query("SELECT * FROM role ORDER BY role_name ASC");
    $roles = $rstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('roles fetch error: '.$e->getMessage());
    $roles = [];
}
try {
    $pstmt = $conn->query("SELECT * FROM permission ORDER BY permission_name ASC");
    $perms = $pstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('perms fetch error: '.$e->getMessage());
    $perms = [];
}

// edit targets
$edit_role = isset($_GET['edit_role']) ? (int)$_GET['edit_role'] : 0;
$edit_perm = isset($_GET['edit_perm']) ? (int)$_GET['edit_perm'] : 0;
$edit_role_data = null;
$edit_perm_data = null;
if ($edit_role) {
    try {
        $s = $conn->prepare('SELECT * FROM role WHERE role_id = :id LIMIT 1');
        $s->execute([':id'=>$edit_role]);
        $edit_role_data = $s->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }
}
if ($edit_perm) {
    try {
        $s = $conn->prepare('SELECT * FROM permission WHERE permission_id = :id LIMIT 1');
        $s->execute([':id'=>$edit_perm]);
        $edit_perm_data = $s->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }
}

// assigned list helper for JS display
function getAssigned($conn, $role_id) {
    $out = [];
    if (!$role_id) return $out;
    try {
        $a = $conn->prepare('SELECT permission_id FROM role_permission WHERE role_id = :rid');
        $a->execute([':rid'=>$role_id]);
        $rows = $a->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) $out[] = (int)$r['permission_id'];
    } catch (Exception $e) {}
    return $out;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Roles & Permissions</title>
    <link rel="stylesheet" href="../../css/dashboard.css">
    <style>
        .roles-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .roles-grid .card{padding:12px}
        .small-link{display:block;margin:6px 0}
    </style>
</head>
<body>

<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="profile">
            <div class="avatar">NT</div>
            <div class="username">NTMH</div>
        </div>

        <nav class="menu">
            <a href="/attendanceleave/admin_dashboard.php">Admin Dashboard</a>
            <a href="/attendanceleave/admin/users/users.php">User Management</a>
            <a href="/attendanceleave/admin/departments/departments.php">Department & HoD Management</a>
            <a href="/attendanceleave/admin/roles/roles.php" class="active">Roles & Permissions</a>
            <a href="/attendanceleave/leave_types.php">Leave Types</a>
            <a href="/attendanceleave/leave_balance.php">Leave Balance</a>
            <a href="/attendanceleave/attendance_logs.php">Attendance Logs</a>
            <a href="/attendanceleave/leave_records.php">Leave Records</a>
            <a href="/attendanceleave/reports.php">Reports</a>
            <a href="/attendanceleave/settings.php">Settings</a>
            <a href="/attendanceleave/admin_logout.php">Logout</a>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

        <!-- TOPBAR -->
        <header class="topbar">
            <div class="search">
                <input placeholder="Search...">
            </div>
            <div class="logout">
                <a href="/attendanceleave/login.php">Logout</a>
            </div>
        </header>

        <!-- PAGE CONTENT -->
        <section class="grid">
            <?php if ($message): ?>
                <div style="color:green;margin-bottom:8px"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="card roles-grid" style="grid-template-columns:1fr;">
                <div class="card">
                    <h2>Manage Roles</h2>
                    <a class="small-link" href="#add-role">+ Add Role</a>
                    <div class="leave-history" style="margin-top:10px;">
                        <div class="table-wrap">
                            <table class="users">
                                <thead>
                                    <tr><th>ID</th><th>Role Name</th><th>Status</th><th>Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (count($roles) === 0): ?>
                                        <tr><td colspan="4">No roles found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($roles as $r): ?>
                                            <tr>
                                                <td><?php echo (int)$r['role_id']; ?></td>
                                                <td><?php echo htmlspecialchars($r['role_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['status']); ?></td>
                                                <td>
                                                    <a class="action-orange" href="?edit_role=<?php echo (int)$r['role_id']; ?>">Edit</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card" id="add-role">
                    <h3><?php echo $edit_role_data ? 'Edit Role' : 'Add Role'; ?></h3>
                    <form method="POST">
                        <input type="hidden" name="role_id" value="<?php echo $edit_role_data ? (int)$edit_role_data['role_id'] : 0; ?>">
                        <div>
                            <label>Role Name</label><br>
                            <input type="text" name="role_name" required value="<?php echo $edit_role_data ? htmlspecialchars($edit_role_data['role_name']) : ''; ?>" style="padding:8px;width:100%">
                        </div>
                        <div style="margin-top:8px">
                            <label>Status</label><br>
                            <select name="status">
                                <option value="active" <?php if (($edit_role_data['status'] ?? '') === 'active') echo 'selected'; ?>>Active</option>
                                <option value="inactive" <?php if (($edit_role_data['status'] ?? '') === 'inactive') echo 'selected'; ?>>Inactive</option>
                            </select>
                        </div>
                        <div style="margin-top:8px">
                            <button type="submit" name="save_role" class="btn"><?php echo $edit_role_data ? 'Update' : 'Save'; ?></button>
                            <a href="/attendanceleave/admin/roles/roles.php" class="btn" style="background:#fff;color:#333;border:1px solid #cfd8db;margin-left:8px">Cancel</a>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h2>Permissions</h2>
                    <a class="small-link" href="#add-perm">+ Add Permission</a>
                    <div class="leave-history" style="margin-top:10px;">
                        <div class="table-wrap">
                            <table class="users">
                                <thead>
                                    <tr><th>ID</th><th>Permission Name</th><th>Status</th><th>Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (count($perms) === 0): ?>
                                        <tr><td colspan="4">No permissions found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($perms as $p): ?>
                                            <tr>
                                                <td><?php echo (int)$p['permission_id']; ?></td>
                                                <td><?php echo htmlspecialchars($p['permission_name']); ?></td>
                                                <td><?php echo htmlspecialchars($p['status']); ?></td>
                                                <td><a class="action-orange" href="?edit_perm=<?php echo (int)$p['permission_id']; ?>">Edit</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card" id="add-perm">
                    <h3><?php echo $edit_perm_data ? 'Edit Permission' : 'Add Permission'; ?></h3>
                    <form method="POST">
                        <input type="hidden" name="perm_id" value="<?php echo $edit_perm_data ? (int)$edit_perm_data['permission_id'] : 0; ?>">
                        <div>
                            <label>Permission Name</label><br>
                            <input type="text" name="permission_name" required value="<?php echo $edit_perm_data ? htmlspecialchars($edit_perm_data['permission_name']) : ''; ?>" style="padding:8px;width:100%">
                        </div>
                        <div style="margin-top:8px">
                            <label>Status</label><br>
                            <select name="pstatus">
                                <option value="active" <?php if (($edit_perm_data['status'] ?? '') === 'active') echo 'selected'; ?>>Active</option>
                                <option value="inactive" <?php if (($edit_perm_data['status'] ?? '') === 'inactive') echo 'selected'; ?>>Inactive</option>
                            </select>
                        </div>
                        <div style="margin-top:8px">
                            <button type="submit" name="save_perm" class="btn"><?php echo $edit_perm_data ? 'Update' : 'Save'; ?></button>
                            <a href="/attendanceleave/admin/roles/roles.php" class="btn" style="background:#fff;color:#333;border:1px solid #cfd8db;margin-left:8px">Cancel</a>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h2>Assign Permissions</h2>
                    <form method="POST">
                        <div>
                            <label>Select Role</label><br>
                            <select name="assign_role_id" id="assign_role_id">
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo (int)$r['role_id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="margin-top:8px">
                            <label>Permissions</label>
                            <div class="leave-history" style="margin-top:8px">
                                <div class="table-wrap">
                                    <table class="users">
                                        <thead>
                                            <tr><th></th><th>Permission</th><th>Status</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($perms) === 0): ?>
                                                <tr><td colspan="3">No permissions available.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($perms as $p): ?>
                                                    <tr>
                                                        <td style="width:40px"><input type="checkbox" name="assign_permissions[]" value="<?php echo (int)$p['permission_id']; ?>"></td>
                                                        <td><?php echo htmlspecialchars($p['permission_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($p['status']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top:8px">
                            <button type="submit" name="save_assign" class="btn">Save Assignments</button>
                        </div>
                    </form>
                </div>

            </div>

        </section>
    </main>
</div>

</body>
</html>
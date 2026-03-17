<?php
include __DIR__ . '/../../database/database.php';

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

if (session_status() === PHP_SESSION_NONE) session_start();
$username = 'NTMH';
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin - Departments</title>
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
            <div class="menu-item">
                <a href="../users/users.php" <?php if(basename($_SERVER['PHP_SELF']) === 'users.php') echo 'class="active"'; ?>>User Management</a>
                <div class="submenu">
                    <a href="../users/add_user.php">➕ Add New User</a>
                </div>
            </div>
            <a href="departments.php" <?php if(basename($_SERVER['PHP_SELF']) === 'departments.php') echo 'class="active"'; ?>>Department &amp; HoD Management</a>
            <a href="../roles_permissions.php" <?php if(basename($_SERVER['PHP_SELF']) === 'roles_permissions.php') echo 'class="active"'; ?>>Roles &amp; Permissions</a>
            <a href="../leave_types.php" <?php if(basename($_SERVER['PHP_SELF']) === 'leave_types.php') echo 'class="active"'; ?>>Leave Types</a>
            <a href="../leave_balance.php" <?php if(basename($_SERVER['PHP_SELF']) === 'leave_balance.php') echo 'class="active"'; ?>>Leave Balance</a>
            <a href="../attendance_logs.php" <?php if(basename($_SERVER['PHP_SELF']) === 'attendance_logs.php') echo 'class="active"'; ?>>Attendance Logs</a>
            <a href="../leave_records.php" <?php if(basename($_SERVER['PHP_SELF']) === 'leave_records.php') echo 'class="active"'; ?>>Leave Records</a>
            <a href="../reports.php" <?php if(basename($_SERVER['PHP_SELF']) === 'reports.php') echo 'class="active"'; ?>>Reports</a>
            <a href="../settings.php" <?php if(basename($_SERVER['PHP_SELF']) === 'settings.php') echo 'class="active"'; ?>>Settings</a>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="search"> <input placeholder="Search..."> </div>
            <div class="logout"><a href="../../login.php">Logout</a></div>
        </header>

        <section>

            <div style="padding:18px">

                <h1>Department & HoD Management</h1>

                <p><a href="#" onclick="openAddModal();return false;">+ Add Department</a></p>

                <div class="leave-history">
                    <div class="table-wrap">
                        <table class="users">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>HOD</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($rows) === 0): ?>
                                    <tr>
                                        <td colspan="4">No departments found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr data-id="<?php echo (int)$row['department_id']; ?>" data-name="<?php echo htmlspecialchars($row['department_name'], ENT_QUOTES); ?>" data-status="<?php echo htmlspecialchars($row['status']); ?>" data-hod-name="<?php echo htmlspecialchars($row['hod_name'] ?? '', ENT_QUOTES); ?>">
                                            <td class="dept-name"><?php echo htmlspecialchars($row['department_name']); ?></td>
                                            <td class="dept-hod"><?php echo $row['hod_name'] ? htmlspecialchars($row['hod_name']) : '<span style="color:#d0342a">Not Assigned</span>'; ?></td>
                                            <td class="dept-status">
                                                <?php if (strtolower(trim($row['status'] ?? '')) === 'active'): ?>
                                                    <span class="status-active">Active</span>
                                                <?php else: ?>
                                                    <span class="status-inactive">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a class="action-orange" href="#" onclick="openEditModal(<?php echo (int)$row['department_id']; ?>);return false;">Edit</a> |
                                                <a class="action-orange" href="#" onclick="openAssignModal(<?php echo (int)$row['department_id']; ?>);return false;">Assign HOD</a> |
                                                <a class="action-orange" href="toggle_department_status.php?id=<?php echo urlencode($row['department_id']); ?>"><?php echo (strtolower(trim($row['status'] ?? '')) === 'active') ? 'Deactivate' : 'Activate'; ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </section>
    </main>
</div>

                <!-- Edit Department Modal -->
                <div id="editModal" style="display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.4);align-items:center;justify-content:center">
                    <div class="card leave-card" style="width:420px;margin:auto;border-radius:6px;position:relative">
                        <h3>Edit Department</h3>
                        <div id="editError" style="color:#d0342a;margin-bottom:8px;display:none"></div>
                        <form id="editForm" class="leave-form">
                            <input type="hidden" name="department_id" id="edit_department_id">
                            <div class="row">
                                <label>Department Name:</label>
                                <input type="text" name="department_name" id="edit_department_name" class="form-control" required>
                            </div>

                            <div class="row">
                                <label>Status:</label>
                                <select name="status" id="edit_status" class="form-control">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div class="row">
                                <button type="submit" class="btn">Save</button>
                                <button type="button" onclick="closeEditModal()" class="btn" style="background:#fff;color:#333;border:1px solid #cfd8db;margin-left:8px">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Assign HoD Modal -->
                <div id="assignModal" style="display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.4);align-items:center;justify-content:center">
                    <div class="card leave-card" style="width:420px;margin:auto;border-radius:6px;position:relative">
                        <h3 id="assignTitle">Assign HoD</h3>
                        <div id="assignError" style="color:#d0342a;margin-bottom:8px;display:none"></div>
                        <form id="assignForm" class="leave-form">
                            <input type="hidden" name="department_id" id="assign_department_id">
                            <div class="row">
                                <label>Select HoD:</label>
                                <select name="employee_id" id="assign_employee_id" required class="form-control">
                                    <option value="">-- Loading --</option>
                                </select>
                            </div>

                            <div class="row">
                                <button type="submit" class="btn">Assign</button>
                                <button type="button" onclick="closeAssignModal()" class="btn" style="background:#fff;color:#333;border:1px solid #cfd8db;margin-left:8px">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Add Department Modal -->
                <div id="addModal" style="display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.4);align-items:center;justify-content:center">
                    <div class="card leave-card" style="width:420px;margin:auto;border-radius:6px;position:relative">
                        <h3>Add Department</h3>
                        <div id="addError" style="color:#d0342a;margin-bottom:8px;display:none"></div>
                        <form id="addForm" class="leave-form">
                            <div class="row">
                                <label>Department Name:</label>
                                <input type="text" name="department_name" id="add_department_name" class="form-control" required>
                            </div>

                            <div class="row">
                                <button type="submit" class="btn">Add</button>
                                <button type="button" onclick="closeAddModal()" class="btn" style="background:#fff;color:#333;border:1px solid #cfd8db;margin-left:8px">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                function el(id){return document.getElementById(id)}

                function openEditModal(id){
                    fetch('edit_department.php?ajax=1&id='+encodeURIComponent(id))
                        .then(r=>r.json())
                        .then(data=>{
                            if(!data.success){ alert(data.error||'Failed to load'); return; }
                            el('edit_department_id').value = data.department.department_id;
                            el('edit_department_name').value = data.department.department_name;
                            el('edit_status').value = data.department.status || 'active';
                            el('editError').style.display='none';
                            el('editModal').style.display='flex';
                        }).catch(e=>{ alert('Error fetching department'); });
                }

                function closeEditModal(){ el('editModal').style.display='none'; }

                document.getElementById('editForm').addEventListener('submit', function(e){
                    e.preventDefault();
                    var form = new FormData(this);
                    form.append('ajax','1');
                    fetch('edit_department.php?id='+encodeURIComponent(el('edit_department_id').value), {method:'POST', body: form})
                        .then(r=>r.json())
                        .then(data=>{
                            if(!data.success){ el('editError').innerText = data.error || 'Save failed'; el('editError').style.display='block'; return; }
                            // update row in table
                            var tr = document.querySelector('tr[data-id="'+data.department_id+'"]');
                            if(tr){ tr.querySelector('.dept-name').innerText = data.department_name; var statusEl = tr.querySelector('.dept-status'); statusEl.innerHTML = (data.status==='active')?'<span class="status-active">Active</span>' : '<span class="status-inactive">Inactive</span>'; }
                            closeEditModal();
                        }).catch(e=>{ el('editError').innerText='Server error'; el('editError').style.display='block'; });
                });

                function openAssignModal(id){
                    fetch('assign_hod.php?ajax=1&id='+encodeURIComponent(id))
                        .then(r=>r.json())
                        .then(data=>{
                            if(!data.success){ alert(data.error||'Failed to load'); return; }
                            el('assign_department_id').value = data.department.department_id;
                            el('assignTitle').innerText = 'Assign HoD to ' + data.department.department_name;
                            var sel = el('assign_employee_id'); sel.innerHTML = '<option value="">-- Select HoD --</option>';
                            data.employees.forEach(function(emp){
                                var opt = document.createElement('option'); opt.value = emp.employee_id; opt.text = emp.employee_name; if(emp.employee_id==data.current_hod_id) opt.selected = true; sel.appendChild(opt);
                            });
                            el('assignError').style.display='none';
                            el('assignModal').style.display='flex';
                        }).catch(e=>{ alert('Error fetching employees'); });
                }

                function closeAssignModal(){ el('assignModal').style.display='none'; }

                document.getElementById('assignForm').addEventListener('submit', function(e){
                    e.preventDefault();
                    var form = new FormData(this);
                    form.append('ajax','1');
                    fetch('assign_hod.php?id='+encodeURIComponent(el('assign_department_id').value), {method:'POST', body: form})
                        .then(r=>r.json())
                        .then(data=>{
                            if(!data.success){ el('assignError').innerText = data.error || 'Assign failed'; el('assignError').style.display='block'; return; }
                            // update HOD column
                            var tr = document.querySelector('tr[data-id="'+data.department_id+'"]');
                            if(tr){
                                var selected = el('assign_employee_id');
                                var name = selected.options[selected.selectedIndex].text;
                                tr.querySelector('.dept-hod').innerHTML = name || '<span style="color:#d0342a">Not Assigned</span>';
                            }
                            closeAssignModal();
                        }).catch(e=>{ el('assignError').innerText='Server error'; el('assignError').style.display='block'; });
                });

                /* Add Department modal control */
                function openAddModal(){
                    el('add_department_name').value = '';
                    el('addError').style.display = 'none';
                    el('addModal').style.display = 'flex';
                }

                function closeAddModal(){ el('addModal').style.display='none'; }

                document.getElementById('addForm').addEventListener('submit', function(e){
                    e.preventDefault();
                    var form = new FormData(this);
                    form.append('ajax','1');
                    fetch('add_department.php', {method:'POST', body: form})
                        .then(r=>r.json())
                        .then(data=>{
                            if(!data.success){ el('addError').innerText = data.error || 'Add failed'; el('addError').style.display='block'; return; }
                            // insert new row at top of table
                            var tbody = document.querySelector('table.users tbody');
                            var tr = document.createElement('tr');
                            tr.setAttribute('data-id', data.department_id);
                            tr.setAttribute('data-name', data.department_name);
                            tr.setAttribute('data-status', data.status);
                            tr.setAttribute('data-hod-name', '');

                            var tdName = document.createElement('td'); tdName.className = 'dept-name'; tdName.textContent = data.department_name;
                            var tdHod = document.createElement('td'); tdHod.className = 'dept-hod'; tdHod.innerHTML = '<span style="color:#d0342a">Not Assigned</span>';
                            var tdStatus = document.createElement('td'); tdStatus.className = 'dept-status'; tdStatus.innerHTML = (data.status==='active')?'<span class="status-active">Active</span>':'<span class="status-inactive">Inactive</span>';
                            var tdActions = document.createElement('td');
                            tdActions.innerHTML = '<a class="action-orange" href="#" onclick="openEditModal('+data.department_id+');return false;">Edit</a> | <a class="action-orange" href="#" onclick="openAssignModal('+data.department_id+');return false;">Assign HOD</a> | <a class="action-orange" href="toggle_department_status.php?id='+encodeURIComponent(data.department_id)+'">'+(data.status==='active'?'Deactivate':'Activate')+'</a>';

                            tr.appendChild(tdName); tr.appendChild(tdHod); tr.appendChild(tdStatus); tr.appendChild(tdActions);
                            if(tbody.firstChild) tbody.insertBefore(tr, tbody.firstChild); else tbody.appendChild(tr);
                            closeAddModal();
                        }).catch(e=>{ el('addError').innerText='Server error'; el('addError').style.display='block'; });
                });
                </script>
</body>
</html>

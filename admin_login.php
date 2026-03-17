<?php
session_start();

// Handle POST login here so we don't need admin_auth.php as a separate file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include __DIR__ . '/database/database.php';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $_SESSION['login_error'] = 'Please enter username and password.';
        header('Location: admin_login.php');
        exit;
    }

    if (empty($conn)) {
        $_SESSION['login_error'] = 'Database connection unavailable.';
        header('Location: admin_login.php');
        exit;
    }

    try {
        // Try singular `admin` table first; fall back to `admins` if the table/name differs.
        $admin = null;
        $adminTable = null;
        try {
            $stmt = $conn->prepare('SELECT admin_id AS id, username, password, admin_name AS name FROM admin WHERE LOWER(username) = LOWER(:u) LIMIT 1');
            $stmt->execute([':u' => $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            $adminTable = 'admin';
        } catch (PDOException $e) {
            // fallback to older/misnamed table
            try {
                $stmt = $conn->prepare('SELECT admin_id AS id, username, password, admin_name AS name FROM admins WHERE LOWER(username) = LOWER(:u) LIMIT 1');
                $stmt->execute([':u' => $username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                $adminTable = 'admins';
            } catch (PDOException $e2) {
                throw $e2; // rethrow to outer handler
            }
        }

        // Verify password allowing legacy hash formats (MD5, SHA1, SHA256, SHA512) and plain-text fallback.
        $verified = false;
        $rehashNeeded = false;
        if ($admin && isset($admin['password'])) {
            $stored = (string)$admin['password'];

            if (password_verify($password, $stored)) {
                $verified = true;
                // if current hash algorithm is outdated for password_hash(), rehash
                if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                    $rehashNeeded = true;
                }
            } else {
                // legacy checks
                if (hash_equals($stored, md5($password))) {
                    $verified = true; $rehashNeeded = true;
                } elseif (hash_equals($stored, sha1($password))) {
                    $verified = true; $rehashNeeded = true;
                } elseif (hash_equals($stored, hash('sha256', $password))) {
                    $verified = true; $rehashNeeded = true;
                } elseif (hash_equals($stored, hash('sha512', $password))) {
                    $verified = true; $rehashNeeded = true;
                } elseif (hash_equals($stored, $password)) {
                    // plain-text stored (last resort)
                    $verified = true; $rehashNeeded = true;
                }
            }
        }

        if ($verified) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'] ?? null;
            $_SESSION['admin_user'] = $admin['username'] ?? null;
            $_SESSION['admin_name'] = $admin['name'] ?? $admin['username'] ?? null;

            // If we matched via a legacy hash or need rehash, upgrade to password_hash()
            if ($rehashNeeded && $adminTable) {
                try {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    // whitelist table names to avoid injection
                    $allowed = ['admin','admins'];
                    if (in_array($adminTable, $allowed, true)) {
                        $upd = $conn->prepare("UPDATE {$adminTable} SET password = :ph WHERE LOWER(username) = LOWER(:u)");
                        $upd->execute([':ph' => $newHash, ':u' => $username]);
                    }
                } catch (Exception $e) {
                    error_log('Failed to rehash admin password: ' . $e->getMessage());
                }
            }

            header('Location: admin_dashboard.php');
            exit;
        }

        // Log why the login failed (do not log plaintext passwords)
        if ($admin) {
            error_log("Admin login failed for username '{$username}': password verification failed for id={$admin['id']}.");
        } else {
            error_log("Admin login failed for username '{$username}': no matching admin record found.");
        }

        $_SESSION['login_error'] = 'Invalid username or password.';
        header('Location: admin_login.php');
        exit;

    } catch (Exception $e) {
        error_log('Admin login error: ' . $e->getMessage());
        $_SESSION['login_error'] = 'Login failed.';
        header('Location: admin_login.php');
        exit;
    }
}

$login_error = '';
if (!empty($_SESSION['login_error'])) {
    $login_error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Admin Login — Attendance App</title>
    <link rel="stylesheet" href="css/Login.css">
    <style>
        :root{--bg:#0f1724;--card:#0b1220;--accent:#6ee7b7;--muted:#9aa4b2}
        *{box-sizing:border-box}
        html,body{height:100%;margin:0;font-family:Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial}
        body{background:linear-gradient(135deg,#071029 0%, #09203f 50%, #0b1220 100%);color:#e6eef6;display:flex;align-items:center;justify-content:center;padding:24px}
        .wrap{width:100%;max-width:980px;display:grid;grid-template-columns:1fr 420px;gap:40px;align-items:center}
        .hero{padding:40px;border-radius:12px;color:var(--accent);background:linear-gradient(180deg, rgba(255,255,255,0.03), transparent);box-shadow:0 10px 30px rgba(2,6,23,0.6)}
            /* increased sizes for the two login page boxes */
            .wrap{width:100%;max-width:1120px;display:grid;grid-template-columns:1fr 520px;gap:40px;align-items:center}
            .hero{padding:48px;border-radius:12px;color:var(--accent);background:linear-gradient(180deg, rgba(255,255,255,0.03), transparent);box-shadow:0 10px 30px rgba(2,6,23,0.6)}
        .hero h1{margin:0 0 12px;font-size:24px;line-height:1;color:#fff}
        .hero p{margin:0;color:var(--muted)}

        .card{background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));padding:28px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.6);backdrop-filter: blur(6px);}
            .card{background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));padding:36px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.6);backdrop-filter: blur(6px);min-height:450px}
        .brand{display:flex;flex-direction:column;align-items:center;text-align:center;gap:6px;margin-bottom:18px}
        h2{margin:0;font-size:26px;color:#fff}
        .desc{color:var(--muted);font-size:14px;margin-top:0}

        .form-row{margin-top:18px;max-width:760px;margin-left:auto;margin-right:auto}
        label{display:block;font-size:14px;color:var(--muted);margin-bottom:10px}
        .input{display:flex;align-items:center;background:#061222;border:1px solid rgba(255,255,255,0.04);padding:12px 14px;border-radius:10px}
        .input input{background:transparent;border:0;outline:0;color:#e6eef6;font-size:16px;width:100%;line-height:1.4;padding:6px 0}

        .actions{display:flex;gap:14px;margin-top:22px;justify-content:space-between;align-items:center}
        .btn{flex:0 0 auto;padding:14px 22px;border-radius:12px;border:0;cursor:pointer;font-weight:600;font-size:16px}
        .btn-primary{background:linear-gradient(90deg,#3b82f6,#6ee7b7);color:#042028;min-width:220px}
        .btn-ghost{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.12);color:#cfe8ef;min-width:180px;text-align:center;padding:12px 20px;border-radius:12px;transition:background .12s,border-color .12s,color .12s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
        .btn-ghost:hover{background:rgba(255,255,255,0.04);border-color:rgba(255,255,255,0.2);color:#ffffff;text-decoration:none}
        .btn-ghost:focus{outline:none;box-shadow:0 8px 24px rgba(2,6,23,0.45)}

        .error{margin-top:12px;background:rgba(176,0,32,0.08);color:#ffb3b3;padding:10px;border-radius:8px;font-size:14px}
        .meta{margin-top:12px;color:var(--muted);font-size:13px}

        .small{font-size:13px;color:var(--muted)}
        @media (max-width:900px){.wrap{grid-template-columns:1fr;gap:18px}.hero{order:2}.card{order:1}}
    </style>
</head>
<body>
<main class="wrap" role="main">
    <section class="hero" aria-hidden="false">
        <h1>Attendance and Leave System — Admin</h1>
        <p>Manage users, view reports and adjust system settings from the admin panel.</p>
        <div style="margin-top:18px;">
            <p class="small">Secure area. Only authorized personnel should sign in.</p>
        </div>
    </section>

    <section class="card" aria-labelledby="admin-login">
        <div class="brand">
            <div>
                <h2 id="admin-login">Admin Login</h2>
                <div class="desc">Use your admin credentials to continue</div>
            </div>
        </div>

        <?php if ($login_error): ?>
            <div class="error" role="alert"><?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>

        <form method="post" action="admin_login.php" autocomplete="off" novalidate>
            <div class="form-row">
                <label for="username">Username</label>
                <div class="input">
                    <input id="username" name="username" type="text" inputmode="text" required placeholder="Enter username" autofocus>
                </div>
            </div>

            <div class="form-row">
                <label for="password">Password</label>
                <div class="input">
                    <input id="password" name="password" type="password" required placeholder="••••••••">
                </div>
            </div>

            <div class="actions">
                <button class="btn btn-primary" type="submit">Login</button>
                <a class="btn btn-ghost" href="Login.php">Employee login</a>
            </div>
        </form>
    </section>
</main>

</body>
</html>
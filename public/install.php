<?php
$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

function testDb($host, $port, $name, $user, $pass): bool {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->query('SELECT 1');
        return true;
    } catch (Exception $e) { return false; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $host = $_POST['db_host'] ?? 'localhost';
        $port = $_POST['db_port'] ?? '3306';
        $name = $_POST['db_name'] ?? 'wontia';
        $user = $_POST['db_user'] ?? 'wontia';
        $pass = $_POST['db_pass'] ?? '';
        if (testDb($host, $port, $name, $user, $pass)) {
            file_put_contents(dirname(__DIR__) . '/.env', "APP_NAME=\"Wontia Web Intelligence\"\nAPP_URL=http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\nDB_HOST=$host\nDB_PORT=$port\nDB_NAME=$name\nDB_USER=$user\nDB_PASS=$pass\nAPP_ENV=production\nAPP_DEBUG=false\nJWT_SECRET=" . bin2hex(random_bytes(32)) . "\n");
            $step = 2;
        } else { $error = 'Database connection failed'; }
    } elseif ($step === 2) {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        if (!$username || !$email || !$password) { $error = 'All fields required'; } else {
            $envFile = dirname(__DIR__) . '/.env';
            $env = file_get_contents($envFile);
            preg_match('/DB_HOST=(.+)/', $env, $m); $host = trim($m[1]);
            preg_match('/DB_PORT=(.+)/', $env, $m); $port = trim($m[1]);
            preg_match('/DB_NAME=(.+)/', $env, $m); $name = trim($m[1]);
            preg_match('/DB_USER=(.+)/', $env, $m); $user = trim($m[1]);
            preg_match('/DB_PASS=(.+)/', $env, $m); $pass = trim($m[1]);
            try {
                $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $sql = file_get_contents(dirname(__DIR__) . '/install/schema.sql');
                $pdo->exec($sql);
                $seed = file_get_contents(dirname(__DIR__) . '/install/seed.sql');
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $seed = str_replace("VALUES (1, 'admin', 'admin@intsolcom.com', '\$2y\$12\$LJ3m4ys3YOlDkOmMrPJ7OOCCpN.1S3Xv7JfYMm8PbBRHxdsF3POMG', 'superadmin')", "VALUES (1, '$username', '$email', '$hash', 'superadmin')", $seed);
                $pdo->exec($seed);
                $step = 3;
                $success = 'Installation complete!';
            } catch (Exception $e) { $error = 'Installation failed: ' . $e->getMessage(); }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Wontia Web Intelligence — Install</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#0f1117;color:#e1e4ed;display:flex;align-items:center;justify-content:center;min-height:100vh}
        .install-box{background:#1a1d27;padding:48px 40px;border-radius:16px;border:1px solid #2a2d3a;width:100%;max-width:480px}
        .install-box h1{font-size:22px;font-weight:700;margin-bottom:8px}
        .install-box p{font-size:13px;color:#8b8fa3;margin-bottom:6px}
        .steps{display:flex;gap:8px;margin-bottom:24px}
        .step{flex:1;height:4px;border-radius:2px;background:#2a2d3a}
        .step.active{background:#B89EFF}
        .step.done{background:#00B87D}
        .form-group{margin-bottom:14px}
        label{font-size:11px;color:#8b8fa3;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.05em;font-weight:500}
        input{width:100%;padding:10px 12px;border-radius:6px;border:1px solid #2a2d3a;background:#0f1117;color:#e1e4ed;font-size:13px;outline:none}
        input:focus{border-color:#B89EFF}
        button{width:100%;padding:12px;border-radius:8px;border:none;background:linear-gradient(135deg,#9B8CDE,#B89EFF);color:#fff;font-size:14px;font-weight:600;cursor:pointer}
        button:hover{opacity:.9}
        .error{color:#ef4444;font-size:12px;margin-bottom:12px;padding:8px 12px;background:rgba(239,68,68,.1);border-radius:6px}
        .success{color:#00B87D;font-size:12px;margin-bottom:12px;padding:8px 12px;background:rgba(0,184,125,.1);border-radius:6px}
        .done-box{text-align:center}
        .done-box h2{font-size:20px;margin-bottom:12px;color:#00B87D}
        .done-box a{display:inline-block;margin-top:16px;padding:12px 28px;background:linear-gradient(135deg,#9B8CDE,#B89EFF);color:#fff;text-decoration:none;border-radius:8px;font-weight:600}
    </style>
</head>
<body>
<div class="install-box">
    <?php if ($step <= 2): ?>
    <h1>Wontia Install</h1>
    <p>Setup your website intelligence engine</p>
    <div class="steps">
        <div class="step <?= $step==1?'active':($step>1?'done':'') ?>"></div>
        <div class="step <?= $step==2?'active':($step>2?'done':'') ?>"></div>
        <div class="step <?= $step==3?'active':'done' ?>"></div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if ($step === 1): ?>
    <form method="post">
        <div class="form-group"><label>Database Host</label><input name="db_host" value="localhost"/></div>
        <div class="form-group"><label>Database Port</label><input name="db_port" value="3306"/></div>
        <div class="form-group"><label>Database Name</label><input name="db_name" value="wontia"/></div>
        <div class="form-group"><label>Database User</label><input name="db_user" value="wontia"/></div>
        <div class="form-group"><label>Database Password</label><input name="db_pass" type="password"/></div>
        <button type="submit">Test & Continue</button>
    </form>
    <?php elseif ($step === 2): ?>
    <form method="post">
        <input type="hidden" name="step" value="2"/>
        <div class="form-group"><label>Admin Username</label><input name="username" required/></div>
        <div class="form-group"><label>Admin Email</label><input name="email" type="email" required/></div>
        <div class="form-group"><label>Admin Password</label><input name="password" type="password" required/></div>
        <button type="submit">Create Admin & Install</button>
    </form>
    <?php elseif ($step === 3): ?>
    <div class="done-box">
        <h2>Installation Complete</h2>
        <p>Wontia Web Intelligence is ready.</p>
        <a href="/admin">Go to Admin</a>
        <a href="/" style="background:#2a2d3a;margin-left:8px">View Site</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>

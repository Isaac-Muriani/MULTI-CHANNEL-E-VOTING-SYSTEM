<?php
// =============================================================
//  VOTER LOGIN (PIN Version)
//  evoting/voter/login.php
// =============================================================
require_once '../includes/config.php';

if (isVoterLoggedIn()) redirect('dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $pin   = $_POST['pin']            ?? '';

    if (empty($email) || empty($pin)) {
        $error = 'Please enter your email and PIN.';
    } else {
        $stmt = $conn->prepare(
            "SELECT voter_id, first_name, last_name, password, status
             FROM voters WHERE email = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $voter = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$voter) {
            $error = 'Invalid email or PIN.';
        } elseif ($voter['status'] === 'suspended') {
            $error = 'Your account has been suspended. Contact the administrator.';
        } elseif (!verifyPassword($pin, $voter['password'])) {
            $error = 'Invalid email or PIN.';
            $uid = $voter['voter_id']; $type = 'voter'; $stat = 'failed'; $ip = getClientIP();
            $log = $conn->prepare("INSERT INTO login_logs (user_id,user_type,ip_address,status) VALUES (?,?,?,?)");
            $log->bind_param('isss', $uid, $type, $ip, $stat);
            $log->execute(); $log->close();
        } else {
            $_SESSION['voter_id']         = $voter['voter_id'];
            $_SESSION['voter_first_name'] = $voter['first_name'];
            $_SESSION['voter_last_name']  = $voter['last_name'];
            $uid = $voter['voter_id']; $type = 'voter'; $stat = 'success'; $ip = getClientIP();
            $log = $conn->prepare("INSERT INTO login_logs (user_id,user_type,ip_address,status) VALUES (?,?,?,?)");
            $log->bind_param('isss', $uid, $type, $ip, $stat);
            $log->execute(); $log->close();
            redirect('dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Login — E-Voting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0a0f1e; --surface:#111827; --border:#1e2d45; --accent:#00d4ff; --accent2:#7c3aed; --text:#e2e8f0; --muted:#64748b; --error:#f87171; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Sora',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; background-image:radial-gradient(ellipse at 20% 20%,rgba(0,212,255,0.06) 0%,transparent 50%),radial-gradient(ellipse at 80% 80%,rgba(124,58,237,0.06) 0%,transparent 50%); }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:2.5rem; width:100%; max-width:420px; box-shadow:0 25px 60px rgba(0,0,0,0.5); }
        .logo { display:flex; align-items:center; gap:.75rem; margin-bottom:2rem; }
        .logo-icon { width:40px; height:40px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; }
        .logo-text { font-size:1.1rem; font-weight:700; } .logo-text span { color:var(--accent); }
        h1 { font-size:1.6rem; font-weight:700; margin-bottom:.4rem; letter-spacing:-.03em; }
        .subtitle { color:var(--muted); font-size:.875rem; margin-bottom:2rem; }
        .alert-error { background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); color:var(--error); padding:.875rem 1rem; border-radius:8px; margin-bottom:1.5rem; font-size:.875rem; }
        .field { margin-bottom:1.25rem; }
        label { display:block; font-size:.8rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; }
        input { width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:.75rem 1rem; color:var(--text); font-family:'Sora',sans-serif; font-size:.9rem; transition:border-color .2s; outline:none; }
        input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,212,255,0.1); }
        .pin-input { font-family:'JetBrains Mono',monospace; font-size:1.4rem; letter-spacing:.5em; text-align:center; }
        .pin-dots { display:flex; justify-content:center; gap:.75rem; margin-bottom:.5rem; }
        .pin-dot { width:14px; height:14px; border-radius:50%; background:var(--border); transition:background .2s; }
        .pin-dot.filled { background:var(--accent); }
        .btn { width:100%; padding:.875rem; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; border:none; border-radius:8px; font-family:'Sora',sans-serif; font-size:1rem; font-weight:600; cursor:pointer; margin-top:.5rem; transition:opacity .2s; }
        .btn:hover { opacity:.9; }
        .links { display:flex; justify-content:space-between; margin-top:1.5rem; font-size:.875rem; color:var(--muted); }
        .links a { color:var(--accent); text-decoration:none; font-weight:600; }
        .admin-link { text-align:center; margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid var(--border); font-size:.8rem; color:var(--muted); font-family:'JetBrains Mono',monospace; }
        .admin-link a { color:var(--muted); text-decoration:none; }
        .admin-link a:hover { color:var(--accent); }
        .pin-hint { font-size:.75rem; color:var(--muted); margin-top:.4rem; text-align:center; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-icon">🗳️</div>
        <div class="logo-text">E<span>Vote</span> System</div>
    </div>
    <h1>Welcome Back</h1>
    <p class="subtitle">Login with your email and 4-digit PIN</p>

    <?php if ($error): ?><div class="alert-error"><?= $error ?></div><?php endif; ?>

    <form method="POST">
        <div class="field">
            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="john@example.com" required autofocus>
        </div>
        <div class="field">
            <label>4-Digit PIN</label>
            <div class="pin-dots">
                <div class="pin-dot" id="dot1"></div>
                <div class="pin-dot" id="dot2"></div>
                <div class="pin-dot" id="dot3"></div>
                <div class="pin-dot" id="dot4"></div>
            </div>
            <input type="password" name="pin" id="pin"
                   class="pin-input" maxlength="4"
                   placeholder="••••" required
                   inputmode="numeric"
                   oninput="this.value=this.value.replace(/[^0-9]/g,'');updateDots()">
            <div class="pin-hint">Enter your 4-digit PIN</div>
        </div>
        <button type="submit" class="btn">Login →</button>
    </form>

    <div class="links">
        <span>New voter? <a href="register.php">Register here</a></span>
        <a href="forgot_password.php">Forgot PIN?</a>
    </div>

    <div class="admin-link"><a href="../admin/login.php">→ Admin Panel</a></div>
</div>
<script>
function updateDots() {
    const val = document.getElementById('pin').value;
    for (let i = 1; i <= 4; i++) {
        document.getElementById('dot' + i).classList.toggle('filled', i <= val.length);
    }
}
</script>
</body>
</html>

<?php
// =============================================================
//  FORGOT PIN (PIN Version)
//  evoting/voter/forgot_password.php
// =============================================================
require_once '../includes/config.php';
require_once '../includes/otp.php';

if (isVoterLoggedIn()) redirect('dashboard.php');

$step = $_SESSION['reset_step'] ?? 1;
$msg  = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'find_voter') {
        $email = sanitize($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter a valid email address.';
        } else {
            $stmt = $conn->prepare("SELECT voter_id, first_name, phone FROM voters WHERE email=? AND deleted_at IS NULL AND status='active'");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $voter = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$voter) {
                $err = 'No active account found with that email address.';
            } else {
                $otp = generateOTP($voter['voter_id'], $conn);
                $result = sendOTP($voter['phone'], $otp);
                $_SESSION['reset_voter_id']   = $voter['voter_id'];
                $_SESSION['reset_first_name'] = $voter['first_name'];
                $_SESSION['reset_phone']      = $voter['phone'];
                $_SESSION['reset_step']       = 2;
                $step = 2;
                $msg = $result['success']
                    ? '✅ OTP sent to your phone ending in '.substr($voter['phone'],-3).'.'
                    : '📱 [SANDBOX MODE] Your OTP is: <strong>'.$otp.'</strong>';
            }
        }
    }

    elseif ($_POST['action'] === 'verify_otp') {
        $otp_entered = sanitize($_POST['otp_code'] ?? '');
        $voter_id    = $_SESSION['reset_voter_id'] ?? 0;
        if (empty($otp_entered)) { $err = 'Please enter the OTP.'; $step = 2; }
        elseif (!$voter_id)      { $err = 'Session expired.'; $step = 1; unset($_SESSION['reset_step']); }
        elseif (verifyOTP($voter_id, $otp_entered, $conn)) { $_SESSION['reset_step'] = 3; $_SESSION['reset_verified'] = true; $step = 3; $msg = '✅ OTP verified! Now set your new PIN.'; }
        else { $err = '❌ Invalid or expired OTP.'; $step = 2; }
    }

    elseif ($_POST['action'] === 'reset_pin') {
        $voter_id = $_SESSION['reset_voter_id'] ?? 0;
        $verified = $_SESSION['reset_verified'] ?? false;
        $pin      = $_POST['pin']         ?? '';
        $confirm  = $_POST['confirm_pin'] ?? '';
        if (!$voter_id || !$verified) { $err = 'Session expired.'; $step = 1; unset($_SESSION['reset_step'],$_SESSION['reset_voter_id'],$_SESSION['reset_verified']); }
        elseif (!preg_match('/^\d{4}$/', $pin)) { $err = 'PIN must be exactly 4 digits.'; $step = 3; }
        elseif ($pin !== $confirm) { $err = 'PINs do not match.'; $step = 3; }
        else {
            $hashed = hashPassword($pin);
            $stmt   = $conn->prepare("UPDATE voters SET password=? WHERE voter_id=?");
            $stmt->bind_param('si', $hashed, $voter_id);
            if ($stmt->execute()) {
                unset($_SESSION['reset_step'],$_SESSION['reset_voter_id'],$_SESSION['reset_first_name'],$_SESSION['reset_phone'],$_SESSION['reset_verified']);
                $step = 4;
            } else { $err = 'Failed to reset PIN. Please try again.'; $step = 3; }
            $stmt->close();
        }
    }
}

$masked_phone = isset($_SESSION['reset_phone']) ? substr($_SESSION['reset_phone'],0,-6).'******' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot PIN — E-Voting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0a0f1e; --surface:#111827; --border:#1e2d45; --accent:#00d4ff; --accent2:#7c3aed; --text:#e2e8f0; --muted:#64748b; --success:#34d399; --error:#f87171; --warning:#fbbf24; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Sora',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; background-image:radial-gradient(ellipse at 20% 20%,rgba(0,212,255,0.06) 0%,transparent 50%),radial-gradient(ellipse at 80% 80%,rgba(124,58,237,0.06) 0%,transparent 50%); }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:2.5rem; width:100%; max-width:420px; box-shadow:0 25px 60px rgba(0,0,0,0.5); }
        .logo { display:flex; align-items:center; gap:.75rem; margin-bottom:2rem; }
        .logo-icon { width:40px; height:40px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; }
        .logo-text { font-size:1.1rem; font-weight:700; } .logo-text span { color:var(--accent); }
        h1 { font-size:1.5rem; font-weight:700; margin-bottom:.4rem; letter-spacing:-.03em; }
        .subtitle { color:var(--muted); font-size:.875rem; margin-bottom:2rem; }
        .alert { padding:.875rem 1rem; border-radius:8px; margin-bottom:1.5rem; font-size:.875rem; }
        .alert-success { background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:var(--success); }
        .alert-error   { background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); color:var(--error); }
        .field { margin-bottom:1.25rem; }
        label { display:block; font-size:.8rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; }
        input { width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:.75rem 1rem; color:var(--text); font-family:'Sora',sans-serif; font-size:.9rem; transition:border-color .2s; outline:none; }
        input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,212,255,0.1); }
        .pin-input { font-family:'JetBrains Mono',monospace; font-size:1.4rem; letter-spacing:.5em; text-align:center; }
        .pin-dots { display:flex; justify-content:center; gap:.75rem; margin-bottom:.5rem; }
        .pin-dot { width:14px; height:14px; border-radius:50%; background:var(--border); transition:background .2s; }
        .pin-dot.filled { background:var(--accent); }
        .otp-input { font-family:'JetBrains Mono',monospace; font-size:1.5rem; letter-spacing:.3em; text-align:center; }
        .btn { width:100%; padding:.875rem; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; border:none; border-radius:8px; font-family:'Sora',sans-serif; font-size:1rem; font-weight:600; cursor:pointer; transition:opacity .2s; margin-bottom:.75rem; }
        .btn:hover { opacity:.9; }
        .btn-outline { width:100%; padding:.75rem; background:transparent; border:1px solid var(--border); color:var(--muted); border-radius:8px; font-family:'Sora',sans-serif; font-size:.875rem; font-weight:600; cursor:pointer; transition:all .2s; text-decoration:none; display:block; text-align:center; }
        .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
        .steps { display:flex; align-items:center; justify-content:center; gap:.5rem; margin-bottom:2rem; }
        .step { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700; border:2px solid var(--border); color:var(--muted); }
        .step.active { border-color:var(--accent); color:var(--accent); background:rgba(0,212,255,0.1); }
        .step.done   { border-color:var(--success); color:#fff; background:var(--success); }
        .step-line { width:30px; height:2px; background:var(--border); }
        .step-line.done { background:var(--success); }
        .success-box { text-align:center; padding:2rem 0; }
        .success-icon { font-size:3.5rem; margin-bottom:1rem; }
        .success-title { font-size:1.3rem; font-weight:700; margin-bottom:.5rem; color:var(--success); }
        .success-sub { color:var(--muted); font-size:.875rem; margin-bottom:2rem; }
        .back-link { text-align:center; margin-top:1rem; font-size:.875rem; }
        .back-link a { color:var(--muted); text-decoration:none; }
        .back-link a:hover { color:var(--accent); }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        .pin-hint { font-size:.75rem; color:var(--muted); margin-top:.4rem; text-align:center; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-icon">🔑</div>
        <div class="logo-text">E<span>Vote</span> System</div>
    </div>

    <?php if ($step < 4): ?>
    <div class="steps">
        <div class="step <?= $step>=1?($step>1?'done':'active'):'' ?>"><?= $step>1?'✓':'1' ?></div>
        <div class="step-line <?= $step>1?'done':'' ?>"></div>
        <div class="step <?= $step>=2?($step>2?'done':'active'):'' ?>"><?= $step>2?'✓':'2' ?></div>
        <div class="step-line <?= $step>2?'done':'' ?>"></div>
        <div class="step <?= $step>=3?($step>3?'done':'active'):'' ?>"><?= $step>3?'✓':'3' ?></div>
    </div>
    <?php endif; ?>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <?php if ($step===1): ?>
        <h1>Forgot PIN?</h1>
        <p class="subtitle">Enter your registered email and we'll send an OTP to your phone.</p>
        <form method="POST">
            <input type="hidden" name="action" value="find_voter">
            <div class="field">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email']??'') ?>" placeholder="john@example.com" required autofocus>
            </div>
            <button type="submit" class="btn">📨 Send OTP →</button>
        </form>

    <?php elseif ($step===2): ?>
        <h1>Enter OTP</h1>
        <p class="subtitle">Hi <?= htmlspecialchars($_SESSION['reset_first_name']??'') ?>! Enter the OTP sent to <?= $masked_phone ?>.</p>
        <form method="POST">
            <input type="hidden" name="action" value="verify_otp">
            <div class="field">
                <label>6-Digit OTP Code</label>
                <input type="text" name="otp_code" class="otp-input" maxlength="6" placeholder="000000" autofocus autocomplete="off" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            </div>
            <button type="submit" class="btn">✅ Verify OTP →</button>
        </form>
        <a href="forgot_password.php" class="btn-outline">🔄 Resend OTP</a>

    <?php elseif ($step===3): ?>
        <h1>Set New PIN</h1>
        <p class="subtitle">OTP verified! Set your new 4-digit PIN below.</p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_pin">
            <div class="row">
                <div class="field">
                    <label>New PIN</label>
                    <div class="pin-dots">
                        <div class="pin-dot" id="dot1"></div><div class="pin-dot" id="dot2"></div>
                        <div class="pin-dot" id="dot3"></div><div class="pin-dot" id="dot4"></div>
                    </div>
                    <input type="password" name="pin" id="pin" class="pin-input" maxlength="4" placeholder="••••" required inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'');updateDots('pin','dot')">
                    <div class="pin-hint">4 digits only</div>
                </div>
                <div class="field">
                    <label>Confirm PIN</label>
                    <div class="pin-dots">
                        <div class="pin-dot" id="cdot1"></div><div class="pin-dot" id="cdot2"></div>
                        <div class="pin-dot" id="cdot3"></div><div class="pin-dot" id="cdot4"></div>
                    </div>
                    <input type="password" name="confirm_pin" id="confirm_pin" class="pin-input" maxlength="4" placeholder="••••" required inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'');updateDots('confirm_pin','cdot')">
                    <div class="pin-hint">Repeat PIN</div>
                </div>
            </div>
            <button type="submit" class="btn">🔐 Reset PIN →</button>
        </form>

    <?php elseif ($step===4): ?>
        <div class="success-box">
            <div class="success-icon">🎉</div>
            <div class="success-title">PIN Reset!</div>
            <div class="success-sub">Your PIN has been changed successfully. You can now login with your new PIN.</div>
            <a href="login.php" class="btn" style="display:block;text-decoration:none;text-align:center;">Login Now →</a>
        </div>
    <?php endif; ?>

    <?php if ($step < 4): ?>
        <div class="back-link"><a href="login.php">← Back to Login</a></div>
    <?php endif; ?>
</div>
<script>
function updateDots(inputId, dotPrefix) {
    const val = document.getElementById(inputId).value;
    for (let i = 1; i <= 4; i++) {
        document.getElementById(dotPrefix + i).classList.toggle('filled', i <= val.length);
    }
}
</script>
</body>
</html>

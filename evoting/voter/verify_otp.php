<?php
// =============================================================
//  OTP VERIFICATION PAGE
//  evoting/voter/verify_otp.php
// =============================================================
require_once '../includes/config.php';
require_once '../includes/otp.php';

if (!isVoterLoggedIn()) redirect('login.php');

$voter_id    = $_SESSION['voter_id'];
$election_id = (int)($_GET['election_id'] ?? $_SESSION['pending_election_id'] ?? 0);

if ($election_id === 0) redirect('dashboard.php');

$_SESSION['pending_election_id'] = $election_id;

// Get voter phone
$stmt = $conn->prepare("SELECT first_name, phone FROM voters WHERE voter_id = ?");
$stmt->bind_param('i', $voter_id);
$stmt->execute();
$voter = $stmt->get_result()->fetch_assoc();
$stmt->close();

$msg = ''; $err = '';

// Handle Send OTP
if (isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    $otp    = generateOTP($voter_id, $conn);
    $result = sendOTP($voter['phone'], $otp);

    if ($result['success']) {
        $msg = '✅ OTP sent to your phone number ending in ' . substr($voter['phone'], -3) . '. Valid for ' . OTP_EXPIRY . ' minutes.';
        $_SESSION['otp_sent'] = true;
    } else {
        // In sandbox, show OTP directly for testing
        $msg = '📱 [SANDBOX MODE] Your OTP is: <strong>' . $otp . '</strong>';
        $_SESSION['otp_sent'] = true;
    }
}

// Handle Verify OTP
if (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $otp_entered = sanitize($_POST['otp_code'] ?? '');

    if (empty($otp_entered)) {
        $err = 'Please enter the OTP code.';
    } elseif (verifyOTP($voter_id, $otp_entered, $conn)) {
        $_SESSION['otp_verified_' . $election_id] = true;
        redirect('vote.php?election_id=' . $election_id);
    } else {
        $err = '❌ Invalid or expired OTP. Please try again.';
    }
}

// Mask phone number for display
$masked_phone = substr($voter['phone'], 0, -6) . '******';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification — E-Voting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0a0f1e; --surface:#111827; --border:#1e2d45; --accent:#00d4ff; --accent2:#7c3aed; --text:#e2e8f0; --muted:#64748b; --success:#34d399; --error:#f87171; --warning:#fbbf24; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Sora',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; background-image:radial-gradient(ellipse at 20% 20%, rgba(0,212,255,0.06) 0%, transparent 50%),radial-gradient(ellipse at 80% 80%, rgba(124,58,237,0.06) 0%, transparent 50%); }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:2.5rem; width:100%; max-width:420px; box-shadow:0 25px 60px rgba(0,0,0,0.5); }
        .logo { display:flex; align-items:center; gap:.75rem; margin-bottom:2rem; }
        .logo-icon { width:40px; height:40px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; }
        .logo-text { font-size:1.1rem; font-weight:700; } .logo-text span { color:var(--accent); }
        h1 { font-size:1.5rem; font-weight:700; margin-bottom:.4rem; letter-spacing:-.03em; }
        .subtitle { color:var(--muted); font-size:.875rem; margin-bottom:2rem; }
        .phone-display { background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:.875rem 1rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:.75rem; }
        .phone-icon { font-size:1.2rem; }
        .phone-text { font-size:.875rem; color:var(--muted); }
        .phone-text strong { color:var(--text); font-family:'JetBrains Mono',monospace; }
        .alert { padding:.875rem 1rem; border-radius:8px; margin-bottom:1.5rem; font-size:.875rem; }
        .alert-success { background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:var(--success); }
        .alert-error   { background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); color:var(--error); }
        .field { margin-bottom:1.25rem; }
        label { display:block; font-size:.8rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; }
        input { width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:.875rem 1rem; color:var(--text); font-family:'JetBrains Mono',monospace; font-size:1.5rem; letter-spacing:.3em; text-align:center; transition:border-color .2s; outline:none; }
        input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,212,255,0.1); }
        .btn { width:100%; padding:.875rem; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; border:none; border-radius:8px; font-family:'Sora',sans-serif; font-size:1rem; font-weight:600; cursor:pointer; transition:opacity .2s; margin-bottom:.75rem; }
        .btn:hover { opacity:.9; }
        .btn-outline { width:100%; padding:.75rem; background:transparent; border:1px solid var(--border); color:var(--muted); border-radius:8px; font-family:'Sora',sans-serif; font-size:.875rem; font-weight:600; cursor:pointer; transition:all .2s; }
        .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
        .divider { height:1px; background:var(--border); margin:1.5rem 0; }
        .back-link { text-align:center; margin-top:1rem; font-size:.875rem; }
        .back-link a { color:var(--muted); text-decoration:none; }
        .back-link a:hover { color:var(--accent); }
        .timer { text-align:center; font-size:.82rem; color:var(--muted); font-family:'JetBrains Mono',monospace; margin-top:.5rem; }
        .timer span { color:var(--warning); font-weight:700; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-icon">🔐</div>
        <div class="logo-text">E<span>Vote</span> Verify</div>
    </div>

    <h1>OTP Verification</h1>
    <p class="subtitle">We'll send a one-time code to verify your identity before voting.</p>

    <div class="phone-display">
        <div class="phone-icon">📱</div>
        <div class="phone-text">
            Sending to: <strong><?= htmlspecialchars($masked_phone) ?></strong>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <?php if (!isset($_SESSION['otp_sent'])): ?>
        <!-- Step 1: Send OTP -->
        <form method="POST">
            <input type="hidden" name="action" value="send_otp">
            <button type="submit" class="btn">📨 Send OTP to My Phone</button>
        </form>
    <?php else: ?>
        <!-- Step 2: Enter OTP -->
        <form method="POST">
            <input type="hidden" name="action" value="verify_otp">
            <div class="field">
                <label>Enter 6-Digit OTP</label>
                <input type="text" name="otp_code" maxlength="6"
                       placeholder="000000" autofocus
                       oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            </div>
            <button type="submit" class="btn">✅ Verify & Proceed to Vote</button>
        </form>

        <div class="timer">OTP valid for <span><?= OTP_EXPIRY ?> minutes</span></div>

        <div class="divider"></div>

        <!-- Resend OTP -->
        <form method="POST">
            <input type="hidden" name="action" value="send_otp">
            <button type="submit" class="btn-outline">🔄 Resend OTP</button>
        </form>
    <?php endif; ?>

    <div class="back-link">
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>
</div>
</body>
</html>

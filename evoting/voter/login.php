<?php
// =============================================================
//  VOTER LOGIN
//  evoting/voter/login.php
// =============================================================
require_once '../includes/config.php';

if (isVoterLoggedIn()) redirect('dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = sanitize($_POST['email']    ?? '');
    $password = $_POST['password']          ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare(
            "SELECT voter_id, first_name, last_name, password, status
             FROM voters
             WHERE email = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $voter  = $result->fetch_assoc();
        $stmt->close();

        if (!$voter) {
            $error = 'Invalid email or password.';
        } elseif ($voter['status'] === 'suspended') {
            $error = 'Your account has been suspended. Contact the administrator.';
        } elseif (!verifyPassword($password, $voter['password'])) {
            $error = 'Invalid email or password.';

            // Log failed attempt
            $ip   = getClientIP();
            $uid  = $voter['voter_id'];
            $type = 'voter';
            $stat = 'failed';
            $log  = $conn->prepare(
                "INSERT INTO login_logs (user_id, user_type, ip_address, status)
                 VALUES (?, ?, ?, ?)"
            );
            $log->bind_param('isss', $uid, $type, $ip, $stat);
            $log->execute();
            $log->close();

        } else {
            // Successful login
            $_SESSION['voter_id']         = $voter['voter_id'];
            $_SESSION['voter_first_name'] = $voter['first_name'];
            $_SESSION['voter_last_name']  = $voter['last_name'];

            // Log success
            $ip   = getClientIP();
            $uid  = $voter['voter_id'];
            $type = 'voter';
            $stat = 'success';
            $log  = $conn->prepare(
                "INSERT INTO login_logs (user_id, user_type, ip_address, status)
                 VALUES (?, ?, ?, ?)"
            );
            $log->bind_param('isss', $uid, $type, $ip, $stat);
            $log->execute();
            $log->close();

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
        :root {
            --bg:      #0a0f1e;
            --surface: #111827;
            --border:  #1e2d45;
            --accent:  #00d4ff;
            --accent2: #7c3aed;
            --text:    #e2e8f0;
            --muted:   #64748b;
            --error:   #f87171;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background-image:
                radial-gradient(ellipse at 20% 20%, rgba(0,212,255,0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(124,58,237,0.06) 0%, transparent 50%);
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }

        .logo-text { font-size: 1.1rem; font-weight: 700; letter-spacing: -.02em; }
        .logo-text span { color: var(--accent); }

        h1 { font-size: 1.6rem; font-weight: 700; margin-bottom: .4rem; letter-spacing: -.03em; }
        .subtitle { color: var(--muted); font-size: .875rem; margin-bottom: 2rem; }

        .alert-error {
            background: rgba(248,113,113,0.1);
            border: 1px solid rgba(248,113,113,0.3);
            color: var(--error);
            padding: .875rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: .875rem;
        }

        .field { margin-bottom: 1.25rem; }

        label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: .4rem;
        }

        input {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: .75rem 1rem;
            color: var(--text);
            font-family: 'Sora', sans-serif;
            font-size: .9rem;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }

        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0,212,255,0.1);
        }

        .btn {
            width: 100%;
            padding: .875rem;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'Sora', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: .5rem;
            transition: opacity .2s, transform .1s;
        }

        .btn:hover  { opacity: .9; }
        .btn:active { transform: scale(.99); }

        .links {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
            font-size: .875rem;
            color: var(--muted);
        }

        .links a { color: var(--accent); text-decoration: none; font-weight: 600; }

        .admin-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            font-size: .8rem;
            color: var(--muted);
            font-family: 'JetBrains Mono', monospace;
        }

        .admin-link a { color: var(--muted); text-decoration: none; }
        .admin-link a:hover { color: var(--accent); }
    </style>
</head>
<body>
<div class="card">

    <div class="logo">
        <div class="logo-icon">🗳️</div>
        <div class="logo-text">E<span>Vote</span> System</div>
    </div>

    <h1>Welcome Back</h1>
    <p class="subtitle">Login to access your voter dashboard</p>

    <?php if ($error): ?>
        <div class="alert-error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="">

        <div class="field">
            <label>Email Address</label>
            <input type="email" name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="john@example.com" required autofocus>
        </div>

        <div class="field">
            <label>Password</label>
            <input type="password" name="password"
                   placeholder="Your password" required>
        </div>

        <button type="submit" class="btn">Login →</button>

    </form>

    <div class="links">
        <span>New voter? <a href="register.php">Register here</a></span>
    </div>

    <div class="admin-link">
        <a href="../admin/login.php">→ Admin Panel</a>
    </div>

</div>
</body>
</html>

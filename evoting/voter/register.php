<?php
// =============================================================
//  VOTER REGISTRATION
//  evoting/voter/register.php
// =============================================================
require_once '../includes/config.php';

// Redirect if already logged in
if (isVoterLoggedIn()) {
    redirect('dashboard.php');
}

$errors   = [];
$success  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect and sanitize inputs
    $first_name   = sanitize($_POST['first_name']   ?? '');
    $last_name    = sanitize($_POST['last_name']    ?? '');
    $gender       = sanitize($_POST['gender']       ?? '');
    $dob          = sanitize($_POST['date_of_birth']?? '');
    $national_id  = sanitize($_POST['national_id']  ?? '');
    $phone        = sanitize($_POST['phone']        ?? '');
    $email        = sanitize($_POST['email']        ?? '');
    $password     = $_POST['password']              ?? '';
    $confirm_pass = $_POST['confirm_password']      ?? '';

    // ---- Validation ----
    if (empty($first_name))  $errors[] = 'First name is required.';
    if (empty($last_name))   $errors[] = 'Last name is required.';
    if (!in_array($gender, ['male', 'female', 'other'])) $errors[] = 'Please select a valid gender.';
    if (empty($dob))         $errors[] = 'Date of birth is required.';
    if (empty($national_id)) $errors[] = 'National ID is required.';
    if (empty($phone))       $errors[] = 'Phone number is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm_pass) $errors[] = 'Passwords do not match.';

    // Age check — must be 18+
    if (!empty($dob)) {
        $birthDate = new DateTime($dob);
        $today     = new DateTime();
        $age       = $today->diff($birthDate)->y;
        if ($age < 18) $errors[] = 'You must be at least 18 years old to register.';
    }

    // Check for duplicates
    if (empty($errors)) {
        $stmt = $conn->prepare(
            "SELECT voter_id FROM voters
             WHERE national_id = ? OR phone = ? OR email = ?"
        );
        $stmt->bind_param('sss', $national_id, $phone, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = 'A voter with the same National ID, phone, or email already exists.';
        }
        $stmt->close();
    }

    // ---- Insert voter ----
    if (empty($errors)) {
        $hashed = hashPassword($password);

        $stmt = $conn->prepare(
            "INSERT INTO voters
                (first_name, last_name, gender, date_of_birth,
                 national_id, phone, email, password, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->bind_param(
            'ssssssss',
            $first_name, $last_name, $gender, $dob,
            $national_id, $phone, $email, $hashed
        );

        if ($stmt->execute()) {
            $success = 'Registration successful! You can now log in.';
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Registration — E-Voting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:       #0a0f1e;
            --surface:  #111827;
            --border:   #1e2d45;
            --accent:   #00d4ff;
            --accent2:  #7c3aed;
            --text:     #e2e8f0;
            --muted:    #64748b;
            --error:    #f87171;
            --success:  #34d399;
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
            max-width: 560px;
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

        .logo-text {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: -.02em;
        }

        .logo-text span { color: var(--accent); }

        h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: .4rem;
            letter-spacing: -.03em;
        }

        .subtitle {
            color: var(--muted);
            font-size: .875rem;
            margin-bottom: 2rem;
        }

        .alert {
            padding: .875rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: .875rem;
        }

        .alert-error {
            background: rgba(248,113,113,0.1);
            border: 1px solid rgba(248,113,113,0.3);
            color: var(--error);
        }

        .alert-success {
            background: rgba(52,211,153,0.1);
            border: 1px solid rgba(52,211,153,0.3);
            color: var(--success);
        }

        .alert ul { padding-left: 1.2rem; }
        .alert ul li { margin-bottom: .25rem; }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .field {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: .4rem;
        }

        input, select {
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

        input:focus, select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0,212,255,0.1);
        }

        select option { background: var(--surface); }

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
            letter-spacing: -.01em;
        }

        .btn:hover  { opacity: .9; }
        .btn:active { transform: scale(.99); }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .875rem;
            color: var(--muted);
        }

        .login-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .divider {
            height: 1px;
            background: var(--border);
            margin: 1.5rem 0;
        }

        .section-label {
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--accent);
            font-family: 'JetBrains Mono', monospace;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<div class="card">

    <div class="logo">
        <div class="logo-icon">🗳️</div>
        <div class="logo-text">E<span>Vote</span> System</div>
    </div>

    <h1>Create Account</h1>
    <p class="subtitle">Register to participate in elections</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= $e ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= $success ?> <a href="login.php" style="color:inherit;font-weight:700;">Login here →</a>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <div class="section-label">Personal Info</div>

        <div class="row">
            <div class="field">
                <label>First Name</label>
                <input type="text" name="first_name"
                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                       placeholder="John" required>
            </div>
            <div class="field">
                <label>Last Name</label>
                <input type="text" name="last_name"
                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                       placeholder="Doe" required>
            </div>
        </div>

        <div class="row">
            <div class="field">
                <label>Gender</label>
                <select name="gender" required>
                    <option value="">Select gender</option>
                    <option value="male"   <?= (($_POST['gender'] ?? '') === 'male')   ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= (($_POST['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                    <option value="other"  <?= (($_POST['gender'] ?? '') === 'other')  ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="field">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth"
                       value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>"
                       required>
            </div>
        </div>

        <div class="divider"></div>
        <div class="section-label">Identification</div>

        <div class="field">
            <label>National ID</label>
            <input type="text" name="national_id"
                   value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>"
                   placeholder="e.g. 19900101-12345-00001-2" required>
        </div>

        <div class="row">
            <div class="field">
                <label>Phone Number</label>
                <input type="tel" name="phone"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       placeholder="+255 7XX XXX XXX" required>
            </div>
            <div class="field">
                <label>Email Address</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="john@example.com" required>
            </div>
        </div>

        <div class="divider"></div>
        <div class="section-label">Security</div>

        <div class="row">
            <div class="field">
                <label>Password</label>
                <input type="password" name="password"
                       placeholder="Min. 8 characters" required>
            </div>
            <div class="field">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password"
                       placeholder="Repeat password" required>
            </div>
        </div>

        <button type="submit" class="btn">Create Account →</button>

    </form>

    <div class="login-link">
        Already registered? <a href="login.php">Login here</a>
    </div>

</div>
</body>
</html>

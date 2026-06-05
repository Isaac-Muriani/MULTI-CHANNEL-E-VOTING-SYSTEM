<?php
// =============================================================
//  APPLY AS CANDIDATE
//  evoting/voter/apply_candidate.php
// =============================================================
require_once '../includes/config.php';

if (!isVoterLoggedIn()) redirect('login.php');

$voter_id = $_SESSION['voter_id'];
$msg = ''; $err = '';

// Get voter details
$stmt = $conn->prepare("SELECT * FROM voters WHERE voter_id = ?");
$stmt->bind_param('i', $voter_id);
$stmt->execute();
$voter = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $position_id = (int)$_POST['position_id'];
    $manifesto   = sanitize($_POST['manifesto'] ?? '');

    if ($position_id === 0) {
        $err = 'Please select a position.';
    } elseif (empty($manifesto)) {
        $err = 'Please write your manifesto.';
    } else {
        // Check if already applied for this position
        $stmt = $conn->prepare(
            "SELECT candidate_id FROM candidates
             WHERE voter_id = ? AND position_id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('ii', $voter_id, $position_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $err = 'You have already applied for this position.';
        }
        $stmt->close();

        if (empty($err)) {
            // Handle photo upload
            $photo = 'default.png';

            if (!empty($_FILES['photo']['name'])) {
                $file      = $_FILES['photo'];
                $allowed   = ['image/jpeg', 'image/jpg', 'image/png'];
                $max_size  = 2 * 1024 * 1024; // 2MB

                if (!in_array($file['type'], $allowed)) {
                    $err = 'Only JPG and PNG images are allowed.';
                } elseif ($file['size'] > $max_size) {
                    $err = 'Image must be less than 2MB.';
                } else {
                    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'candidate_' . $voter_id . '_' . time() . '.' . $ext;
                    $dest     = '../uploads/candidates/' . $filename;

                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $photo = $filename;
                    } else {
                        $err = 'Failed to upload photo. Please try again.';
                    }
                }
            }

            if (empty($err)) {
                $stmt = $conn->prepare(
                    "INSERT INTO candidates (voter_id, position_id, manifesto, photo, status)
                     VALUES (?, ?, ?, ?, 'pending')"
                );
                $stmt->bind_param('iiss', $voter_id, $position_id, $manifesto, $photo);
                if ($stmt->execute()) {
                    $msg = 'Application submitted successfully! Please wait for admin approval.';
                } else {
                    $err = 'Submission failed. Please try again.';
                }
                $stmt->close();
            }
        }
    }
}

// Get available positions (only from active/upcoming elections)
$positions = $conn->query(
    "SELECT p.position_id, p.position_name, e.election_name, e.status
     FROM positions p
     JOIN elections e ON e.election_id = p.election_id
     WHERE e.status IN ('upcoming','active') AND e.deleted_at IS NULL
     ORDER BY e.election_name, p.position_name"
)->fetch_all(MYSQLI_ASSOC);

// Get voter's existing applications
$applications = $conn->query(
    "SELECT c.status, c.created_at, p.position_name, e.election_name
     FROM candidates c
     JOIN positions p ON p.position_id = c.position_id
     JOIN elections e ON e.election_id = p.election_id
     WHERE c.voter_id = $voter_id AND c.deleted_at IS NULL
     ORDER BY c.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply as Candidate — E-Voting System</title>
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
            --success: #34d399;
            --error:   #f87171;
            --warning: #fbbf24;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Sora',sans-serif;
            background:var(--bg); color:var(--text); min-height:100vh;
            background-image:
                radial-gradient(ellipse at 10% 10%, rgba(0,212,255,0.05) 0%, transparent 40%),
                radial-gradient(ellipse at 90% 90%, rgba(124,58,237,0.05) 0%, transparent 40%);
        }
        nav {
            background:var(--surface); border-bottom:1px solid var(--border);
            padding:1rem 2rem; display:flex; align-items:center; justify-content:space-between;
        }
        .nav-logo { display:flex; align-items:center; gap:.6rem; font-weight:700; font-size:1rem; }
        .nav-logo-icon {
            width:32px; height:32px;
            background:linear-gradient(135deg,var(--accent),var(--accent2));
            border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.9rem;
        }
        .nav-logo span { color:var(--accent); }
        .nav-right { display:flex; align-items:center; gap:1rem; }
        .nav-link { color:var(--muted); text-decoration:none; font-size:.875rem; transition:color .2s; }
        .nav-link:hover { color:var(--accent); }
        main { max-width:700px; margin:0 auto; padding:2.5rem 1.5rem; }
        .page-title { font-size:1.5rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.3rem; }
        .page-sub { color:var(--muted); font-size:.875rem; margin-bottom:2rem; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.5rem; }
        .section-label {
            font-size:.75rem; font-weight:700; text-transform:uppercase;
            letter-spacing:.1em; color:var(--accent);
            font-family:'JetBrains Mono',monospace; margin-bottom:1rem;
        }
        .alert { padding:.875rem 1rem; border-radius:8px; margin-bottom:1.5rem; font-size:.875rem; }
        .alert-success { background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:var(--success); }
        .alert-error   { background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); color:var(--error); }
        .form-group { margin-bottom:1.25rem; }
        label {
            display:block; font-size:.8rem; font-weight:600; color:var(--muted);
            text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem;
        }
        input, select, textarea {
            width:100%; background:var(--bg); border:1px solid var(--border);
            border-radius:8px; padding:.75rem 1rem; color:var(--text);
            font-family:'Sora',sans-serif; font-size:.9rem;
            transition:border-color .2s; outline:none;
        }
        input:focus, select:focus, textarea:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,212,255,0.1); }
        textarea { resize:vertical; min-height:120px; }
        .btn {
            display:inline-block; padding:.75rem 1.75rem;
            background:linear-gradient(135deg,var(--accent),var(--accent2));
            color:#fff; border:none; border-radius:8px;
            font-family:'Sora',sans-serif; font-size:.9rem; font-weight:600;
            cursor:pointer; transition:opacity .2s; text-decoration:none;
        }
        .btn:hover { opacity:.85; }
        .photo-preview {
            width:100px; height:100px; border-radius:50%;
            object-fit:cover; border:3px solid var(--accent);
            display:none; margin-top:.75rem;
        }
        .badge {
            display:inline-block; padding:.2rem .7rem; border-radius:20px;
            font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em;
        }
        .badge-pending  { background:rgba(251,191,36,0.15);  color:var(--warning); border:1px solid rgba(251,191,36,0.3); }
        .badge-approved { background:rgba(52,211,153,0.15);  color:var(--success); border:1px solid rgba(52,211,153,0.3); }
        .badge-rejected { background:rgba(248,113,113,0.15); color:var(--error);   border:1px solid rgba(248,113,113,0.3); }
        table { width:100%; border-collapse:collapse; font-size:.875rem; }
        th { text-align:left; padding:.75rem 1rem; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); border-bottom:1px solid var(--border); font-family:'JetBrains Mono',monospace; }
        td { padding:.75rem 1rem; border-bottom:1px solid var(--border); }
        tr:last-child td { border-bottom:none; }
        .empty { text-align:center; padding:2rem; color:var(--muted); font-size:.875rem; }
        .file-hint { font-size:.75rem; color:var(--muted); margin-top:.4rem; }
    </style>
</head>
<body>
<nav>
    <div class="nav-logo">
        <div class="nav-logo-icon">🗳️</div>
        E<span>Vote</span> System
    </div>
    <div class="nav-right">
        <a href="dashboard.php" class="nav-link">← Back to Dashboard</a>
        <a href="logout.php" class="nav-link">Logout</a>
    </div>
</nav>

<main>
    <div class="page-title">Apply as Candidate</div>
    <div class="page-sub">Submit your nomination for an election position</div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <!-- Application Form -->
    <div class="card">
        <div class="section-label">Nomination Form</div>
        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label>Select Position *</label>
                <select name="position_id" required>
                    <option value="">-- Choose a position --</option>
                    <?php foreach ($positions as $p): ?>
                        <option value="<?= $p['position_id'] ?>"
                            <?= (($_POST['position_id'] ?? '') == $p['position_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['position_name']) ?> —
                            <?= htmlspecialchars($p['election_name']) ?>
                            (<?= $p['status'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Your Manifesto *</label>
                <textarea name="manifesto" placeholder="Write your manifesto — what you stand for, your plans and vision..." required><?= htmlspecialchars($_POST['manifesto'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Candidate Photo</label>
                <input type="file" name="photo" accept="image/jpeg,image/png"
                       onchange="previewPhoto(this)">
                <div class="file-hint">JPG or PNG only. Maximum 2MB. Leave empty to use default.</div>
                <img id="photo-preview" class="photo-preview" src="" alt="Preview">
            </div>

            <button type="submit" class="btn">Submit Application →</button>
        </form>
    </div>

    <!-- My Applications -->
    <div class="card">
        <div class="section-label">My Applications</div>
        <?php if (empty($applications)): ?>
            <div class="empty">You have not applied for any position yet.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Position</th><th>Election</th><th>Applied</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $a): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['position_name']) ?></strong></td>
                    <td><?= htmlspecialchars($a['election_name']) ?></td>
                    <td style="font-size:.78rem;color:var(--muted);font-family:'JetBrains Mono',monospace;"><?= date('d M Y', strtotime($a['created_at'])) ?></td>
                    <td><span class="badge badge-<?= $a['status'] ?>"><?= $a['status'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>

<script>
function previewPhoto(input) {
    const preview = document.getElementById('photo-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>

<?php
// =============================================================
//  VOTING PAGE
//  evoting/voter/vote.php
// =============================================================
require_once '../includes/config.php';

if (!isVoterLoggedIn()) redirect('login.php');

$voter_id    = $_SESSION['voter_id'];
$election_id = (int)($_GET['election_id'] ?? 0);

if ($election_id === 0) redirect('dashboard.php');

// Get election details
$stmt = $conn->prepare(
    "SELECT * FROM elections
     WHERE election_id = ? AND status = 'active' AND deleted_at IS NULL"
);
$stmt->bind_param('i', $election_id);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$election) {
    redirect('dashboard.php');
}

$msg = ''; $err = '';

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $votes_cast = 0;
    $errors     = [];

    // Get all positions for this election
    $positions_res = $conn->query(
        "SELECT position_id FROM positions WHERE election_id = $election_id"
    );

    // Get web channel id
    $channel = $conn->query(
        "SELECT channel_id FROM voting_channels WHERE channel_name = 'web'"
    )->fetch_assoc();
    $channel_id = $channel['channel_id'];

    while ($pos = $positions_res->fetch_assoc()) {
        $position_id  = $pos['position_id'];
        $post_key     = 'position_' . $position_id;
        $candidate_id = (int)($_POST[$post_key] ?? 0);

        if ($candidate_id === 0) continue; // skipped position

        // Check already voted for this position
        $stmt = $conn->prepare(
            "SELECT vote_id FROM votes WHERE voter_id = ? AND position_id = ?"
        );
        $stmt->bind_param('ii', $voter_id, $position_id);
        $stmt->execute();
        $stmt->store_result();
        $already = $stmt->num_rows > 0;
        $stmt->close();

        if ($already) continue;

        // Verify candidate belongs to this position and is approved
        $stmt = $conn->prepare(
            "SELECT candidate_id FROM candidates
             WHERE candidate_id = ? AND position_id = ? AND status = 'approved'"
        );
        $stmt->bind_param('ii', $candidate_id, $position_id);
        $stmt->execute();
        $stmt->store_result();
        $valid = $stmt->num_rows > 0;
        $stmt->close();

        if (!$valid) {
            $errors[] = "Invalid candidate selection for position $position_id.";
            continue;
        }

        // Cast vote
        $stmt = $conn->prepare(
            "INSERT INTO votes (voter_id, candidate_id, position_id, channel_id)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('iiii', $voter_id, $candidate_id, $position_id, $channel_id);
        if ($stmt->execute()) {
            $votes_cast++;
        }
        $stmt->close();
    }

    if (!empty($errors)) {
        $err = implode(' ', $errors);
    } elseif ($votes_cast > 0) {
        $msg = "✅ Your votes have been cast successfully! Thank you for voting.";
    } else {
        $err = "No new votes were cast. You may have already voted for all positions.";
    }
}

// Get positions and their approved candidates
$positions = $conn->query(
    "SELECT p.position_id, p.position_name
     FROM positions p
     WHERE p.election_id = $election_id
     ORDER BY p.position_name"
)->fetch_all(MYSQLI_ASSOC);

// For each position, get candidates and check if voter already voted
foreach ($positions as &$pos) {
    $pid = $pos['position_id'];

    // Already voted?
    $stmt = $conn->prepare(
        "SELECT candidate_id FROM votes WHERE voter_id = ? AND position_id = ?"
    );
    $stmt->bind_param('ii', $voter_id, $pid);
    $stmt->execute();
    $voted = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $pos['voted_candidate_id'] = $voted ? $voted['candidate_id'] : null;
    $pos['already_voted']      = $voted ? true : false;

    // Get approved candidates
    $pos['candidates'] = $conn->query(
        "SELECT c.candidate_id, c.manifesto, c.photo,
                v.first_name, v.last_name
         FROM candidates c
         JOIN voters v ON v.voter_id = c.voter_id
         WHERE c.position_id = $pid AND c.status = 'approved' AND c.deleted_at IS NULL
         ORDER BY v.last_name"
    )->fetch_all(MYSQLI_ASSOC);
}
unset($pos);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote — <?= htmlspecialchars($election['election_name']) ?></title>
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
            position:sticky; top:0; z-index:100;
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
        main { max-width:800px; margin:0 auto; padding:2.5rem 1.5rem; }
        .election-header {
            background:var(--surface); border:1px solid var(--border);
            border-radius:12px; padding:1.5rem; margin-bottom:2rem;
            border-left:4px solid var(--accent);
        }
        .election-title { font-size:1.4rem; font-weight:700; margin-bottom:.3rem; }
        .election-meta { font-size:.82rem; color:var(--muted); font-family:'JetBrains Mono',monospace; }
        .alert { padding:.875rem 1rem; border-radius:8px; margin-bottom:1.5rem; font-size:.875rem; }
        .alert-success { background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:var(--success); }
        .alert-error   { background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); color:var(--error); }

        /* Position Section */
        .position-section {
            background:var(--surface); border:1px solid var(--border);
            border-radius:12px; padding:1.5rem; margin-bottom:1.5rem;
        }
        .position-title {
            font-size:1.05rem; font-weight:700; margin-bottom:1.25rem;
            display:flex; align-items:center; gap:.75rem;
        }
        .position-number {
            width:28px; height:28px;
            background:linear-gradient(135deg,var(--accent),var(--accent2));
            border-radius:50%; display:flex; align-items:center; justify-content:center;
            font-size:.75rem; font-weight:700; color:#fff; flex-shrink:0;
        }
        .voted-notice {
            display:flex; align-items:center; gap:.5rem;
            padding:.75rem 1rem; border-radius:8px;
            background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3);
            color:var(--success); font-size:.875rem; font-weight:600;
        }

        /* Candidate Cards Grid */
        .candidates-grid {
            display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr));
            gap:1rem;
        }
        .candidate-card {
            position:relative; cursor:pointer;
        }
        .candidate-card input[type="radio"] {
            position:absolute; opacity:0; width:0; height:0;
        }
        .candidate-label {
            display:flex; flex-direction:column; align-items:center;
            padding:1.25rem 1rem; border:2px solid var(--border);
            border-radius:12px; cursor:pointer; transition:all .2s;
            text-align:center; background:var(--bg);
        }
        .candidate-card input[type="radio"]:checked + .candidate-label {
            border-color:var(--accent);
            background:rgba(0,212,255,0.06);
            box-shadow:0 0 0 3px rgba(0,212,255,0.15);
        }
        .candidate-label:hover {
            border-color:var(--accent);
            background:rgba(0,212,255,0.04);
        }
        .candidate-photo {
            width:72px; height:72px; border-radius:50%;
            object-fit:cover; margin-bottom:.75rem;
            border:2px solid var(--border);
        }
        .candidate-name { font-size:.875rem; font-weight:700; margin-bottom:.3rem; }
        .candidate-manifesto {
            font-size:.72rem; color:var(--muted); line-height:1.4;
            display:-webkit-box; -webkit-line-clamp:3;
            -webkit-box-orient:vertical; overflow:hidden;
        }
        .selected-check {
            position:absolute; top:.5rem; right:.5rem;
            width:22px; height:22px; border-radius:50%;
            background:var(--accent); color:#000;
            display:none; align-items:center; justify-content:center;
            font-size:.7rem; font-weight:900;
        }
        .candidate-card input[type="radio"]:checked ~ .selected-check {
            display:flex;
        }
        .no-candidates {
            text-align:center; padding:2rem; color:var(--muted); font-size:.875rem;
            border:1px dashed var(--border); border-radius:8px;
        }

        /* Submit */
        .submit-section {
            background:var(--surface); border:1px solid var(--border);
            border-radius:12px; padding:1.5rem;
            display:flex; align-items:center; justify-content:space-between;
            gap:1rem; position:sticky; bottom:1rem;
        }
        .submit-info { font-size:.875rem; color:var(--muted); }
        .submit-info strong { color:var(--text); }
        .btn-submit {
            padding:.875rem 2rem;
            background:linear-gradient(135deg,var(--accent),var(--accent2));
            color:#fff; border:none; border-radius:8px;
            font-family:'Sora',sans-serif; font-size:1rem; font-weight:700;
            cursor:pointer; transition:opacity .2s; white-space:nowrap;
        }
        .btn-submit:hover { opacity:.85; }
    </style>
</head>
<body>
<nav>
    <div class="nav-logo">
        <div class="nav-logo-icon">🗳️</div>
        E<span>Vote</span> System
    </div>
    <div class="nav-right">
        <a href="dashboard.php" class="nav-link">← Dashboard</a>
        <a href="logout.php" class="nav-link">Logout</a>
    </div>
</nav>

<main>
    <!-- Election Header -->
    <div class="election-header">
        <div class="election-title">🗳️ <?= htmlspecialchars($election['election_name']) ?></div>
        <div class="election-meta">
            Ends: <?= date('d M Y, H:i', strtotime($election['end_date'])) ?> &nbsp;|&nbsp;
            <?= count($positions) ?> position(s) to vote for
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <form method="POST" id="voteForm" onsubmit="return confirmVote()">

        <?php foreach ($positions as $i => $pos): ?>
        <div class="position-section">
            <div class="position-title">
                <div class="position-number"><?= $i+1 ?></div>
                <?= htmlspecialchars($pos['position_name']) ?>
            </div>

            <?php if ($pos['already_voted']): ?>
                <div class="voted-notice">
                    ✅ You have already voted for this position.
                </div>

            <?php elseif (empty($pos['candidates'])): ?>
                <div class="no-candidates">No approved candidates for this position yet.</div>

            <?php else: ?>
                <div class="candidates-grid">
                    <?php foreach ($pos['candidates'] as $c): ?>
                        <?php
                            $photo_path = file_exists('../uploads/candidates/'.$c['photo'])
                                ? '../uploads/candidates/'.$c['photo']
                                : '../uploads/candidates/default.png';
                            // fallback avatar if no photo
                            $photo_url = file_exists($photo_path)
                                ? $photo_path
                                : 'https://ui-avatars.com/api/?name='.urlencode($c['first_name'].' '.$c['last_name']).'&background=1e2d45&color=00d4ff&size=128';
                        ?>
                        <div class="candidate-card">
                            <input type="radio"
                                   name="position_<?= $pos['position_id'] ?>"
                                   id="c_<?= $c['candidate_id'] ?>"
                                   value="<?= $c['candidate_id'] ?>">
                            <label class="candidate-label" for="c_<?= $c['candidate_id'] ?>">
                                <img src="<?= $photo_url ?>"
                                     alt="<?= htmlspecialchars($c['first_name']) ?>"
                                     class="candidate-photo"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($c['first_name'].' '.$c['last_name']) ?>&background=1e2d45&color=00d4ff&size=128'">
                                <div class="candidate-name"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></div>
                                <?php if ($c['manifesto']): ?>
                                    <div class="candidate-manifesto"><?= htmlspecialchars($c['manifesto']) ?></div>
                                <?php endif; ?>
                            </label>
                            <div class="selected-check">✓</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- Submit Bar -->
        <div class="submit-section">
            <div class="submit-info">
                Select your preferred candidate for each position, then click <strong>Submit Votes</strong>.
                <br><span style="color:var(--warning);font-size:.78rem;">⚠️ You cannot change your vote after submission.</span>
            </div>
            <button type="submit" class="btn-submit">Submit Votes 🗳️</button>
        </div>

    </form>
</main>

<script>
function confirmVote() {
    return confirm(
        '⚠️ Are you sure you want to submit your votes?\n\nThis action CANNOT be undone.'
    );
}
</script>
</body>
</html>

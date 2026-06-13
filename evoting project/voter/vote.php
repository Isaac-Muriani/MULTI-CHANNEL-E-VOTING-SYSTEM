<?php
// =============================================================
//  VOTING PAGE (Running Mate Version)
//  evoting/voter/vote.php
// =============================================================
require_once '../includes/config.php';

if (!isVoterLoggedIn()) redirect('login.php');

$voter_id    = $_SESSION['voter_id'];
$election_id = (int)($_GET['election_id'] ?? 0);
if ($election_id === 0) redirect('dashboard.php');

$stmt = $conn->prepare("SELECT * FROM elections WHERE election_id=? AND status='active' AND deleted_at IS NULL");
$stmt->bind_param('i', $election_id);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$election) redirect('dashboard.php');

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $channel    = $conn->query("SELECT channel_id FROM voting_channels WHERE channel_name='web'")->fetch_assoc();
    $channel_id = $channel['channel_id'];
    $votes_cast = 0;

    $positions_res = $conn->query("SELECT position_id, running_mate_enabled FROM positions WHERE election_id=$election_id");

    while ($pos = $positions_res->fetch_assoc()) {
        $position_id  = $pos['position_id'];
        $post_key     = 'position_'.$position_id;
        $candidate_id = (int)($_POST[$post_key] ?? 0);
        if ($candidate_id === 0) continue;

        // Already voted?
        $stmt = $conn->prepare("SELECT vote_id FROM votes WHERE voter_id=? AND position_id=?");
        $stmt->bind_param('ii', $voter_id, $position_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) { $stmt->close(); continue; }
        $stmt->close();

        if ($pos['running_mate_enabled']) {
            // For running mate positions — candidate_id is the PRESIDENTIAL candidate
            // Verify it's a valid presidential candidate (not a running mate)
            $stmt = $conn->prepare(
                "SELECT c.candidate_id, c.running_mate_id FROM candidates c
                 WHERE c.candidate_id=? AND c.position_id=? AND c.status='approved' AND c.is_running_mate=0"
            );
            $stmt->bind_param('ii', $candidate_id, $position_id);
            $stmt->execute();
            $pres = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$pres) continue;

            // Cast vote for presidential candidate
            $stmt = $conn->prepare("INSERT INTO votes (voter_id,candidate_id,position_id,channel_id) VALUES (?,?,?,?)");
            $stmt->bind_param('iiii', $voter_id, $candidate_id, $position_id, $channel_id);
            $stmt->execute(); $stmt->close();

            // Also cast vote for running mate if exists
            if ($pres['running_mate_id']) {
                $rm_id = $pres['running_mate_id'];
                // Get running mate's position_id (same position)
                $stmt = $conn->prepare("INSERT INTO votes (voter_id,candidate_id,position_id,channel_id) VALUES (?,?,?,?)");
                $stmt->bind_param('iiii', $voter_id, $rm_id, $position_id, $channel_id);
                $stmt->execute(); $stmt->close();
            }
            $votes_cast++;
        } else {
            // Normal vote
            $stmt = $conn->prepare("SELECT candidate_id FROM candidates WHERE candidate_id=? AND position_id=? AND status='approved'");
            $stmt->bind_param('ii', $candidate_id, $position_id);
            $stmt->execute(); $stmt->store_result();
            $valid = $stmt->num_rows > 0; $stmt->close();
            if (!$valid) continue;

            $stmt = $conn->prepare("INSERT INTO votes (voter_id,candidate_id,position_id,channel_id) VALUES (?,?,?,?)");
            $stmt->bind_param('iiii', $voter_id, $candidate_id, $position_id, $channel_id);
            $stmt->execute(); $stmt->close();
            $votes_cast++;
        }
    }

    $votes_cast > 0 ? $msg = '✅ Your votes have been cast successfully! Thank you for voting.' : $err = 'No new votes were cast. You may have already voted for all positions.';
}

// Get positions
$positions = $conn->query(
    "SELECT * FROM positions WHERE election_id=$election_id ORDER BY position_name"
)->fetch_all(MYSQLI_ASSOC);

foreach ($positions as &$pos) {
    $pid = $pos['position_id'];

    // Already voted?
    $stmt = $conn->prepare("SELECT candidate_id FROM votes WHERE voter_id=? AND position_id=?");
    $stmt->bind_param('ii', $voter_id, $pid);
    $stmt->execute();
    $voted = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $pos['already_voted']      = $voted ? true : false;
    $pos['voted_candidate_id'] = $voted ? $voted['candidate_id'] : null;

    if ($pos['running_mate_enabled']) {
        // Get TICKETS (presidential candidates with their running mates)
        $tickets = $conn->query(
            "SELECT c.candidate_id, c.photo, c.running_mate_id,
                    v.first_name, v.last_name,
                    c2.candidate_id AS rm_candidate_id, c2.photo AS rm_photo,
                    v2.first_name AS rm_first, v2.last_name AS rm_last
             FROM candidates c
             JOIN voters v ON v.voter_id = c.voter_id
             LEFT JOIN candidates c2 ON c2.candidate_id = c.running_mate_id
             LEFT JOIN voters v2 ON v2.voter_id = c2.voter_id
             WHERE c.position_id=$pid AND c.status='approved'
               AND c.is_running_mate=0 AND c.deleted_at IS NULL
             ORDER BY v.last_name"
        )->fetch_all(MYSQLI_ASSOC);
        $pos['tickets'] = $tickets;
    } else {
        // Normal candidates
        $pos['candidates'] = $conn->query(
            "SELECT c.candidate_id, c.manifesto, c.photo, v.first_name, v.last_name
             FROM candidates c JOIN voters v ON v.voter_id=c.voter_id
             WHERE c.position_id=$pid AND c.status='approved' AND c.deleted_at IS NULL
             ORDER BY v.last_name"
        )->fetch_all(MYSQLI_ASSOC);
    }
}
unset($pos);

function photoUrl($photo, $name) {
    if (!empty($photo) && $photo !== 'default.png' && file_exists('../uploads/candidates/'.$photo)) {
        return '../uploads/candidates/'.$photo;
    }
    return 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=1e2d45&color=00d4ff&size=128';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote — <?= htmlspecialchars($election['election_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0a0f1e; --surface:#111827; --border:#1e2d45; --accent:#00d4ff; --accent2:#7c3aed; --text:#e2e8f0; --muted:#64748b; --success:#34d399; --error:#f87171; --warning:#fbbf24; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Sora',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
        nav { background:var(--surface); border-bottom:1px solid var(--border); padding:1rem 2rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
        .nav-logo { display:flex; align-items:center; gap:.6rem; font-weight:700; font-size:1rem; }
        .nav-logo-icon { width:32px; height:32px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.9rem; }
        .nav-logo span { color:var(--accent); }
        .nav-link { color:var(--muted); text-decoration:none; font-size:.875rem; }
        main { max-width:860px; margin:0 auto; padding:2.5rem 1.5rem; }
        .election-header { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-bottom:2rem; border-left:4px solid var(--accent); }
        .election-title { font-size:1.4rem; font-weight:700; margin-bottom:.3rem; }
        .election-meta { font-size:.82rem; color:var(--muted); font-family:'JetBrains Mono',monospace; }
        .alert { padding:.875rem 1rem; border-radius:8px; margin-bottom:1.5rem; font-size:.875rem; }
        .alert-success { background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:var(--success); }
        .alert-error   { background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); color:var(--error); }
        .position-section { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; }
        .position-title { font-size:1.05rem; font-weight:700; margin-bottom:1.25rem; display:flex; align-items:center; gap:.75rem; }
        .position-number { width:28px; height:28px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; color:#fff; flex-shrink:0; }
        .voted-notice { display:flex; align-items:center; gap:.5rem; padding:.75rem 1rem; border-radius:8px; background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:var(--success); font-size:.875rem; font-weight:600; }
        .no-candidates { text-align:center; padding:2rem; color:var(--muted); font-size:.875rem; border:1px dashed var(--border); border-radius:8px; }

        /* Normal candidate cards */
        .candidates-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:1rem; }
        .candidate-card { position:relative; cursor:pointer; }
        .candidate-card input[type="radio"] { position:absolute; opacity:0; width:0; height:0; }
        .candidate-label { display:flex; flex-direction:column; align-items:center; padding:1.25rem 1rem; border:2px solid var(--border); border-radius:12px; cursor:pointer; transition:all .2s; text-align:center; background:var(--bg); }
        .candidate-card input[type="radio"]:checked + .candidate-label { border-color:var(--accent); background:rgba(0,212,255,0.06); box-shadow:0 0 0 3px rgba(0,212,255,0.15); }
        .candidate-label:hover { border-color:var(--accent); background:rgba(0,212,255,0.04); }
        .candidate-photo { width:64px; height:64px; border-radius:50%; object-fit:cover; margin-bottom:.75rem; border:2px solid var(--border); }
        .candidate-name { font-size:.875rem; font-weight:700; margin-bottom:.3rem; }
        .candidate-manifesto { font-size:.72rem; color:var(--muted); line-height:1.4; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
        .selected-check { position:absolute; top:.5rem; right:.5rem; width:22px; height:22px; border-radius:50%; background:var(--accent); color:#000; display:none; align-items:center; justify-content:center; font-size:.7rem; font-weight:900; }
        .candidate-card input[type="radio"]:checked ~ .selected-check { display:flex; }

        /* TICKET cards */
        .tickets-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:1rem; }
        .ticket-card { position:relative; cursor:pointer; }
        .ticket-card input[type="radio"] { position:absolute; opacity:0; width:0; height:0; }
        .ticket-label { display:flex; flex-direction:column; padding:1.25rem; border:2px solid var(--border); border-radius:12px; cursor:pointer; transition:all .2s; background:var(--bg); }
        .ticket-card input[type="radio"]:checked + .ticket-label { border-color:var(--accent); background:rgba(0,212,255,0.06); box-shadow:0 0 0 3px rgba(0,212,255,0.15); }
        .ticket-label:hover { border-color:var(--accent); background:rgba(0,212,255,0.04); }
        .ticket-header { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--accent); font-family:'JetBrains Mono',monospace; margin-bottom:.875rem; }
        .ticket-member { display:flex; align-items:center; gap:.75rem; margin-bottom:.75rem; }
        .ticket-member:last-child { margin-bottom:0; }
        .ticket-photo { width:48px; height:48px; border-radius:50%; object-fit:cover; border:2px solid var(--border); flex-shrink:0; }
        .ticket-member-info { flex:1; }
        .ticket-role { font-size:.68rem; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; font-family:'JetBrains Mono',monospace; }
        .ticket-name { font-size:.875rem; font-weight:700; }
        .ticket-divider { height:1px; background:var(--border); margin:.75rem 0; position:relative; }
        .ticket-divider::after { content:'🤝'; position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); background:var(--bg); padding:0 .5rem; font-size:.9rem; }
        .ticket-selected-check { position:absolute; top:.75rem; right:.75rem; width:24px; height:24px; border-radius:50%; background:var(--accent); color:#000; display:none; align-items:center; justify-content:center; font-size:.75rem; font-weight:900; }
        .ticket-card input[type="radio"]:checked ~ .ticket-selected-check { display:flex; }
        .no-ticket-partner { font-size:.78rem; color:var(--warning); font-style:italic; }

        /* Submit */
        .submit-section { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; position:sticky; bottom:1rem; }
        .submit-info { font-size:.875rem; color:var(--muted); }
        .submit-info strong { color:var(--text); }
        .btn-submit { padding:.875rem 2rem; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; border:none; border-radius:8px; font-family:'Sora',sans-serif; font-size:1rem; font-weight:700; cursor:pointer; transition:opacity .2s; white-space:nowrap; }
        .btn-submit:hover { opacity:.85; }
    </style>
</head>
<body>
<nav>
    <div class="nav-logo"><div class="nav-logo-icon">🗳️</div>E<span>Vote</span> System</div>
    <div class="nav-right">
        <a href="dashboard.php" class="nav-link">← Dashboard</a>
        <a href="logout.php" class="nav-link">Logout</a>
    </div>
</nav>

<main>
    <div class="election-header">
        <div class="election-title">🗳️ <?= htmlspecialchars($election['election_name']) ?></div>
        <div class="election-meta">Ends: <?= date('d M Y, H:i', strtotime($election['end_date'])) ?> &nbsp;|&nbsp; <?= count($positions) ?> position(s)</div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <form method="POST" id="voteForm" onsubmit="return confirmVote()">
        <?php foreach ($positions as $i => $pos): ?>
        <div class="position-section">
            <div class="position-title">
                <div class="position-number"><?= $i+1 ?></div>
                <?= htmlspecialchars($pos['position_name']) ?>
                <?php if ($pos['running_mate_enabled']): ?>
                    <span style="font-size:.72rem;background:rgba(0,212,255,0.1);border:1px solid rgba(0,212,255,0.3);color:var(--accent);padding:.15rem .6rem;border-radius:20px;font-family:'JetBrains Mono',monospace;font-weight:700;">TICKET SYSTEM</span>
                <?php endif; ?>
            </div>

            <?php if ($pos['already_voted']): ?>
                <div class="voted-notice">✅ You have already voted for this position.</div>

            <?php elseif ($pos['running_mate_enabled']): ?>
                <!-- TICKET VOTING -->
                <?php if (empty($pos['tickets'])): ?>
                    <div class="no-candidates">No approved tickets for this position yet.</div>
                <?php else: ?>
                    <div class="tickets-grid">
                        <?php foreach ($pos['tickets'] as $t_i => $t): ?>
                            <div class="ticket-card">
                                <input type="radio"
                                       name="position_<?= $pos['position_id'] ?>"
                                       id="ticket_<?= $t['candidate_id'] ?>"
                                       value="<?= $t['candidate_id'] ?>">
                                <label class="ticket-label" for="ticket_<?= $t['candidate_id'] ?>">
                                    <div class="ticket-header">🏛️ Ticket <?= $t_i+1 ?></div>

                                    <!-- President -->
                                    <div class="ticket-member">
                                        <img src="<?= photoUrl($t['photo'], $t['first_name'].' '.$t['last_name']) ?>"
                                             class="ticket-photo" alt="<?= htmlspecialchars($t['first_name']) ?>"
                                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($t['first_name'].' '.$t['last_name']) ?>&background=1e2d45&color=00d4ff&size=96'">
                                        <div class="ticket-member-info">
                                            <div class="ticket-role">🏛️ President</div>
                                            <div class="ticket-name"><?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?></div>
                                        </div>
                                    </div>

                                    <div class="ticket-divider"></div>

                                    <!-- Vice President -->
                                    <?php if ($t['rm_first']): ?>
                                        <div class="ticket-member">
                                            <img src="<?= photoUrl($t['rm_photo'], $t['rm_first'].' '.$t['rm_last']) ?>"
                                                 class="ticket-photo" alt="<?= htmlspecialchars($t['rm_first']) ?>"
                                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($t['rm_first'].' '.$t['rm_last']) ?>&background=1e2d45&color=7c3aed&size=96'">
                                            <div class="ticket-member-info">
                                                <div class="ticket-role">🤝 Vice President</div>
                                                <div class="ticket-name"><?= htmlspecialchars($t['rm_first'].' '.$t['rm_last']) ?></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-ticket-partner">⚠️ No running mate assigned yet</div>
                                    <?php endif; ?>
                                </label>
                                <div class="ticket-selected-check">✓</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- NORMAL VOTING -->
                <?php if (empty($pos['candidates'])): ?>
                    <div class="no-candidates">No approved candidates for this position yet.</div>
                <?php else: ?>
                    <div class="candidates-grid">
                        <?php foreach ($pos['candidates'] as $c): ?>
                            <div class="candidate-card">
                                <input type="radio" name="position_<?= $pos['position_id'] ?>" id="c_<?= $c['candidate_id'] ?>" value="<?= $c['candidate_id'] ?>">
                                <label class="candidate-label" for="c_<?= $c['candidate_id'] ?>">
                                    <img src="<?= photoUrl($c['photo'], $c['first_name'].' '.$c['last_name']) ?>"
                                         class="candidate-photo" alt="<?= htmlspecialchars($c['first_name']) ?>"
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
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="submit-section">
            <div class="submit-info">
                Select your preferred candidate or ticket for each position.<br>
                <span style="color:var(--warning);font-size:.78rem;">⚠️ You cannot change your vote after submission.</span>
            </div>
            <button type="submit" class="btn-submit">Submit Votes 🗳️</button>
        </div>
    </form>
</main>

<script>
function confirmVote() {
    return confirm('⚠️ Are you sure you want to submit your votes?\n\nThis action CANNOT be undone.');
}
</script>
</body>
</html>

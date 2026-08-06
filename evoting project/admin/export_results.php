<?php
// =============================================================
//  ELECTION RESULTS — VIEW & EXPORT
//  evoting/admin/export_results.php
// =============================================================
require_once 'auth_guard.php';

// Get all elections for the selector dropdown
$all_elections = $conn->query(
    "SELECT election_id, election_name, status FROM elections
     WHERE deleted_at IS NULL ORDER BY created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$election_id = (int)($_GET['election_id'] ?? 0);

// If no election selected, default to most recent one
if ($election_id === 0 && !empty($all_elections)) {
    $election_id = $all_elections[0]['election_id'];
}

$election = null;
$positions = [];
$total_votes = 0;
$channels = [];
$web_votes = 0;
$ussd_votes = 0;

if ($election_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM elections WHERE election_id = ? AND deleted_at IS NULL");
    $stmt->bind_param('i', $election_id);
    $stmt->execute();
    $election = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($election) {
    // Positions with results
    $positions = $conn->query(
        "SELECT p.position_id, p.position_name FROM positions p
         WHERE p.election_id = $election_id ORDER BY p.position_name"
    )->fetch_all(MYSQLI_ASSOC);

    foreach ($positions as &$pos) {
        $pid = $pos['position_id'];
        $pos['total_votes'] = $conn->query(
            "SELECT COUNT(*) c FROM votes WHERE position_id = $pid"
        )->fetch_assoc()['c'];

        // Check if this position has running mate enabled
        $rm_check = $conn->query("SELECT running_mate_enabled FROM positions WHERE position_id = $pid")->fetch_assoc();
        $pos['running_mate_enabled'] = $rm_check['running_mate_enabled'] ?? 0;

        if ($pos['running_mate_enabled']) {
            // TICKET results — show President + VP paired together
            // Only count votes on the presidential candidate (since both get voted together,
            // but we display vote_count from the president's row to avoid double counting)
            $pos['candidates'] = $conn->query(
                "SELECT c.candidate_id, v.first_name, v.last_name,
                        v2.first_name AS rm_first, v2.last_name AS rm_last,
                        COUNT(vt.vote_id) AS vote_count
                 FROM candidates c
                 JOIN voters v ON v.voter_id = c.voter_id
                 LEFT JOIN candidates c2 ON c2.candidate_id = c.running_mate_id
                 LEFT JOIN voters v2 ON v2.voter_id = c2.voter_id
                 LEFT JOIN votes vt ON vt.candidate_id = c.candidate_id
                 WHERE c.position_id = $pid AND c.status = 'approved' AND c.is_running_mate = 0
                 GROUP BY c.candidate_id ORDER BY vote_count DESC"
            )->fetch_all(MYSQLI_ASSOC);
        } else {
            // Normal candidate results
            $pos['candidates'] = $conn->query(
                "SELECT c.candidate_id, v.first_name, v.last_name,
                        NULL AS rm_first, NULL AS rm_last,
                        COUNT(vt.vote_id) AS vote_count
                 FROM candidates c
                 JOIN voters v ON v.voter_id = c.voter_id
                 LEFT JOIN votes vt ON vt.candidate_id = c.candidate_id
                 WHERE c.position_id = $pid AND c.status = 'approved'
                 GROUP BY c.candidate_id ORDER BY vote_count DESC"
            )->fetch_all(MYSQLI_ASSOC);
        }
    }
    unset($pos);

    // Total votes
    $total_votes = $conn->query(
        "SELECT COUNT(*) c FROM votes v
         JOIN positions p ON p.position_id = v.position_id
         WHERE p.election_id = $election_id"
    )->fetch_assoc()['c'];

    // Channel breakdown — Web vs USSD
    $channels = $conn->query(
        "SELECT vc.channel_name, COUNT(v.vote_id) AS total
         FROM votes v
         JOIN voting_channels vc ON vc.channel_id = v.channel_id
         JOIN positions p ON p.position_id = v.position_id
         WHERE p.election_id = $election_id
         GROUP BY vc.channel_id"
    )->fetch_all(MYSQLI_ASSOC);

    foreach ($channels as $ch) {
        if ($ch['channel_name'] === 'web')  $web_votes  = $ch['total'];
        if ($ch['channel_name'] === 'ussd') $ussd_votes = $ch['total'];
    }
}

$web_pct  = $total_votes > 0 ? round(($web_votes  / $total_votes) * 100) : 0;
$ussd_pct = $total_votes > 0 ? round(($ussd_votes / $total_votes) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results — E-Voting Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0a0f1e; --surface:#111827; --border:#1e2d45; --accent:#f59e0b; --accent2:#ef4444; --text:#e2e8f0; --muted:#64748b; --success:#34d399; --gold:#f59e0b; --webcolor:#00d4ff; --ussdcolor:#34d399; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Sora',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
        @media print {
            body { background:#fff; color:#000; }
            .no-print { display:none !important; }
            .card { border:1px solid #ccc !important; background:#fff !important; }
            .page-title, .election-title { color:#000 !important; }
            nav { display:none !important; }
        }
        main { max-width:950px; margin:0 auto; padding:2.5rem 1.5rem; }
        .page-title { font-size:1.5rem; font-weight:700; margin-bottom:.3rem; }
        .page-sub { color:var(--muted); font-size:.875rem; margin-bottom:1.5rem; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; }
        .section-label { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--accent); font-family:'JetBrains Mono',monospace; margin-bottom:1rem; }
        .election-header { text-align:center; padding:1.5rem; border-bottom:2px solid var(--border); margin-bottom:1.5rem; }
        .election-title { font-size:1.6rem; font-weight:700; margin-bottom:.5rem; }
        .election-meta { font-size:.82rem; color:var(--muted); font-family:'JetBrains Mono',monospace; }
        .stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem; }
        .stat-box { background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:1rem; text-align:center; }
        .stat-val { font-size:1.8rem; font-weight:700; color:var(--accent); }
        .stat-lbl { font-size:.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; font-family:'JetBrains Mono',monospace; }
        .position-block { margin-bottom:2rem; }
        .position-title { font-size:1.1rem; font-weight:700; margin-bottom:1rem; padding-bottom:.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; }
        .position-total { font-size:.78rem; color:var(--muted); font-family:'JetBrains Mono',monospace; font-weight:400; }
        table { width:100%; border-collapse:collapse; font-size:.875rem; }
        th { text-align:left; padding:.65rem 1rem; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); border-bottom:1px solid var(--border); font-family:'JetBrains Mono',monospace; }
        td { padding:.65rem 1rem; border-bottom:1px solid var(--border); }
        tr:last-child td { border-bottom:none; }
        .winner-row td { color:var(--gold); }
        .progress-bar { height:8px; background:var(--border); border-radius:4px; overflow:hidden; min-width:100px; }
        .progress-fill { height:100%; border-radius:4px; }
        .fill-winner { background:linear-gradient(90deg,var(--gold),#f97316); }
        .fill-other  { background:linear-gradient(90deg,#00d4ff,#7c3aed); }
        .btn-print { display:inline-block; padding:.65rem 1.4rem; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; border:none; border-radius:8px; font-family:'Sora',sans-serif; font-size:.875rem; font-weight:600; cursor:pointer; text-decoration:none; margin-right:.75rem; }
        .btn-back { display:inline-block; padding:.65rem 1.4rem; background:transparent; border:1px solid var(--border); color:var(--text); border-radius:8px; font-family:'Sora',sans-serif; font-size:.875rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all .2s; }
        .btn-back:hover { border-color:var(--accent); color:var(--accent); }
        .generated { text-align:center; font-size:.75rem; color:var(--muted); margin-top:2rem; font-family:'JetBrains Mono',monospace; }
        .empty { text-align:center; padding:3rem; color:var(--muted); border:1px dashed var(--border); border-radius:12px; font-size:.9rem; }

        /* Channel comparison */
        .channel-compare { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem; }
        .channel-box { background:var(--bg); border:1px solid var(--border); border-radius:10px; padding:1.25rem; }
        .channel-box.web  { border-left:4px solid var(--webcolor); }
        .channel-box.ussd { border-left:4px solid var(--ussdcolor); }
        .channel-icon { font-size:1.5rem; margin-bottom:.5rem; }
        .channel-name { font-size:.78rem; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; font-family:'JetBrains Mono',monospace; margin-bottom:.3rem; }
        .channel-count { font-size:2rem; font-weight:700; }
        .channel-count.web  { color:var(--webcolor); }
        .channel-count.ussd { color:var(--ussdcolor); }
        .channel-pct { font-size:.82rem; color:var(--muted); margin-top:.2rem; }
        .channel-bar-wrap { height:10px; background:var(--border); border-radius:5px; overflow:hidden; margin-top:.75rem; }
        .channel-bar { height:100%; border-radius:5px; }
        .channel-bar.web  { background:var(--webcolor); }
        .channel-bar.ussd { background:var(--ussdcolor); }

        /* Election selector */
        .election-select { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:.65rem 1rem; color:var(--text); font-family:'Sora',sans-serif; font-size:.9rem; cursor:pointer; }
    </style>
</head>
<body>
<?php require_once 'partials/navbar.php'; ?>

<main>
    <!-- Actions -->
    <div class="no-print" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <div class="page-title">Election Results</div>
            <div class="page-sub">View live results, channel breakdown, and export as PDF</div>
        </div>
        <div style="display:flex;gap:.75rem;align-items:center;">
            <form method="GET" style="display:inline;">
                <select name="election_id" class="election-select" onchange="this.form.submit()">
                    <?php foreach ($all_elections as $e): ?>
                        <option value="<?= $e['election_id'] ?>" <?= $e['election_id']==$election_id?'selected':'' ?>>
                            <?= htmlspecialchars($e['election_name']) ?> (<?= $e['status'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($election): ?>
            <button onclick="window.print()" class="btn-print">🖨️ Print / Save PDF</button>
            <?php endif; ?>
            <a href="dashboard.php" class="btn-back">← Back</a>
        </div>
    </div>

    <?php if (empty($all_elections)): ?>
        <div class="empty">🗳️ No elections created yet. Go to Elections to create one first.</div>

    <?php elseif (!$election): ?>
        <div class="empty">⚠️ Election not found.</div>

    <?php else: ?>
    <div class="card">
        <!-- Election Header -->
        <div class="election-header">
            <div style="font-size:2rem;margin-bottom:.5rem;">🗳️</div>
            <div class="election-title"><?= htmlspecialchars($election['election_name']) ?></div>
            <?php if ($election['description']): ?>
                <div style="color:var(--muted);font-size:.875rem;margin:.5rem 0;"><?= htmlspecialchars($election['description']) ?></div>
            <?php endif; ?>
            <div class="election-meta">
                <?= date('d M Y H:i', strtotime($election['start_date'])) ?> →
                <?= date('d M Y H:i', strtotime($election['end_date'])) ?>
                &nbsp;|&nbsp; Status: <?= strtoupper($election['status']) ?>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-val"><?= $total_votes ?></div>
                <div class="stat-lbl">Total Votes Cast</div>
            </div>
            <div class="stat-box">
                <div class="stat-val"><?= count($positions) ?></div>
                <div class="stat-lbl">Positions Contested</div>
            </div>
            <div class="stat-box">
                <div class="stat-val"><?= count(array_filter($positions, fn($p) => $p['total_votes'] > 0)) ?></div>
                <div class="stat-lbl">Positions Voted</div>
            </div>
        </div>

        <!-- Channel Breakdown — Web vs USSD -->
        <div class="section-label">📊 Votes by Channel (Web vs USSD)</div>
        <div class="channel-compare">
            <div class="channel-box web">
                <div class="channel-icon">🌐</div>
                <div class="channel-name">Web Voting</div>
                <div class="channel-count web"><?= $web_votes ?></div>
                <div class="channel-pct"><?= $web_pct ?>% of total votes</div>
                <div class="channel-bar-wrap">
                    <div class="channel-bar web" style="width:<?= $web_pct ?>%"></div>
                </div>
            </div>
            <div class="channel-box ussd">
                <div class="channel-icon">📱</div>
                <div class="channel-name">USSD Voting</div>
                <div class="channel-count ussd"><?= $ussd_votes ?></div>
                <div class="channel-pct"><?= $ussd_pct ?>% of total votes</div>
                <div class="channel-bar-wrap">
                    <div class="channel-bar ussd" style="width:<?= $ussd_pct ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Results by Position -->
        <div class="section-label">🏆 Results by Position</div>

        <?php if (empty($positions)): ?>
            <p style="color:var(--muted);font-size:.875rem;">No positions added to this election yet.</p>
        <?php endif; ?>

        <?php foreach ($positions as $pos): ?>
        <div class="position-block">
            <div class="position-title">
                <?= htmlspecialchars($pos['position_name']) ?>
                <span class="position-total"><?= $pos['total_votes'] ?> vote(s) cast</span>
            </div>

            <?php if (empty($pos['candidates'])): ?>
                <p style="color:var(--muted);font-size:.875rem;">No candidates.</p>
            <?php elseif ($pos['total_votes'] == 0): ?>
                <p style="color:var(--muted);font-size:.875rem;">No votes cast for this position.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>#</th><th>Candidate</th><th>Votes</th><th>Percentage</th><th>Bar</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pos['candidates'] as $rank => $c): ?>
                        <?php $pct = $pos['total_votes'] > 0 ? round(($c['vote_count'] / $pos['total_votes']) * 100) : 0; ?>
                        <tr class="<?= $rank === 0 ? 'winner-row' : '' ?>">
                            <td style="font-family:'JetBrains Mono',monospace;"><?= $rank + 1 ?><?= $rank === 0 ? ' 👑' : '' ?></td>
                            <td>
                                <strong><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></strong>
                                <?php if (!empty($c['rm_first'])): ?>
                                    <div style="font-size:.78rem;color:var(--muted);margin-top:.15rem;">
                                        🤝 VP: <?= htmlspecialchars($c['rm_first'].' '.$c['rm_last']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-family:'JetBrains Mono',monospace;font-weight:700;"><?= $c['vote_count'] ?></td>
                            <td style="font-family:'JetBrains Mono',monospace;"><?= $pct ?>%</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill <?= $rank === 0 ? 'fill-winner' : 'fill-other' ?>"
                                         style="width:<?= $pct ?>%"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="generated">
            Report generated on <?= date('d M Y \a\t H:i:s') ?> by <?= htmlspecialchars($admin_name) ?>
        </div>
    </div>
    <?php endif; ?>
</main>
</body>
</html>

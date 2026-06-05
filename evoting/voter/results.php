<?php
// =============================================================
//  ELECTION RESULTS
//  evoting/voter/results.php
// =============================================================
require_once '../includes/config.php';

if (!isVoterLoggedIn()) redirect('login.php');

$election_id = (int)($_GET['election_id'] ?? 0);
if ($election_id === 0) redirect('dashboard.php');

// Get election — results visible for active and completed
$stmt = $conn->prepare(
    "SELECT * FROM elections
     WHERE election_id = ? AND deleted_at IS NULL"
);
$stmt->bind_param('i', $election_id);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$election) redirect('dashboard.php');

// Get positions with candidates and vote counts
$positions = $conn->query(
    "SELECT p.position_id, p.position_name
     FROM positions p
     WHERE p.election_id = $election_id
     ORDER BY p.position_name"
)->fetch_all(MYSQLI_ASSOC);

foreach ($positions as &$pos) {
    $pid = $pos['position_id'];

    // Total votes for this position
    $total = $conn->query(
        "SELECT COUNT(*) c FROM votes WHERE position_id = $pid"
    )->fetch_assoc()['c'];

    $pos['total_votes'] = $total;

    // Candidates with vote counts
    $pos['candidates'] = $conn->query(
        "SELECT c.candidate_id, c.photo,
                v.first_name, v.last_name,
                COUNT(vt.vote_id) AS vote_count
         FROM candidates c
         JOIN voters v ON v.voter_id = c.voter_id
         LEFT JOIN votes vt ON vt.candidate_id = c.candidate_id
         WHERE c.position_id = $pid AND c.status = 'approved' AND c.deleted_at IS NULL
         GROUP BY c.candidate_id
         ORDER BY vote_count DESC"
    )->fetch_all(MYSQLI_ASSOC);
}
unset($pos);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results — <?= htmlspecialchars($election['election_name']) ?></title>
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
            --warning: #fbbf24;
            --gold:    #f59e0b;
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
        .nav-link { color:var(--muted); text-decoration:none; font-size:.875rem; }
        .nav-link:hover { color:var(--accent); }
        main { max-width:800px; margin:0 auto; padding:2.5rem 1.5rem; }
        .election-header {
            background:var(--surface); border:1px solid var(--border);
            border-radius:12px; padding:1.5rem; margin-bottom:2rem;
            border-left:4px solid var(--gold); text-align:center;
        }
        .election-title { font-size:1.5rem; font-weight:700; margin-bottom:.3rem; }
        .election-meta { font-size:.82rem; color:var(--muted); font-family:'JetBrains Mono',monospace; }
        .badge {
            display:inline-block; padding:.2rem .8rem; border-radius:20px;
            font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
            margin-bottom:.5rem;
        }
        .badge-active    { background:rgba(52,211,153,0.15); color:var(--success); border:1px solid rgba(52,211,153,0.3); }
        .badge-completed { background:rgba(96,165,250,0.15); color:#60a5fa; border:1px solid rgba(96,165,250,0.3); }
        .badge-upcoming  { background:rgba(251,191,36,0.15); color:var(--warning); border:1px solid rgba(251,191,36,0.3); }

        .position-section {
            background:var(--surface); border:1px solid var(--border);
            border-radius:12px; padding:1.5rem; margin-bottom:1.5rem;
        }
        .position-title {
            font-size:1.05rem; font-weight:700; margin-bottom:1.25rem;
            padding-bottom:.75rem; border-bottom:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between;
        }
        .total-votes { font-size:.78rem; color:var(--muted); font-family:'JetBrains Mono',monospace; }

        .candidate-result {
            display:flex; align-items:center; gap:1rem;
            padding:.875rem 0; border-bottom:1px solid var(--border);
        }
        .candidate-result:last-child { border-bottom:none; }

        .candidate-rank {
            width:28px; height:28px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:.8rem; font-weight:700; flex-shrink:0;
        }
        .rank-1 { background:linear-gradient(135deg,#f59e0b,#f97316); color:#fff; }
        .rank-2 { background:rgba(148,163,184,0.3); color:var(--text); }
        .rank-3 { background:rgba(180,120,60,0.3); color:#cd853f; }
        .rank-other { background:var(--border); color:var(--muted); }

        .candidate-photo {
            width:48px; height:48px; border-radius:50%;
            object-fit:cover; border:2px solid var(--border); flex-shrink:0;
        }
        .candidate-info { flex:1; min-width:0; }
        .candidate-name { font-size:.9rem; font-weight:600; margin-bottom:.2rem; }
        .candidate-name.winner { color:var(--gold); }

        .progress-wrap { margin-top:.4rem; }
        .progress-bar {
            height:6px; background:var(--border); border-radius:3px; overflow:hidden;
        }
        .progress-fill {
            height:100%; border-radius:3px;
            transition:width .8s ease;
        }
        .progress-fill.winner { background:linear-gradient(90deg,var(--gold),#f97316); }
        .progress-fill.other  { background:linear-gradient(90deg,var(--accent),var(--accent2)); }
        .progress-label {
            display:flex; justify-content:space-between;
            font-size:.72rem; color:var(--muted); margin-top:.3rem;
            font-family:'JetBrains Mono',monospace;
        }

        .winner-crown { font-size:1rem; margin-left:.4rem; }
        .no-votes { text-align:center; padding:1.5rem; color:var(--muted); font-size:.875rem; }
        .back-btn {
            display:inline-block; padding:.65rem 1.4rem;
            background:var(--surface); border:1px solid var(--border);
            color:var(--text); border-radius:8px; text-decoration:none;
            font-size:.875rem; font-weight:600; transition:all .2s; margin-bottom:2rem;
        }
        .back-btn:hover { border-color:var(--accent); color:var(--accent); }
    </style>
</head>
<body>
<nav>
    <div class="nav-logo">
        <div class="nav-logo-icon">🗳️</div>
        E<span>Vote</span> System
    </div>
    <a href="dashboard.php" class="nav-link">← Dashboard</a>
</nav>

<main>
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

    <div class="election-header">
        <div class="badge badge-<?= $election['status'] ?>"><?= $election['status'] ?></div>
        <div class="election-title">📊 <?= htmlspecialchars($election['election_name']) ?></div>
        <div class="election-meta">
            <?= date('d M Y H:i', strtotime($election['start_date'])) ?> →
            <?= date('d M Y H:i', strtotime($election['end_date'])) ?>
        </div>
    </div>

    <?php foreach ($positions as $i => $pos): ?>
    <div class="position-section">
        <div class="position-title">
            <span><?= htmlspecialchars($pos['position_name']) ?></span>
            <span class="total-votes"><?= $pos['total_votes'] ?> vote(s) cast</span>
        </div>

        <?php if (empty($pos['candidates'])): ?>
            <div class="no-votes">No candidates for this position.</div>
        <?php elseif ($pos['total_votes'] == 0): ?>
            <div class="no-votes">No votes cast yet for this position.</div>
        <?php else: ?>
            <?php foreach ($pos['candidates'] as $rank => $c): ?>
                <?php
                    $pct        = $pos['total_votes'] > 0 ? round(($c['vote_count'] / $pos['total_votes']) * 100) : 0;
                    $is_winner  = $rank === 0;
                    $rank_num   = $rank + 1;
                    $rank_class = $rank_num <= 3 ? "rank-$rank_num" : 'rank-other';
                    $photo_url  = 'https://ui-avatars.com/api/?name='.urlencode($c['first_name'].' '.$c['last_name']).'&background=1e2d45&color=00d4ff&size=128';
                    if (!empty($c['photo']) && file_exists('../uploads/candidates/'.$c['photo'])) {
                        $photo_url = '../uploads/candidates/'.$c['photo'];
                    }
                ?>
                <div class="candidate-result">
                    <div class="candidate-rank <?= $rank_class ?>"><?= $rank_num ?></div>
                    <img src="<?= $photo_url ?>" class="candidate-photo"
                         alt="<?= htmlspecialchars($c['first_name']) ?>"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($c['first_name'].' '.$c['last_name']) ?>&background=1e2d45&color=00d4ff&size=128'">
                    <div class="candidate-info">
                        <div class="candidate-name <?= $is_winner ? 'winner' : '' ?>">
                            <?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?>
                            <?= $is_winner ? '<span class="winner-crown">👑</span>' : '' ?>
                        </div>
                        <div class="progress-wrap">
                            <div class="progress-bar">
                                <div class="progress-fill <?= $is_winner ? 'winner' : 'other' ?>"
                                     style="width:<?= $pct ?>%"></div>
                            </div>
                            <div class="progress-label">
                                <span><?= $c['vote_count'] ?> vote(s)</span>
                                <span><?= $pct ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

</main>
</body>
</html>

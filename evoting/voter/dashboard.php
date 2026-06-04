<?php
// =============================================================
//  VOTER DASHBOARD (Updated — Phase 3)
//  evoting/voter/dashboard.php
// =============================================================
require_once '../includes/config.php';

if (!isVoterLoggedIn()) redirect('login.php');

$voter_id = $_SESSION['voter_id'];

$stmt = $conn->prepare("SELECT first_name, last_name, email, phone, status FROM voters WHERE voter_id = ?");
$stmt->bind_param('i', $voter_id);
$stmt->execute();
$voter = $stmt->get_result()->fetch_assoc();
$stmt->close();

$elections = $conn->query(
    "SELECT e.election_id, e.election_name, e.description,
            e.start_date, e.end_date, e.status,
            COUNT(DISTINCT p.position_id) AS total_positions,
            COUNT(DISTINCT v.position_id) AS voted_positions
     FROM elections e
     LEFT JOIN positions p ON p.election_id = e.election_id
     LEFT JOIN votes v ON v.position_id = p.position_id AND v.voter_id = $voter_id
     WHERE e.deleted_at IS NULL
     GROUP BY e.election_id
     ORDER BY FIELD(e.status,'active','upcoming','completed','cancelled'), e.start_date ASC"
)->fetch_all(MYSQLI_ASSOC);

$total_votes    = $conn->query("SELECT COUNT(*) c FROM votes WHERE voter_id = $voter_id")->fetch_assoc()['c'];
$my_nominations = $conn->query("SELECT COUNT(*) c FROM candidates WHERE voter_id = $voter_id AND deleted_at IS NULL")->fetch_assoc()['c'];
$active_count   = $conn->query("SELECT COUNT(*) c FROM elections WHERE status='active' AND deleted_at IS NULL")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — E-Voting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0a0f1e; --surface:#111827; --border:#1e2d45; --accent:#00d4ff; --accent2:#7c3aed; --text:#e2e8f0; --muted:#64748b; --success:#34d399; --warning:#fbbf24; --error:#f87171; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Sora',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; background-image:radial-gradient(ellipse at 10% 10%, rgba(0,212,255,0.05) 0%, transparent 40%),radial-gradient(ellipse at 90% 90%, rgba(124,58,237,0.05) 0%, transparent 40%); }
        nav { background:var(--surface); border-bottom:1px solid var(--border); padding:1rem 2rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
        .nav-logo { display:flex; align-items:center; gap:.6rem; font-weight:700; font-size:1rem; }
        .nav-logo-icon { width:32px; height:32px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.9rem; }
        .nav-logo span { color:var(--accent); }
        .nav-right { display:flex; align-items:center; gap:1.5rem; }
        .nav-user { font-size:.875rem; color:var(--muted); }
        .nav-user strong { color:var(--text); }
        .logout-btn { background:transparent; border:1px solid var(--border); color:var(--muted); padding:.4rem .9rem; border-radius:6px; font-family:'Sora',sans-serif; font-size:.8rem; cursor:pointer; transition:all .2s; text-decoration:none; }
        .logout-btn:hover { border-color:var(--error); color:var(--error); }
        main { max-width:960px; margin:0 auto; padding:2.5rem 1.5rem; }
        .page-title { font-size:1.5rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.3rem; }
        .page-sub { color:var(--muted); font-size:.875rem; margin-bottom:2rem; }
        .stats { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:2rem; }
        .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.25rem 1.5rem; }
        .stat-label { font-size:.75rem; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; margin-bottom:.5rem; font-family:'JetBrains Mono',monospace; }
        .stat-value { font-size:2rem; font-weight:700; letter-spacing:-.04em; }
        .c-accent { color:var(--accent); } .c-success { color:var(--success); } .c-warning { color:var(--warning); }
        .quick-actions { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:2rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
        .quick-label { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--accent); font-family:'JetBrains Mono',monospace; margin-right:.5rem; }
        .btn { display:inline-block; padding:.55rem 1.2rem; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; border-radius:8px; text-decoration:none; font-size:.82rem; font-weight:600; transition:opacity .2s; border:none; cursor:pointer; font-family:'Sora',sans-serif; }
        .btn:hover { opacity:.85; }
        .btn-outline { display:inline-block; padding:.55rem 1.2rem; background:transparent; border:1px solid var(--border); color:var(--text); border-radius:8px; text-decoration:none; font-size:.82rem; font-weight:600; transition:all .2s; }
        .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
        .section-label { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--accent); font-family:'JetBrains Mono',monospace; margin-bottom:1rem; }
        .election-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-bottom:1rem; transition:border-color .2s; }
        .election-card:hover { border-color:rgba(0,212,255,0.3); }
        .election-top { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; }
        .election-info { flex:1; }
        .election-name { font-size:1.05rem; font-weight:700; margin-bottom:.3rem; }
        .election-desc { font-size:.825rem; color:var(--muted); margin-bottom:.75rem; }
        .election-meta { display:flex; gap:1.5rem; font-size:.78rem; color:var(--muted); font-family:'JetBrains Mono',monospace; flex-wrap:wrap; }
        .election-actions { display:flex; flex-direction:column; gap:.5rem; align-items:flex-end; }
        .badge { display:inline-block; padding:.2rem .7rem; border-radius:20px; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; margin-bottom:.5rem; }
        .badge-active    { background:rgba(52,211,153,0.15); color:var(--success); border:1px solid rgba(52,211,153,0.3); }
        .badge-upcoming  { background:rgba(251,191,36,0.15); color:var(--warning); border:1px solid rgba(251,191,36,0.3); }
        .badge-completed { background:rgba(96,165,250,0.15); color:#60a5fa; border:1px solid rgba(96,165,250,0.3); }
        .badge-cancelled { background:rgba(248,113,113,0.15); color:var(--error); border:1px solid rgba(248,113,113,0.3); }
        .voted-badge { display:inline-flex; align-items:center; gap:.4rem; padding:.5rem 1rem; background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:var(--success); border-radius:8px; font-size:.82rem; font-weight:600; white-space:nowrap; }
        .disabled-btn { display:inline-block; padding:.5rem 1rem; background:var(--border); color:var(--muted); border-radius:8px; font-size:.82rem; font-weight:600; white-space:nowrap; }
        .progress-bar { height:4px; background:var(--border); border-radius:2px; margin-top:.75rem; overflow:hidden; }
        .progress-fill { height:100%; background:linear-gradient(90deg,var(--accent),var(--accent2)); border-radius:2px; }
        .empty { text-align:center; padding:3rem; color:var(--muted); background:var(--surface); border:1px dashed var(--border); border-radius:12px; font-size:.9rem; }
    </style>
</head>
<body>
<nav>
    <div class="nav-logo"><div class="nav-logo-icon">🗳️</div>E<span>Vote</span> System</div>
    <div class="nav-right">
        <div class="nav-user">Hi, <strong><?= htmlspecialchars($voter['first_name'].' '.$voter['last_name']) ?></strong></div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
<main>
    <div class="page-title">Voter Dashboard</div>
    <div class="page-sub">Welcome back, <?= htmlspecialchars($voter['first_name']) ?>!</div>

    <div class="stats">
        <div class="stat-card"><div class="stat-label">Active Elections</div><div class="stat-value c-accent"><?= $active_count ?></div></div>
        <div class="stat-card"><div class="stat-label">My Votes Cast</div><div class="stat-value c-success"><?= $total_votes ?></div></div>
        <div class="stat-card"><div class="stat-label">My Nominations</div><div class="stat-value c-warning"><?= $my_nominations ?></div></div>
    </div>

    <div class="quick-actions">
        <span class="quick-label">Quick Actions</span>
        <a href="apply_candidate.php" class="btn">🙋 Apply as Candidate</a>
        <a href="apply_candidate.php" class="btn-outline">📋 My Applications</a>
    </div>

    <div class="section-label">All Elections</div>

    <?php if (empty($elections)): ?>
        <div class="empty">🗳️ No elections available at the moment.</div>
    <?php else: ?>
        <?php foreach ($elections as $e): ?>
            <?php
                $fully_voted = $e['total_positions'] > 0 && $e['voted_positions'] >= $e['total_positions'];
                $progress    = $e['total_positions'] > 0 ? round(($e['voted_positions'] / $e['total_positions']) * 100) : 0;
            ?>
            <div class="election-card">
                <div class="election-top">
                    <div class="election-info">
                        <div class="badge badge-<?= $e['status'] ?>"><?= $e['status'] ?></div>
                        <div class="election-name"><?= htmlspecialchars($e['election_name']) ?></div>
                        <?php if ($e['description']): ?><div class="election-desc"><?= htmlspecialchars($e['description']) ?></div><?php endif; ?>
                        <div class="election-meta">
                            <span>📅 <?= date('d M Y', strtotime($e['start_date'])) ?></span>
                            <span>⏳ <?= date('d M Y', strtotime($e['end_date'])) ?></span>
                            <span>🏛️ <?= $e['total_positions'] ?> position(s)</span>
                            <?php if (in_array($e['status'], ['active','completed'])): ?>
                                <span>✅ <?= $e['voted_positions'] ?>/<?= $e['total_positions'] ?> voted</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($e['status'] === 'active' && $e['total_positions'] > 0): ?>
                            <div class="progress-bar"><div class="progress-fill" style="width:<?= $progress ?>%"></div></div>
                        <?php endif; ?>
                    </div>
                    <div class="election-actions">
                        <?php if ($e['status'] === 'active'): ?>
                            <?php if ($fully_voted): ?>
                                <div class="voted-badge">✅ Fully Voted</div>
                            <?php else: ?>
                                <a href="vote.php?election_id=<?= $e['election_id'] ?>" class="btn">Vote Now →</a>
                            <?php endif; ?>
                            <a href="results.php?election_id=<?= $e['election_id'] ?>" class="btn-outline">📊 Results</a>
                        <?php elseif ($e['status'] === 'completed'): ?>
                            <a href="results.php?election_id=<?= $e['election_id'] ?>" class="btn">📊 View Results</a>
                        <?php elseif ($e['status'] === 'upcoming'): ?>
                            <div class="disabled-btn">⏳ Not Open Yet</div>
                        <?php else: ?>
                            <div class="disabled-btn">❌ Cancelled</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>

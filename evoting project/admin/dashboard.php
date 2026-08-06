<?php
// =============================================================
//  ADMIN DASHBOARD
//  evoting/admin/dashboard.php
// =============================================================
require_once 'auth_guard.php';

// Stats
$stats = [
    'voters'     => $conn->query("SELECT COUNT(*) c FROM voters WHERE deleted_at IS NULL")->fetch_assoc()['c'],
    'elections'  => $conn->query("SELECT COUNT(*) c FROM elections WHERE deleted_at IS NULL")->fetch_assoc()['c'],
    'active'     => $conn->query("SELECT COUNT(*) c FROM elections WHERE status='active'")->fetch_assoc()['c'],
    'candidates' => $conn->query("SELECT COUNT(*) c FROM candidates WHERE deleted_at IS NULL")->fetch_assoc()['c'],
    'votes'      => $conn->query("SELECT COUNT(*) c FROM votes")->fetch_assoc()['c'],
    'pending'    => $conn->query("SELECT COUNT(*) c FROM candidates WHERE status='pending'")->fetch_assoc()['c'],
];

// Web vs USSD channel breakdown (across all elections)
$web_votes  = $conn->query(
    "SELECT COUNT(*) c FROM votes v
     JOIN voting_channels vc ON vc.channel_id = v.channel_id
     WHERE vc.channel_name = 'web'"
)->fetch_assoc()['c'];

$ussd_votes = $conn->query(
    "SELECT COUNT(*) c FROM votes v
     JOIN voting_channels vc ON vc.channel_id = v.channel_id
     WHERE vc.channel_name = 'ussd'"
)->fetch_assoc()['c'];

$channel_total = $web_votes + $ussd_votes;
$web_pct  = $channel_total > 0 ? round(($web_votes  / $channel_total) * 100) : 0;
$ussd_pct = $channel_total > 0 ? round(($ussd_votes / $channel_total) * 100) : 0;

// Recent elections
$elections = $conn->query(
    "SELECT election_name, status, start_date, end_date FROM elections
     WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

// Recent voters
$voters = $conn->query(
    "SELECT first_name, last_name, email, status, created_at FROM voters
     WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — E-Voting System</title>
</head>
<body>
<?php require_once 'partials/navbar.php'; ?>

<main>
    <?php if (isset($_SESSION['access_denied'])): ?>
        <div class="alert alert-error">🔒 <?= $_SESSION['access_denied'] ?> <?php unset($_SESSION['access_denied']); ?></div>
    <?php endif; ?>

    <div class="page-title">Admin Dashboard</div>
    <div class="page-sub">Welcome back, <?= htmlspecialchars($admin_name) ?>. Here's what's happening.</div>

    <!-- Stats Grid -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem;">
        <?php
        $cards = [
            ['label'=>'Total Voters',       'value'=>$stats['voters'],     'color'=>'#00d4ff', 'icon'=>'👥'],
            ['label'=>'Total Elections',     'value'=>$stats['elections'],  'color'=>'#f59e0b', 'icon'=>'🗳️'],
            ['label'=>'Active Elections',    'value'=>$stats['active'],     'color'=>'#34d399', 'icon'=>'✅'],
            ['label'=>'Total Candidates',    'value'=>$stats['candidates'], 'color'=>'#a78bfa', 'icon'=>'🙋'],
            ['label'=>'Total Votes Cast',    'value'=>$stats['votes'],      'color'=>'#60a5fa', 'icon'=>'📊'],
            ['label'=>'Pending Approvals',   'value'=>$stats['pending'],    'color'=>'#fbbf24', 'icon'=>'⏳'],
        ];
        foreach ($cards as $c): ?>
        <div class="card" style="padding:1.25rem 1.5rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
                <span style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-family:'JetBrains Mono',monospace;"><?= $c['label'] ?></span>
                <span style="font-size:1.2rem;"><?= $c['icon'] ?></span>
            </div>
            <div style="font-size:2rem;font-weight:700;letter-spacing:-.04em;color:<?= $c['color'] ?>;"><?= $c['value'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Actions -->
    <div class="card" style="margin-bottom:2rem;">
        <div class="section-label">Quick Actions</div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <a href="elections.php?action=create" class="btn-primary">+ New Election</a>
            <a href="positions.php?action=create" class="btn-primary">+ Add Position</a>
            <a href="candidates.php?filter=pending" class="btn-primary">⏳ Review Candidates (<?= $stats['pending'] ?>)</a>
            <a href="voters.php" class="btn-primary">👥 Manage Voters</a>
        </div>
    </div>

    <!-- Web vs USSD Channel Breakdown -->
    <div class="card" style="margin-bottom:2rem;">
        <div class="section-label">📊 Votes by Channel — Web vs USSD (All Elections)</div>
        <?php if ($channel_total === 0): ?>
            <div class="empty">No votes cast yet across any election.</div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div style="background:var(--bg);border:1px solid var(--border);border-left:4px solid #00d4ff;border-radius:10px;padding:1.25rem;">
                <div style="font-size:1.5rem;margin-bottom:.5rem;">🌐</div>
                <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-family:'JetBrains Mono',monospace;margin-bottom:.3rem;">Web Voting</div>
                <div style="font-size:2rem;font-weight:700;color:#00d4ff;"><?= $web_votes ?></div>
                <div style="font-size:.82rem;color:var(--muted);margin-top:.2rem;"><?= $web_pct ?>% of total votes</div>
                <div style="height:10px;background:var(--border);border-radius:5px;overflow:hidden;margin-top:.75rem;">
                    <div style="height:100%;background:#00d4ff;border-radius:5px;width:<?= $web_pct ?>%;"></div>
                </div>
            </div>
            <div style="background:var(--bg);border:1px solid var(--border);border-left:4px solid #34d399;border-radius:10px;padding:1.25rem;">
                <div style="font-size:1.5rem;margin-bottom:.5rem;">📱</div>
                <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-family:'JetBrains Mono',monospace;margin-bottom:.3rem;">USSD Voting</div>
                <div style="font-size:2rem;font-weight:700;color:#34d399;"><?= $ussd_votes ?></div>
                <div style="font-size:.82rem;color:var(--muted);margin-top:.2rem;"><?= $ussd_pct ?>% of total votes</div>
                <div style="height:10px;background:var(--border);border-radius:5px;overflow:hidden;margin-top:.75rem;">
                    <div style="height:100%;background:#34d399;border-radius:5px;width:<?= $ussd_pct ?>%;"></div>
                </div>
            </div>
        </div>
        <div style="text-align:right;margin-top:.75rem;">
            <a href="export_results.php" style="font-size:.82rem;color:var(--accent);text-decoration:none;font-weight:600;">View Full Results & Export →</a>
        </div>
        <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

        <!-- Recent Elections -->
        <div class="card">
            <div class="section-label">Recent Elections</div>
            <?php if (empty($elections)): ?>
                <div class="empty">No elections yet.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Name</th><th>Status</th><th>End Date</th></tr>
                </thead>
                <tbody>
                <?php foreach ($elections as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['election_name']) ?></td>
                        <td><span class="badge badge-<?= $e['status'] ?>"><?= $e['status'] ?></span></td>
                        <td style="font-size:.8rem;color:var(--muted);font-family:'JetBrains Mono',monospace;"><?= date('d M Y', strtotime($e['end_date'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent Voters -->
        <div class="card">
            <div class="section-label">Recently Registered Voters</div>
            <?php if (empty($voters)): ?>
                <div class="empty">No voters yet.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Name</th><th>Status</th><th>Joined</th></tr>
                </thead>
                <tbody>
                <?php foreach ($voters as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['first_name'].' '.$v['last_name']) ?></td>
                        <td><span class="badge badge-<?= $v['status'] ?>"><?= $v['status'] ?></span></td>
                        <td style="font-size:.8rem;color:var(--muted);font-family:'JetBrains Mono',monospace;"><?= date('d M Y', strtotime($v['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>
</main>
</body>
</html>

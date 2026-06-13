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

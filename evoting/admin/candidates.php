<?php
// =============================================================
//  MANAGE CANDIDATES
//  evoting/admin/candidates.php
// =============================================================
require_once 'auth_guard.php';

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Approve / Reject
    if (in_array($action, ['approve','reject'])) {
        $id     = (int)$_POST['candidate_id'];
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt   = $conn->prepare("UPDATE candidates SET status=? WHERE candidate_id=?");
        $stmt->bind_param('si', $status, $id);
        $stmt->execute() ? $msg = 'Candidate '.$status.'.' : $err = 'Update failed.';
        $stmt->close();
    }

    // Delete (soft)
    if ($action === 'delete') {
        $id   = (int)$_POST['candidate_id'];
        $stmt = $conn->prepare("UPDATE candidates SET deleted_at=NOW() WHERE candidate_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? $msg = 'Candidate removed.' : $err = 'Delete failed.';
        $stmt->close();
    }
}

// Filter
$filter = sanitize($_GET['filter'] ?? 'all');
$where  = "c.deleted_at IS NULL";
if ($filter === 'pending')  $where .= " AND c.status='pending'";
if ($filter === 'approved') $where .= " AND c.status='approved'";
if ($filter === 'rejected') $where .= " AND c.status='rejected'";

$candidates = $conn->query(
    "SELECT c.candidate_id, c.manifesto, c.photo, c.status,
            v.first_name, v.last_name, v.email, v.phone,
            p.position_name, e.election_name
     FROM candidates c
     JOIN voters v    ON v.voter_id    = c.voter_id
     JOIN positions p ON p.position_id = c.position_id
     JOIN elections e ON e.election_id = p.election_id
     WHERE $where
     ORDER BY c.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates — E-Voting Admin</title>
</head>
<body>
<?php require_once 'partials/navbar.php'; ?>
<main>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <div class="page-title">Manage Candidates</div>
            <div class="page-sub">Approve or reject candidate nominations</div>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <!-- Filter Tabs -->
    <div style="display:flex;gap:.5rem;margin-bottom:1.5rem;">
        <?php foreach (['all','pending','approved','rejected'] as $f): ?>
            <a href="?filter=<?= $f ?>"
               style="padding:.4rem 1rem;border-radius:6px;font-size:.82rem;font-weight:600;text-decoration:none;
                      <?= $filter===$f ? 'background:rgba(245,158,11,0.15);color:var(--accent);border:1px solid rgba(245,158,11,0.3);' : 'background:var(--surface);color:var(--muted);border:1px solid var(--border);' ?>">
                <?= ucfirst($f) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="section-label">Candidates (<?= count($candidates) ?>)</div>
        <?php if (empty($candidates)): ?>
            <div class="empty">No candidates found for this filter.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Candidate</th><th>Position</th><th>Election</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($candidates as $i => $c): ?>
                <tr>
                    <td style="color:var(--muted);font-family:'JetBrains Mono',monospace;"><?= $i+1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></strong>
                        <div style="font-size:.78rem;color:var(--muted);"><?= htmlspecialchars($c['email']) ?></div>
                        <?php if ($c['manifesto']): ?>
                            <div style="font-size:.78rem;color:var(--muted);margin-top:.2rem;font-style:italic;">"<?= htmlspecialchars(substr($c['manifesto'],0,60)) ?>..."</div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($c['position_name']) ?></td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars($c['election_name']) ?></td>
                    <td><span class="badge badge-<?= $c['status'] ?>"><?= $c['status'] ?></span></td>
                    <td>
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                            <?php if ($c['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="candidate_id" value="<?= $c['candidate_id'] ?>">
                                    <button type="submit" class="btn-sm btn-success">Approve</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="candidate_id" value="<?= $c['candidate_id'] ?>">
                                    <button type="submit" class="btn-sm btn-danger">Reject</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Remove this candidate?');" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="candidate_id" value="<?= $c['candidate_id'] ?>">
                                <button type="submit" class="btn-sm btn-warning">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>
</body>
</html>

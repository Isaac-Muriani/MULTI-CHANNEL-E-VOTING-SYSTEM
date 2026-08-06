<?php
// =============================================================
//  MANAGE VOTERS
//  evoting/admin/voters.php
// =============================================================
require_once 'auth_guard.php';

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $voter_id = (int)$_POST['voter_id'];

    if ($action === 'activate') {
        $s = 'active';
        $stmt = $conn->prepare("UPDATE voters SET status=? WHERE voter_id=?");
        $stmt->bind_param('si', $s, $voter_id);
        $stmt->execute() ? $msg = 'Voter activated.' : $err = 'Update failed.';
        $stmt->close();
    }

    if ($action === 'suspend') {
        $s = 'suspended';
        $stmt = $conn->prepare("UPDATE voters SET status=? WHERE voter_id=?");
        $stmt->bind_param('si', $s, $voter_id);
        $stmt->execute() ? $msg = 'Voter suspended.' : $err = 'Update failed.';
        $stmt->close();
    }
    if ($action === 'reset_pin') {
    $hashed = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    $stmt = $conn->prepare("UPDATE voters SET password = ? WHERE voter_id = ?");
    $stmt->bind_param('si', $hashed, $voter_id);
    $stmt->execute() ? $msg = 'PIN reset to 1234 successfully.' : $err = 'Reset failed.';
    $stmt->close();
    }
    if ($action === 'delete') {
        $stmt = $conn->prepare("UPDATE voters SET deleted_at=NOW() WHERE voter_id=?");
        $stmt->bind_param('i', $voter_id);
        $stmt->execute() ? $msg = 'Voter deleted.' : $err = 'Delete failed.';
        $stmt->close();
    }
}

// Search
$search = sanitize($_GET['search'] ?? '');
$where  = "deleted_at IS NULL";
if ($search) {
    $s      = $conn->real_escape_string($search);
    $where .= " AND (first_name LIKE '%$s%' OR last_name LIKE '%$s%' OR email LIKE '%$s%' OR national_id LIKE '%$s%')";
}

$voters = $conn->query(
    "SELECT voter_id, first_name, last_name, email, phone,
            national_id, gender, status, created_at
     FROM voters WHERE $where ORDER BY created_at DESC"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Voters — E-Voting Admin</title>
</head>
<body>
<?php require_once 'partials/navbar.php'; ?>
<main>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <div class="page-title">Manage Voters</div>
            <div class="page-sub">View, activate, and suspend voter accounts</div>
        </div>
        <span style="font-family:'JetBrains Mono',monospace;font-size:.82rem;color:var(--muted);">
            Total: <?= count($voters) ?> voter(s)
        </span>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <!-- Search -->
    <form method="GET" style="margin-bottom:1.5rem;display:flex;gap:.75rem;">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
               placeholder="Search by name, email, or national ID..."
               style="flex:1;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:.65rem 1rem;color:var(--text);font-family:'Sora',sans-serif;font-size:.875rem;outline:none;">
        <button type="submit" class="btn-primary">Search</button>
        <?php if ($search): ?>
            <a href="voters.php" class="btn-sm btn-info" style="padding:.65rem 1rem;">Clear</a>
        <?php endif; ?>
    </form>

    <div class="card">
        <div class="section-label">Registered Voters</div>
        <?php if (empty($voters)): ?>
            <div class="empty">No voters found.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Contact</th><th>National ID</th><th>Gender</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($voters as $i => $v): ?>
                <tr>
                    <td style="color:var(--muted);font-family:'JetBrains Mono',monospace;"><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($v['first_name'].' '.$v['last_name']) ?></strong></td>
                    <td>
                        <div style="font-size:.82rem;"><?= htmlspecialchars($v['email']) ?></div>
                        <div style="font-size:.78rem;color:var(--muted);"><?= htmlspecialchars($v['phone']) ?></div>
                    </td>
                    <td style="font-family:'JetBrains Mono',monospace;font-size:.78rem;"><?= htmlspecialchars($v['national_id']) ?></td>
                    <td style="text-transform:capitalize;"><?= $v['gender'] ?></td>
                    <td><span class="badge badge-<?= $v['status'] ?>"><?= $v['status'] ?></span></td>
                    <td style="font-size:.78rem;color:var(--muted);font-family:'JetBrains Mono',monospace;"><?= date('d M Y', strtotime($v['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                            <?php if ($v['status'] !== 'active'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="voter_id" value="<?= $v['voter_id'] ?>">
                                    <button type="submit" class="btn-sm btn-success">Activate</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Reset PIN to 1234 for this voter?');" style="display:inline;">
                                <input type="hidden" name="action" value="reset_pin">
                                <input type="hidden" name="voter_id" value="<?= $v['voter_id'] ?>">
                                <button type="submit" class="btn-sm btn-info">🔑 Reset PIN</button>
                            </form>
                            <?php if ($v['status'] !== 'suspended'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="suspend">
                                    <input type="hidden" name="voter_id" value="<?= $v['voter_id'] ?>">
                                    <button type="submit" class="btn-sm btn-warning" onclick="return confirm('Suspend this voter?')">Suspend</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Permanently delete this voter?');" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="voter_id" value="<?= $v['voter_id'] ?>">
                                <button type="submit" class="btn-sm btn-danger">Delete</button>
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

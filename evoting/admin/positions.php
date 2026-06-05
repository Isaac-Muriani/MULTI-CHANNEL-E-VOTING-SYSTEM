<?php
// =============================================================
//  MANAGE POSITIONS
//  evoting/admin/positions.php
// =============================================================
require_once 'auth_guard.php';

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name        = sanitize($_POST['position_name'] ?? '');
        $election_id = (int)$_POST['election_id'];
        $max_winners = (int)($_POST['max_winners'] ?? 1);

        if (empty($name) || $election_id === 0) {
            $err = 'Please fill in all required fields.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO positions (position_name, election_id, max_winners) VALUES (?,?,?)"
            );
            $stmt->bind_param('sii', $name, $election_id, $max_winners);
            $stmt->execute() ? $msg = 'Position added successfully.' : $err = 'Failed to add position.';
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['position_id'];
        $stmt = $conn->prepare("DELETE FROM positions WHERE position_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? $msg = 'Position deleted.' : $err = 'Delete failed.';
        $stmt->close();
    }
}

// Fetch positions with election name
$positions = $conn->query(
    "SELECT p.*, e.election_name FROM positions p
     JOIN elections e ON e.election_id = p.election_id
     WHERE e.deleted_at IS NULL
     ORDER BY e.election_name, p.position_name"
)->fetch_all(MYSQLI_ASSOC);

// Elections for dropdown
$elections = $conn->query(
    "SELECT election_id, election_name FROM elections
     WHERE deleted_at IS NULL ORDER BY election_name"
)->fetch_all(MYSQLI_ASSOC);

$show_form = isset($_GET['action']) && $_GET['action'] === 'create';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Positions — E-Voting Admin</title>
</head>
<body>
<?php require_once 'partials/navbar.php'; ?>
<main>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <div class="page-title">Manage Positions</div>
            <div class="page-sub">Add positions like President, Vice President, Secretary per election</div>
        </div>
        <a href="?action=create" class="btn-primary">+ Add Position</a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <?php if ($show_form): ?>
    <div class="card" style="margin-bottom:2rem;">
        <div class="section-label">Add New Position</div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>Position Name *</label>
                    <input type="text" name="position_name" placeholder="e.g. President" required>
                </div>
                <div class="form-group">
                    <label>Election *</label>
                    <select name="election_id" required>
                        <option value="">Select election</option>
                        <?php foreach ($elections as $e): ?>
                            <option value="<?= $e['election_id'] ?>"><?= htmlspecialchars($e['election_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group" style="max-width:200px;">
                <label>Max Winners (Seats)</label>
                <input type="number" name="max_winners" value="1" min="1" max="100">
            </div>
            <div style="display:flex;gap:.75rem;">
                <button type="submit" class="btn-primary">Add Position</button>
                <a href="positions.php" class="btn-sm btn-info" style="padding:.65rem 1.2rem;">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="section-label">All Positions (<?= count($positions) ?>)</div>
        <?php if (empty($positions)): ?>
            <div class="empty">No positions added yet. Click "Add Position" to get started.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Position</th><th>Election</th><th>Max Winners</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($positions as $i => $p): ?>
                <tr>
                    <td style="color:var(--muted);font-family:'JetBrains Mono',monospace;"><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($p['position_name']) ?></strong></td>
                    <td><?= htmlspecialchars($p['election_name']) ?></td>
                    <td style="font-family:'JetBrains Mono',monospace;"><?= $p['max_winners'] ?> seat(s)</td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Delete this position?');" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="position_id" value="<?= $p['position_id'] ?>">
                            <button type="submit" class="btn-sm btn-danger">Delete</button>
                        </form>
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

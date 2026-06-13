<?php
// =============================================================
//  MANAGE POSITIONS (Running Mate Version)
//  evoting/admin/positions.php
// =============================================================
require_once 'auth_guard.php';

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name              = sanitize($_POST['position_name']      ?? '');
        $election_id       = (int)$_POST['election_id'];
        $max_winners       = (int)($_POST['max_winners']           ?? 1);
        $running_mate      = (int)($_POST['running_mate_enabled']  ?? 0);

        if (empty($name) || $election_id === 0) {
            $err = 'Please fill in all required fields.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO positions (position_name, election_id, max_winners, running_mate_enabled)
                 VALUES (?,?,?,?)"
            );
            $stmt->bind_param('siii', $name, $election_id, $max_winners, $running_mate);
            $stmt->execute() ? $msg = 'Position added successfully.' : $err = 'Failed to add position.';
            $stmt->close();
        }
    }

    if ($action === 'toggle_runningmate') {
        $id  = (int)$_POST['position_id'];
        $val = (int)$_POST['value'];
        $stmt = $conn->prepare("UPDATE positions SET running_mate_enabled=? WHERE position_id=?");
        $stmt->bind_param('ii', $val, $id);
        $stmt->execute() ? $msg = 'Running mate setting updated.' : $err = 'Update failed.';
        $stmt->close();
    }

    if ($action === 'delete') {
        $id   = (int)$_POST['position_id'];
        $stmt = $conn->prepare("DELETE FROM positions WHERE position_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? $msg = 'Position deleted.' : $err = 'Delete failed.';
        $stmt->close();
    }
}

$positions = $conn->query(
    "SELECT p.*, e.election_name FROM positions p
     JOIN elections e ON e.election_id = p.election_id
     WHERE e.deleted_at IS NULL
     ORDER BY e.election_name, p.position_name"
)->fetch_all(MYSQLI_ASSOC);

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
            <div class="page-sub">Add positions and configure running mate system</div>
        </div>
        <a href="?action=create" class="btn-primary">+ Add Position</a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <?php if ($show_form): ?>
    <div class="card" style="margin-bottom:2rem;">
        <div class="section-label">Add New Position</div>
        <form method="POST">
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
            <div class="form-row">
                <div class="form-group">
                    <label>Max Winners (Seats)</label>
                    <input type="number" name="max_winners" value="1" min="1" max="100">
                </div>
                <div class="form-group">
                    <label>Running Mate System</label>
                    <select name="running_mate_enabled">
                        <option value="0">❌ Disabled (Normal voting)</option>
                        <option value="1">✅ Enabled (Ticket/Running mate voting)</option>
                    </select>
                </div>
            </div>
            <!-- Info box -->
            <div style="background:rgba(0,212,255,0.06);border:1px solid rgba(0,212,255,0.2);border-radius:8px;padding:.875rem 1rem;margin-bottom:1.25rem;font-size:.82rem;color:var(--muted);">
                💡 <strong style="color:var(--accent);">Running Mate System</strong> — Enable this for positions like President where a candidate runs together with a Vice Presidential partner as a ticket. Voters will vote for the entire ticket (President + VP) at once.
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
                <tr>
                    <th>#</th>
                    <th>Position</th>
                    <th>Election</th>
                    <th>Max Winners</th>
                    <th>Running Mate</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($positions as $i => $p): ?>
                <tr>
                    <td style="color:var(--muted);font-family:'JetBrains Mono',monospace;"><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($p['position_name']) ?></strong></td>
                    <td><?= htmlspecialchars($p['election_name']) ?></td>
                    <td style="font-family:'JetBrains Mono',monospace;"><?= $p['max_winners'] ?> seat(s)</td>
                    <td>
                        <!-- Toggle running mate on/off -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_runningmate">
                            <input type="hidden" name="position_id" value="<?= $p['position_id'] ?>">
                            <input type="hidden" name="value" value="<?= $p['running_mate_enabled'] ? 0 : 1 ?>">
                            <button type="submit"
                                style="background:<?= $p['running_mate_enabled'] ? 'rgba(52,211,153,0.15)' : 'rgba(100,116,139,0.15)' ?>;
                                       border:1px solid <?= $p['running_mate_enabled'] ? 'rgba(52,211,153,0.3)' : 'rgba(100,116,139,0.3)' ?>;
                                       color:<?= $p['running_mate_enabled'] ? 'var(--success)' : 'var(--muted)' ?>;
                                       padding:.25rem .75rem;border-radius:20px;font-size:.72rem;font-weight:700;
                                       cursor:pointer;font-family:\'JetBrains Mono\',monospace;text-transform:uppercase;letter-spacing:.06em;">
                                <?= $p['running_mate_enabled'] ? '✅ Enabled' : '❌ Disabled' ?>
                            </button>
                        </form>
                    </td>
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

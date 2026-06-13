<?php
// =============================================================
//  MANAGE ELECTIONS
//  evoting/admin/elections.php
// =============================================================
require_once 'auth_guard.php';

$msg = ''; $err = '';

// ---- Handle actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREATE
    if ($action === 'create') {
        $name  = sanitize($_POST['election_name'] ?? '');
        $desc  = sanitize($_POST['description']   ?? '');
        $start = sanitize($_POST['start_date']     ?? '');
        $end   = sanitize($_POST['end_date']       ?? '');

        if (empty($name) || empty($start) || empty($end)) {
            $err = 'Please fill in all required fields.';
        } elseif ($end <= $start) {
            $err = 'End date must be after start date.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO elections (election_name,description,start_date,end_date,status)
                 VALUES (?,?,?,?,'upcoming')"
            );
            $stmt->bind_param('ssss', $name, $desc, $start, $end);
            $stmt->execute() ? $msg = 'Election created successfully.' : $err = 'Failed to create election.';
            $stmt->close();
        }
    }

    // UPDATE STATUS
    if ($action === 'status') {
        $id     = (int)$_POST['election_id'];
        $status = sanitize($_POST['status']);
        $allowed = ['upcoming','active','completed','cancelled'];
        if (in_array($status, $allowed)) {
            $stmt = $conn->prepare("UPDATE elections SET status=? WHERE election_id=?");
            $stmt->bind_param('si', $status, $id);
            $stmt->execute() ? $msg = 'Election status updated.' : $err = 'Update failed.';
            $stmt->close();
        }
    }

    // DELETE (soft)
    if ($action === 'delete') {
        $id = (int)$_POST['election_id'];
        $stmt = $conn->prepare("UPDATE elections SET deleted_at=NOW() WHERE election_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? $msg = 'Election deleted.' : $err = 'Delete failed.';
        $stmt->close();
    }
}

// Fetch elections
$elections = $conn->query(
    "SELECT * FROM elections WHERE deleted_at IS NULL ORDER BY created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$show_form = isset($_GET['action']) && $_GET['action'] === 'create';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Elections — E-Voting Admin</title>
</head>
<body>
<?php require_once 'partials/navbar.php'; ?>
<main>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <div class="page-title">Manage Elections</div>
            <div class="page-sub">Create and manage elections</div>
        </div>
        <a href="?action=create" class="btn-primary">+ New Election</a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <!-- Create Form -->
    <?php if ($show_form): ?>
    <div class="card" style="margin-bottom:2rem;">
        <div class="section-label">Create New Election</div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Election Name *</label>
                <input type="text" name="election_name" placeholder="e.g. Student Union Elections 2025" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Brief description of this election..."></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date & Time *</label>
                    <input type="datetime-local" name="start_date" required>
                </div>
                <div class="form-group">
                    <label>End Date & Time *</label>
                    <input type="datetime-local" name="end_date" required>
                </div>
            </div>
            <div style="display:flex;gap:.75rem;">
                <button type="submit" class="btn-primary">Create Election</button>
                <a href="elections.php" class="btn-sm btn-info" style="padding:.65rem 1.2rem;">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Elections Table -->
    <div class="card">
        <div class="section-label">All Elections (<?= count($elections) ?>)</div>
        <?php if (empty($elections)): ?>
            <div class="empty">🗳️ No elections created yet. Click "New Election" to get started.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Election Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($elections as $i => $e): ?>
                <tr>
                    <td style="color:var(--muted);font-family:'JetBrains Mono',monospace;"><?= $i+1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($e['election_name']) ?></strong>
                        <?php if ($e['description']): ?>
                            <div style="font-size:.78rem;color:var(--muted);margin-top:.2rem;"><?= htmlspecialchars(substr($e['description'],0,60)) ?>...</div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.82rem;font-family:'JetBrains Mono',monospace;"><?= date('d M Y H:i', strtotime($e['start_date'])) ?></td>
                    <td style="font-size:.82rem;font-family:'JetBrains Mono',monospace;"><?= date('d M Y H:i', strtotime($e['end_date'])) ?></td>
                    <td><span class="badge badge-<?= $e['status'] ?>"><?= $e['status'] ?></span></td>
                    <td>
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                            <!-- Change Status -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="election_id" value="<?= $e['election_id'] ?>">
                                <select name="status" onchange="this.form.submit()"
                                    style="background:var(--bg);border:1px solid var(--border);color:var(--text);padding:.3rem .6rem;border-radius:6px;font-size:.78rem;cursor:pointer;">
                                    <option value="upcoming"  <?= $e['status']==='upcoming'  ?'selected':'' ?>>Upcoming</option>
                                    <option value="active"    <?= $e['status']==='active'    ?'selected':'' ?>>Active</option>
                                    <option value="completed" <?= $e['status']==='completed' ?'selected':'' ?>>Completed</option>
                                    <option value="cancelled" <?= $e['status']==='cancelled' ?'selected':'' ?>>Cancelled</option>
                                </select>
                            </form>
                            <!-- Delete -->
                            <form method="POST" onsubmit="return confirm('Delete this election?');" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="election_id" value="<?= $e['election_id'] ?>">
                                <a href="export_results.php?election_id=<?= $e['election_id'] ?>" class="btn-sm btn-info" target="_blank">📊 Export</a>
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

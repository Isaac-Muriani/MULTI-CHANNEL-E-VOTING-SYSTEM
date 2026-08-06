<?php
// =============================================================
//  MANAGE ADMINS — SUPERADMIN ONLY
//  evoting/admin/manage_admins.php
// =============================================================
require_once 'auth_guard.php';
requireSuperAdmin();

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREATE NEW ADMIN
    if ($action === 'create') {
        $full_name = sanitize($_POST['full_name'] ?? '');
        $email     = sanitize($_POST['email']     ?? '');
        $password  = $_POST['password']           ?? '';
        $role      = sanitize($_POST['role']      ?? 'admin');

        if (empty($full_name) || empty($email) || empty($password)) {
            $err = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $err = 'Password must be at least 6 characters.';
        } elseif (!in_array($role, ['superadmin','admin','observer'])) {
            $err = 'Invalid role selected.';
        } else {
            $hashed = hashPassword($password);
            $stmt = $conn->prepare(
                "INSERT INTO admins (full_name, email, password, role) VALUES (?,?,?,?)"
            );
            $stmt->bind_param('ssss', $full_name, $email, $hashed, $role);
            try {
                $stmt->execute();
                $msg = 'Admin account created successfully.';
            } catch (mysqli_sql_exception $e) {
                $err = ($e->getCode() == 1062) ? 'An admin with this email already exists.' : 'Failed to create admin.';
            }
            $stmt->close();
        }
    }

    // UPDATE ROLE
    if ($action === 'update_role') {
        $id   = (int)$_POST['admin_id'];
        $role = sanitize($_POST['role']);

        if ($id === (int)$admin_id) {
            $err = 'You cannot change your own role.';
        } elseif (!in_array($role, ['superadmin','admin','observer'])) {
            $err = 'Invalid role.';
        } else {
            $stmt = $conn->prepare("UPDATE admins SET role=? WHERE admin_id=?");
            $stmt->bind_param('si', $role, $id);
            $stmt->execute() ? $msg = 'Admin role updated successfully.' : $err = 'Update failed.';
            $stmt->close();
        }
    }

    // DELETE ADMIN
    if ($action === 'delete') {
        $id = (int)$_POST['admin_id'];
        if ($id === (int)$admin_id) {
            $err = 'You cannot delete your own account.';
        } else {
            $stmt = $conn->prepare("DELETE FROM admins WHERE admin_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $msg = 'Admin account deleted.' : $err = 'Delete failed.';
            $stmt->close();
        }
    }

    // RESET PASSWORD
    if ($action === 'reset_password') {
        $id = (int)$_POST['admin_id'];
        $hashed = hashPassword('Admin@123');
        $stmt = $conn->prepare("UPDATE admins SET password=? WHERE admin_id=?");
        $stmt->bind_param('si', $hashed, $id);
        $stmt->execute() ? $msg = 'Password reset to: Admin@123' : $err = 'Reset failed.';
        $stmt->close();
    }
}

$admins = $conn->query(
    "SELECT admin_id, full_name, email, role, created_at FROM admins ORDER BY created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$show_form = isset($_GET['action']) && $_GET['action'] === 'create';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins — E-Voting Admin</title>
</head>
<body>
<?php require_once 'partials/navbar.php'; ?>
<main>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
        <div>
            <div class="page-title">Manage Admins</div>
            <div class="page-sub">Create and manage admin accounts with role-based access</div>
        </div>
        <a href="?action=create" class="btn-primary">+ New Admin</a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= $err ?></div><?php endif; ?>

    <!-- Role explainer -->
    <div class="card" style="margin-bottom:1.5rem;background:rgba(0,212,255,0.05);border-color:rgba(0,212,255,0.2);">
        <div class="section-label">Role Permissions</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;font-size:.82rem;">
            <div>
                <strong style="color:var(--accent);">🔑 Superadmin</strong>
                <div style="color:var(--muted);margin-top:.3rem;">Full access — manage elections, positions, candidates, voters, and other admins.</div>
            </div>
            <div>
                <strong style="color:#34d399;">⚙️ Admin</strong>
                <div style="color:var(--muted);margin-top:.3rem;">Approve candidates, manage voters, view & export results. Cannot create/delete elections or manage admins.</div>
            </div>
            <div>
                <strong style="color:#94a3b8;">👁️ Observer</strong>
                <div style="color:var(--muted);margin-top:.3rem;">Read-only access — can view results and stats only.</div>
            </div>
        </div>
    </div>

    <?php if ($show_form): ?>
    <div class="card" style="margin-bottom:2rem;">
        <div class="section-label">Create New Admin</div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" placeholder="e.g. John Doe" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" placeholder="admin@evoting.com" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" placeholder="Min. 6 characters" required>
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" required>
                        <option value="admin">⚙️ Admin (Election Admin)</option>
                        <option value="observer">👁️ Observer (Read-only)</option>
                        <option value="superadmin">🔑 Superadmin (Full access)</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:.75rem;">
                <button type="submit" class="btn-primary">Create Admin</button>
                <a href="manage_admins.php" class="btn-sm btn-info" style="padding:.65rem 1.2rem;">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="section-label">All Admins (<?= count($admins) ?>)</div>
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $i => $a): ?>
                <tr>
                    <td style="color:var(--muted);font-family:'JetBrains Mono',monospace;"><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($a['full_name']) ?></strong>
                        <?php if ($a['admin_id'] == $admin_id): ?>
                            <span style="font-size:.7rem;color:var(--accent);">(You)</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars($a['email']) ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="update_role">
                            <input type="hidden" name="admin_id" value="<?= $a['admin_id'] ?>">
                            <select name="role" onchange="this.form.submit()"
                                <?= $a['admin_id'] == $admin_id ? 'disabled' : '' ?>
                                style="background:var(--bg);border:1px solid var(--border);color:var(--text);padding:.3rem .6rem;border-radius:6px;font-size:.78rem;cursor:pointer;">
                                <option value="superadmin" <?= $a['role']==='superadmin'?'selected':'' ?>>🔑 Superadmin</option>
                                <option value="admin"      <?= $a['role']==='admin'     ?'selected':'' ?>>⚙️ Admin</option>
                                <option value="observer"   <?= $a['role']==='observer'  ?'selected':'' ?>>👁️ Observer</option>
                            </select>
                        </form>
                    </td>
                    <td style="font-size:.78rem;color:var(--muted);font-family:'JetBrains Mono',monospace;"><?= date('d M Y', strtotime($a['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                            <?php if ($a['admin_id'] != $admin_id): ?>
                                <form method="POST" onsubmit="return confirm('Reset password to Admin@123?');" style="display:inline;">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="admin_id" value="<?= $a['admin_id'] ?>">
                                    <button type="submit" class="btn-sm btn-info">🔑 Reset Password</button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Delete this admin account?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="admin_id" value="<?= $a['admin_id'] ?>">
                                    <button type="submit" class="btn-sm btn-danger">Delete</button>
                                </form>
                            <?php else: ?>
                                <span style="font-size:.78rem;color:var(--muted);">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>

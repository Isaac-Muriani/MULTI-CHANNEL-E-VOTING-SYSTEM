<?php
// =============================================================
//  ADMIN AUTH GUARD
//  evoting/admin/auth_guard.php
//  Include this at the top of every admin page
// =============================================================
require_once '../includes/config.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];
$admin_id   = $_SESSION['admin_id'];

/**
 * Check if current admin is a superadmin
 */
function isSuperAdmin() {
    return ($_SESSION['admin_role'] ?? '') === 'superadmin';
}

/**
 * Require superadmin role — redirects with error if not superadmin
 * Call this at the top of pages only superadmin should access
 */
function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        $_SESSION['access_denied'] = 'This action requires Super Admin privileges.';
        redirect('dashboard.php');
    }
}
?>

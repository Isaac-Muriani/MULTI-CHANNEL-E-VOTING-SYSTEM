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
?>

<?php
// =============================================================
//  VOTER LOGOUT
//  evoting/voter/logout.php
// =============================================================
require_once '../includes/config.php';

session_destroy();
redirect('login.php');
?>

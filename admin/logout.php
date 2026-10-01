<?php
/**
 * Sweet Choice - Admin Logout
 */

require_once __DIR__ . '/../includes/auth.php';

logoutUser();
setFlash('info', 'Logged out of Admin Portal.');
header("Location: login.php");
exit;

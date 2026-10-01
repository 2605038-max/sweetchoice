<?php
/**
 * Sweet Choice - Customer Logout
 */

require_once __DIR__ . '/../includes/auth.php';

logoutUser();
setFlash('info', 'You have been logged out.');
header("Location: index.php");
exit;

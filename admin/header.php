<?php
/**
 * Sweet Choice - Admin Header & Navigation
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';

$pdo = getDB();
requireAdmin('login.php');

$adminUser = getCurrentUser($pdo);
$adminScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($adminTitle) ? e($adminTitle) . ' | Sweet Choice Staff' : 'Sweet Choice Staff Portal'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-nav {
            background: #2D2224;
            color: #FAF4F4;
            padding: 0 20px;
        }
        .admin-nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
            max-width: 1300px;
            margin: 0 auto;
        }
        .admin-menu-links {
            display: flex;
            list-style: none;
            gap: 18px;
            align-items: center;
        }
        .admin-menu-links a {
            color: #DABFBE;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 8px 10px;
            border-radius: 6px;
        }
        .admin-menu-links a:hover,
        .admin-menu-links a.active {
            color: #FFFFFF;
            background: rgba(231, 182, 188, 0.2);
        }
        .admin-content {
            max-width: 1300px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .admin-card {
            background: #FFFFFF;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .admin-table th {
            background: #FAF7F6;
            padding: 12px 14px;
            text-align: left;
            font-size: 0.85rem;
            color: var(--color-secondary-text);
            border-bottom: 1.5px solid var(--color-border);
        }
        .admin-table td {
            padding: 14px;
            border-bottom: 1px solid var(--color-border-light);
            font-size: 0.9rem;
            vertical-align: middle;
        }
        .admin-table tr:hover td {
            background: #FFFDFD;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #FFFFFF;
            border-radius: var(--radius-md);
            border: 1px solid var(--color-border);
            padding: 22px;
            box-shadow: var(--shadow-sm);
        }
        .stat-val {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-deep-plum);
            margin: 6px 0;
        }
        .stat-label {
            font-size: 0.82rem;
            color: var(--color-secondary-text);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .admin-menu-links { flex-wrap: wrap; }
        }
    </style>
</head>
<body style="background:#FBF8F6;">

<header class="admin-nav">
    <div class="admin-nav-inner">
        <div style="display:flex; align-items:center; gap:16px;">
            <a href="index.php" style="color:#FFF; font-weight:700; font-size:1.2rem; display:flex; align-items:center; gap:8px;">
                <span>🍰</span> Sweet Choice Staff
            </a>
            <span style="background:rgba(231,182,188,0.25); color:#E7B6BC; font-size:0.75rem; padding:2px 8px; border-radius:4px; font-weight:600;">ADMIN</span>
        </div>

        <ul class="admin-menu-links">
            <li><a href="index.php" class="<?= $adminScript === 'index.php' ? 'active' : ''; ?>">Dashboard</a></li>
            <li><a href="products.php" class="<?= $adminScript === 'products.php' ? 'active' : ''; ?>">Products</a></li>
            <li><a href="categories.php" class="<?= $adminScript === 'categories.php' ? 'active' : ''; ?>">Categories</a></li>
            <li><a href="banners.php" class="<?= $adminScript === 'banners.php' ? 'active' : ''; ?>">Banners</a></li>
            <li><a href="orders.php" class="<?= $adminScript === 'orders.php' ? 'active' : ''; ?>">Orders</a></li>
            <li><a href="users.php" class="<?= $adminScript === 'users.php' ? 'active' : ''; ?>">Users</a></li>
            <li><a href="reviews.php" class="<?= $adminScript === 'reviews.php' ? 'active' : ''; ?>">Reviews</a></li>
            <li><a href="settings.php" class="<?= $adminScript === 'settings.php' ? 'active' : ''; ?>">Settings</a></li>
        </ul>

        <div style="display:flex; align-items:center; gap:14px; font-size:0.85rem;">
            <a href="../public/index.php" target="_blank" style="color:#E7B6BC;">🌐 View Storefront &rarr;</a>
            <span style="color:#776063;">|</span>
            <a href="logout.php" style="color:#FFF; font-weight:600;">Log Out</a>
        </div>
    </div>
</header>

<div class="container flash-container" style="max-width:1300px; padding:20px 20px 0 20px;">
    <?php foreach (getFlashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']); ?>">
            <span><?= e($flash['message']); ?></span>
            <button type="button" style="background:none;border:none;cursor:pointer;font-weight:bold;" onclick="this.parentElement.remove();">&times;</button>
        </div>
    <?php endforeach; ?>
</div>

<main class="admin-content">

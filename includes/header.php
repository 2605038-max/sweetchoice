<?php
/**
 * Sweet Choice - Common Page Header
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/language.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$pdo = getDB();
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . __('brand_name', 'Sweet Choice') : __('brand_name', 'Sweet Choice') . ' - Handcrafted Japanese Desserts';
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>
    <meta name="description" content="Sweet Choice - Artisanal Japanese Desserts, handcrafted shortcakes, purin puddings, and matcha confections in Tokyo.">
    
    <!-- Google Fonts & Stylesheet -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,400&family=Noto+Serif+JP:wght@400;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
    <link rel="icon" type="image/png" href="../assets/images/logo_banner.jpg">
</head>
<?php
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$isHomePage = ($currentScript === 'index.php' && strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') === false);
$bodyClass = $isHomePage ? 'page-home' : 'page-inner';
?>
<body class="<?= $bodyClass; ?>">

<?php require_once __DIR__ . '/navbar.php'; ?>

<!-- Flash Messages Container -->
<?php $flashes = getFlashes(); if (!empty($flashes)): ?>
<div class="container flash-container">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']); ?>">
            <span><?= e($flash['message']); ?></span>
            <button type="button" style="background:none;border:none;cursor:pointer;font-weight:bold;" onclick="this.parentElement.remove();">&times;</button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<main>

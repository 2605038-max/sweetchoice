<?php
/**
 * Sweet Choice - Asset Generator
 * Generates beautiful, styled confection graphics for banners, categories, and products.
 */

$productDir = '/workspaces/sweetchoice/uploads/products/';
$bannerDir = '/workspaces/sweetchoice/uploads/banners/';
$assetsImageDir = '/workspaces/sweetchoice/assets/images/';

@mkdir($productDir, 0777, true);
@mkdir($bannerDir, 0777, true);
@mkdir($assetsImageDir, 0777, true);

function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    ];
}

function createStyledGraphic($filename, $width, $height, $title, $sub, $badge, $bgColor1, $bgColor2, $accentColor, $iconType) {
    $im = imagecreatetruecolor($width, $height);
    imageantialias($im, true);

    $rgb1 = hexToRgb($bgColor1);
    $rgb2 = hexToRgb($bgColor2);
    $accRgb = hexToRgb($accentColor);

    // Vertical gradient
    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = (int)($rgb1[0] * (1 - $ratio) + $rgb2[0] * $ratio);
        $g = (int)($rgb1[1] * (1 - $ratio) + $rgb2[1] * $ratio);
        $b = (int)($rgb1[2] * (1 - $ratio) + $rgb2[2] * $ratio);
        $lineColor = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $width, $y, $lineColor);
    }

    // Soft decorative circular glows
    $glowCol = imagecolorallocatealpha($im, $accRgb[0], $accRgb[1], $accRgb[2], 105);
    imagefilledellipse($im, (int)($width * 0.75), (int)($height * 0.35), (int)($width * 0.6), (int)($width * 0.6), $glowCol);

    $glowCol2 = imagecolorallocatealpha($im, 255, 255, 255, 115);
    imagefilledellipse($im, (int)($width * 0.25), (int)($height * 0.65), (int)($width * 0.5), (int)($width * 0.5), $glowCol2);

    // Confectionery plate or dessert silhouette
    $plateCol = imagecolorallocatealpha($im, 255, 255, 255, 60);
    $plateShadow = imagecolorallocatealpha($im, 150, 130, 140, 110);
    $cx = (int)($width / 2);
    $cy = (int)($height * 0.42);

    // Plate shadow & rim
    imagefilledellipse($im, $cx, $cy + 40, (int)($width * 0.65), (int)($height * 0.25), $plateShadow);
    imagefilledellipse($im, $cx, $cy + 30, (int)($width * 0.62), (int)($height * 0.24), $plateCol);

    // Draw stylized dessert representation
    $dessertColor = imagecolorallocate($im, $accRgb[0], $accRgb[1], $accRgb[2]);
    $creamWhite = imagecolorallocate($im, 253, 253, 253);
    $strawberryRed = imagecolorallocate($im, 220, 70, 85);
    $chocolateBrown = imagecolorallocate($im, 90, 50, 40);
    $matchaGreen = imagecolorallocate($im, 95, 140, 75);
    $caramelAmber = imagecolorallocate($im, 225, 150, 50);

    if (strpos($iconType, 'cake') !== false || strpos($iconType, 'strawberry') !== false) {
        // Multi-layered shortcake / cake wedge
        imagefilledarc($im, $cx, $cy + 15, 180, 110, 0, 360, $creamWhite, IMG_ARC_PIE);
        imagefilledarc($im, $cx, $cy, 170, 95, 0, 360, $dessertColor, IMG_ARC_PIE);
        imagefilledarc($im, $cx, $cy - 10, 160, 85, 0, 360, $creamWhite, IMG_ARC_PIE);
        // Strawberry topper
        imagefilledellipse($im, $cx, $cy - 25, 34, 42, $strawberryRed);
        imagefilledellipse($im, $cx - 8, $cy - 38, 14, 10, $matchaGreen);
        imagefilledellipse($im, $cx + 8, $cy - 38, 14, 10, $matchaGreen);
    } elseif (strpos($iconType, 'chocolate') !== false) {
        // Rich chocolate layered wedge
        imagefilledarc($im, $cx, $cy + 15, 180, 100, 0, 360, $chocolateBrown, IMG_ARC_PIE);
        imagefilledarc($im, $cx, $cy, 170, 90, 0, 360, $dessertColor, IMG_ARC_PIE);
        imagefilledellipse($im, $cx - 20, $cy - 15, 24, 24, $strawberryRed);
        imagefilledellipse($im, $cx + 20, $cy - 10, 18, 18, $caramelAmber);
    } elseif (strpos($iconType, 'matcha') !== false) {
        // Matcha crepe / latte
        imagefilledarc($im, $cx, $cy + 15, 180, 100, 0, 360, $matchaGreen, IMG_ARC_PIE);
        imagefilledarc($im, $cx, $cy, 160, 85, 0, 360, $creamWhite, IMG_ARC_PIE);
        imagefilledarc($im, $cx, $cy - 10, 150, 75, 0, 360, $matchaGreen, IMG_ARC_PIE);
    } elseif (strpos($iconType, 'pudding') !== false) {
        // Purin flan shape
        imagefilledpolygon($im, [
            $cx - 65, $cy + 25,
            $cx + 65, $cy + 25,
            $cx + 45, $cy - 30,
            $cx - 45, $cy - 30
        ], $caramelAmber);
        imagefilledellipse($im, $cx, $cy - 30, 90, 35, $chocolateBrown);
        // Whipped cream on top
        imagefilledellipse($im, $cx, $cy - 45, 35, 24, $creamWhite);
        imagefilledellipse($im, $cx, $cy - 55, 16, 16, $strawberryRed);
    } elseif (strpos($iconType, 'cookie') !== false) {
        // Butter cookies
        imagefilledellipse($im, $cx - 35, $cy, 70, 60, $caramelAmber);
        imagefilledellipse($im, $cx + 35, $cy - 10, 70, 60, $chocolateBrown);
        imagefilledellipse($im, $cx, $cy + 15, 60, 50, $matchaGreen);
    } elseif (strpos($iconType, 'drink') !== false) {
        // Tall glass / latte
        imagefilledpolygon($im, [
            $cx - 35, $cy + 40,
            $cx + 35, $cy + 40,
            $cx + 45, $cy - 45,
            $cx - 45, $cy - 45
        ], $dessertColor);
        imagefilledellipse($im, $cx, $cy - 45, 90, 30, $creamWhite);
        // Straw
        imageline($im, $cx + 10, $cy - 75, $cx + 30, $cy + 25, $strawberryRed);
        imageline($im, $cx + 11, $cy - 75, $cx + 31, $cy + 25, $strawberryRed);
    } else {
        // General pastry / tart
        imagefilledellipse($im, $cx, $cy + 10, 170, 90, $caramelAmber);
        imagefilledellipse($im, $cx, $cy - 5, 150, 70, $creamWhite);
        imagefilledellipse($im, $cx - 30, $cy - 10, 25, 25, $strawberryRed);
        imagefilledellipse($im, $cx, $cy - 15, 22, 22, $matchaGreen);
        imagefilledellipse($im, $cx + 30, $cy - 10, 24, 24, $chocolateBrown);
    }

    // Border line inside
    $innerBorder = imagecolorallocatealpha($im, 255, 255, 255, 80);
    imagesetthickness($im, 2);
    imagerectangle($im, 10, 10, $width - 11, $height - 11, $innerBorder);

    // Badge Pill
    if ($badge) {
        $badgeBg = imagecolorallocate($im, $accRgb[0], $accRgb[1], $accRgb[2]);
        $badgeTxt = imagecolorallocate($im, 255, 255, 255);
        $bw = strlen($badge) * 8 + 20;
        $bx = 20;
        $by = 22;
        imagefilledrectangle($im, $bx, $by, $bx + $bw, $by + 22, $badgeBg);
        imagestring($im, 3, $bx + 10, $by + 4, $badge, $badgeTxt);
    }

    // Bottom dark translucent banner for crisp readable text
    $textBg = imagecolorallocatealpha($im, 40, 25, 30, 45);
    imagefilledrectangle($im, 0, (int)($height * 0.72), $width, $height, $textBg);

    // Title & Subtitle text
    $white = imagecolorallocate($im, 255, 255, 255);
    $cream = imagecolorallocate($im, 248, 238, 230);

    // Centered or left-padded text
    $fontTitle = 5;
    $titleX = 24;
    $titleY = (int)($height * 0.76);
    imagestring($im, $fontTitle, $titleX, $titleY, $title, $white);

    $fontSub = 3;
    $subY = (int)($height * 0.86);
    imagestring($im, $fontSub, $titleX, $subY, $sub, $cream);

    imagejpeg($im, $filename, 92);
    imagedestroy($im);
}

// 1. Generate Products (600x480)
$products = [
    'prod_strawberry_shortcake.jpg' => ['Strawberry Shortcake', 'Hokkaido Cream & Strawberries', 'SWEET CHOICE', '#E7B6BC', '#FBF2F4', '#C99FA1', 'strawberry'],
    'prod_chocolate_cake.jpg' => ['Belgian Chocolate Cake', '70% Dark Cocoa Ganache', 'BESTSELLER', '#DABFBE', '#E8DED4', '#5A3228', 'chocolate'],
    'prod_matcha_mille_crepe.jpg' => ['Kyoto Matcha Crepe', '20 Delicate Uji Matcha Layers', 'ARTISAN', '#E8DED4', '#F4F7F2', '#5F8C4B', 'matcha'],
    'prod_souffle_cheesecake.jpg' => ['Souffle Cheesecake', 'Melt-in-Mouth Yuzu Zest', 'LIMITED', '#E7B6BC', '#FFF8F0', '#D69E2E', 'cake'],
    'prod_custard_purin.jpg' => ['Artisan Custard Purin', 'Madagascar Vanilla & Caramel', 'CLASSIC', '#E8DED4', '#FFFDF9', '#C99FA1', 'pudding'],
    'prod_strawberry_pudding.jpg' => ['Strawberry Parfait Purin', 'Milk Pudding & Berry Compote', 'POPULAR', '#E7B6BC', '#FAF0F2', '#C99FA1', 'strawberry'],
    'prod_matcha_cookies.jpg' => ['Matcha Butter Cookies', 'Kyoto Ceremonial Tea Sable', 'HANDMADE', '#E8DED4', '#F3F7EE', '#5F8C4B', 'cookie'],
    'prod_chocolate_cookies.jpg' => ['Triple Chocolate Fudge', 'Sea Salt & Belgian Chunks', 'POPULAR', '#DABFBE', '#ECE5DF', '#5A3228', 'chocolate'],
    'prod_fruit_tart.jpg' => ['Orchard Fruit Tart', 'Seasonal Berries & Pistachio', 'LIMITED', '#E7B6BC', '#FDF5F2', '#C99FA1', 'tart'],
    'prod_cream_puff.jpg' => ['Vanilla Cream Puff', 'Crispy Choux & Custard', 'SOLD OUT', '#E8DED4', '#FDF8F0', '#C99FA1', 'pudding'],
    'prod_matcha_latte.jpg' => ['Kyoto Iced Matcha Latte', 'Ceremonial Grade & Whole Milk', 'FRESH', '#E8DED4', '#EDF5EB', '#5F8C4B', 'drink'],
    'prod_cold_brew.jpg' => ['Cold Brew Coffee', '16-Hour Slow Drip Specialty', 'ICED', '#DABFBE', '#F0EBE5', '#5A3228', 'drink']
];

foreach ($products as $file => $cfg) {
    createStyledGraphic($productDir . $file, 600, 480, $cfg[0], $cfg[1], $cfg[2], $cfg[3], $cfg[4], $cfg[5], $cfg[6]);
}

// 2. Generate Categories (500x380)
$categories = [
    'cat_cakes.jpg' => ['Artisanal Cakes', 'Shortcakes, Mousses & Crepes', 'COLLECTION 01', '#E7B6BC', '#FBF2F4', '#C99FA1', 'cake'],
    'cat_puddings.jpg' => ['Japanese Purin', 'Silky Custard & Fruit Parfaits', 'COLLECTION 02', '#E8DED4', '#FFFDF9', '#C99FA1', 'pudding'],
    'cat_cookies.jpg' => ['Handmade Cookies', 'Buttery Sables & Tea Biscuits', 'COLLECTION 03', '#DABFBE', '#F5EEEA', '#5A3228', 'cookie'],
    'cat_pastries.jpg' => ['Fresh Pastries', 'Tarts, Cream Puffs & Choux', 'COLLECTION 04', '#E7B6BC', '#FDF5F2', '#C99FA1', 'tart'],
    'cat_drinks.jpg' => ['Beverages & Tea', 'Uji Matcha & Artisan Cold Brew', 'COLLECTION 05', '#E8DED4', '#EDF5EB', '#5F8C4B', 'drink']
];

foreach ($categories as $file => $cfg) {
    createStyledGraphic($productDir . $file, 500, 380, $cfg[0], $cfg[1], $cfg[2], $cfg[3], $cfg[4], $cfg[5], $cfg[6]);
}

// 3. Generate Hero Banners (1400x560)
$banners = [
    'banner1.jpg' => ['Exquisite Japanese Confections', 'Crafted fresh daily with pure Hokkaido dairy & Kyoto Uji matcha', 'GINZA TOKYO', '#E7B6BC', '#E8DED4', '#C99FA1', 'cake'],
    'banner2.jpg' => ['Find Your Craving with AI', 'Tell us your mood and taste preference for your perfect dessert', 'AI SOMMELIER', '#DABFBE', '#FDFDFD', '#C99FA1', 'pudding'],
    'banner3.jpg' => ['Spring Strawberry Festival', 'Sweet Tochigi berries featured in limited edition tarts and parfaits', 'SEASONAL SPECIAL', '#E7B6BC', '#FAF0F2', '#C99FA1', 'strawberry']
];

foreach ($banners as $file => $cfg) {
    createStyledGraphic($bannerDir . $file, 1400, 560, $cfg[0], $cfg[1], $cfg[2], $cfg[3], $cfg[4], $cfg[5], $cfg[6]);
}

// 4. Logo / Favicon
createStyledGraphic($assetsImageDir . 'logo_banner.jpg', 600, 200, 'Sweet Choice', 'Artisanal Japanese Confectionery', 'EST. GINZA', '#E7B6BC', '#E8DED4', '#C99FA1', 'cake');

echo "All assets generated successfully!\n";

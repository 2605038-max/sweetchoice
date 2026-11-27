<?php
/**
 * Sweet Choice - Current Website Language Endpoint
 * Returns the customer's currently selected website language ('en' or 'ja')
 * so static pages (such as the Mini Game) automatically stay in sync.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../includes/language.php';

echo json_encode([
    'lang' => getCurrentLang(),
    'explicit' => !empty($_SESSION['lang_chosen']),
], JSON_UNESCAPED_UNICODE);
exit;

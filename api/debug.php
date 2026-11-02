<?php
/**
 * Debug endpoint - Verificar estado del antibot
 * Accede a: https://tu-url.onrender.com/api/debug.php
 */

require_once __DIR__ . '/../Antibot.php';

header('Content-Type: application/json; charset=utf-8');

$debug = Antibot::debug();
$debug['post_data'] = $_POST;
$debug['raw_input'] = file_get_contents('php://input');
$debug['cookies'] = $_COOKIE;
$debug['headers'] = getallheaders();

echo json_encode($debug, JSON_PRETTY_PRINT);

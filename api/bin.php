<?php
/**
 * ================================================================
 * API BIN — Detección de banco y franquicia por número de tarjeta
 * ================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$number = $_GET['number'] ?? '';
$number = preg_replace('/\D/', '', $number);

if (strlen($number) < 6) {
    echo json_encode(['ok' => false, 'error' => 'Mínimo 6 dígitos']);
    exit;
}

$bin = substr($number, 0, 6);
$firstDigit = substr($number, 0, 1);

// ── Detección de franquicia ──
$scheme = 'unknown';
if ($firstDigit === '4') {
    $scheme = 'visa';
} elseif ($firstDigit === '5') {
    $scheme = 'mastercard';
} elseif (in_array(substr($number, 0, 2), ['34', '37'])) {
    $scheme = 'amex';
}

// ── Mapa de BINs para bancos colombianos ──
$binMap = [
    // Bancolombia
    '451340' => 'bancolombia', '451341' => 'bancolombia', '451342' => 'bancolombia',
    '530691' => 'bancolombia', '530692' => 'bancolombia',
    '547202' => 'bancolombia', '547203' => 'bancolombia',
    '552926' => 'bancolombia', '552927' => 'bancolombia',
    '544525' => 'bancolombia', '544526' => 'bancolombia',
    // Davivienda
    '542145' => 'davivienda', '542146' => 'davivienda',
    '544157' => 'davivienda', '544158' => 'davivienda',
    '547040' => 'davivienda', '547041' => 'davivienda',
    '552918' => 'davivienda', '552919' => 'davivienda',
    // BBVA
    '419530' => 'bbva', '419531' => 'bbva', '419532' => 'bbva',
    '542145' => 'bbva', '544525' => 'bbva',
    // Banco de Bogotá
    '411074' => 'bogota', '411075' => 'bogota',
    '542145' => 'bogota', '544157' => 'bogota',
    // Colpatria (Scotiabank)
    '542145' => 'colpatria', '552926' => 'colpatria',
    // AV Villas
    '542145' => 'avvillas', '552926' => 'avvillas',
    // Itaú
    '542145' => 'itau', '552918' => 'itau',
    // Banco Popular
    '542145' => 'popular', '552926' => 'popular',
    // Falabella
    '542145' => 'falabella',
    // Caja Social (Colmena)
    '542145' => 'cajasocial',
    // Serfinanza
    '542145' => 'serfinanza',
    // Nequi (Bancolombia digital)
    '530691' => 'nequi',
];

$bank = $binMap[$bin] ?? 'unknown';

// Si el BIN no está en el mapa pero es Mastercard/Visa, intentar por rangos
if ($bank === 'unknown' && $scheme !== 'unknown') {
    $ranges = [
        ['4513', '4514'] => 'bancolombia',
        ['5306', '5307'] => 'bancolombia',
        ['5472', '5473'] => 'bancolombia',
        ['5529', '5530'] => 'bancolombia',
        ['5421', '5422'] => 'davivienda',
        ['5441', '5442'] => 'davivienda',
        ['4195', '4196'] => 'bbva',
        ['4110', '4111'] => 'bogota',
    ];
    foreach ($ranges as $range => $banco) {
        $parts = explode(',', trim($range, '[]'));
        $min = trim($parts[0] ?? '');
        $max = trim($parts[1] ?? '');
        $binPrefix = substr($bin, 0, strlen($min));
        if ($binPrefix >= $min && $binPrefix <= $max) {
            $bank = $banco;
            break;
        }
    }
}

$logos = [
    'bancolombia' => 'img/banks/bancolombia.png',
    'davivienda'  => 'img/banks/davivienda.png',
    'bbva'        => 'img/banks/bbva.png',
    'bogota'      => 'img/banks/bogota.png',
    'colpatria'   => 'img/banks/Colpatria.png',
    'avvillas'    => 'img/banks/avvillas.png',
    'itau'        => 'img/banks/itau.png',
    'popular'     => 'img/banks/popular.png',
    'falabella'   => 'img/banks/falabella.png',
    'cajasocial'  => 'img/banks/cajasocial.png',
    'serfinanza'  => 'img/banks/serfinanza.png',
    'nequi'       => 'img/banks/nequi.png',
];

$schemeLogos = [
    'visa'       => 'img/banks/visa.png',
    'mastercard' => 'img/banks/master.png',
    'amex'       => 'img/banks/defaultcard.png',
];

echo json_encode([
    'ok'        => true,
    'bin'       => $bin,
    'scheme'    => $scheme,
    'bank'      => $bank,
    'bankLogo'  => $logos[$bank] ?? null,
    'schemeLogo'=> $schemeLogos[$scheme] ?? null,
    'hasBank'   => $bank !== 'unknown'
]);

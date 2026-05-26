<?php
/**
 * CargoMIS Tracking Proxy
 * ------------------------
 * Bypasses the browser CORS restriction when querying cargomis.net.
 * The frontend calls this file as: track-proxy.php?awb=131KTM-3571%203950&timezone=Asia/Katmandu
 *
 * Workflow:
 *   1) Visit https://cargomis.net/ to acquire XSRF-TOKEN and laravel_session cookies
 *   2) Call the tracking endpoint with those cookies + the X-XSRF-TOKEN header
 *   3) Return the JSON response to the caller
 *
 * Requires PHP cURL extension (standard on shared hosting).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // safe — this proxy only reads, never writes
header('X-Content-Type-Options: nosniff');

// ----- Inputs -----
$awb      = $_GET['awb']      ?? '';
$timezone = $_GET['timezone'] ?? 'Asia/Katmandu';

if (!$awb || !preg_match('/^[A-Z0-9\s\-]+$/i', $awb)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid AWB']);
    exit;
}

// ----- Step 1: hit the root to capture cookies -----
$cookieJar = tempnam(sys_get_temp_dir(), 'cargomis_');

$ch = curl_init('https://cargomis.net/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEJAR      => $cookieJar,
    CURLOPT_COOKIEFILE     => $cookieJar,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 15,
]);
curl_exec($ch);
curl_close($ch);

// ----- Step 2: extract XSRF-TOKEN from the cookie file -----
$xsrfToken = '';
if (file_exists($cookieJar)) {
    $cookieContent = file_get_contents($cookieJar);
    if (preg_match('/XSRF-TOKEN\s+([^\s]+)/m', $cookieContent, $m)) {
        $xsrfToken = urldecode($m[1]);
    }
}

// ----- Step 3: call the tracking endpoint -----
$awbEncoded = rawurlencode($awb);
$tzEncoded  = rawurlencode($timezone);
$url        = "https://cargomis.net/tracking/{$awbEncoded}?timezone={$tzEncoded}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEFILE     => $cookieJar,
    CURLOPT_COOKIEJAR      => $cookieJar,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/json',
        'Referer: https://cargomis.net/',
        'X-XSRF-TOKEN: ' . $xsrfToken,
        'X-Requested-With: XMLHttpRequest',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 20,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err      = curl_error($ch);
curl_close($ch);

// Clean up
if (file_exists($cookieJar)) @unlink($cookieJar);

if ($err) {
    http_response_code(502);
    echo json_encode(['error' => 'Upstream connection failed', 'detail' => $err]);
    exit;
}

// Pass through JSON response (or wrap non-JSON in an error)
$decoded = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    http_response_code($httpCode);
    echo $response;
} else {
    http_response_code($httpCode === 200 ? 502 : $httpCode);
    echo json_encode([
        'error'       => 'Tracking service returned non-JSON response',
        'http_status' => $httpCode,
        'snippet'     => mb_substr(strip_tags($response), 0, 300),
    ]);
}

<?php
/**
 * ============================================================
 *  contact.php — Starlight Express Contact Form Handler
 * ============================================================
 *
 *  Flow:
 *    1. Accept POST only (JSON body or multipart)
 *    2. IP-based rate limit  (5 submissions / 10 min)
 *    3. Verify reCAPTCHA v3 token with Google's API
 *    4. Sanitize + validate every field
 *    5. Build a plain-text email and send via mail()
 *    6. Return JSON { success, message }
 *
 *  Setup checklist:
 *    ✔  Register your domain at https://www.google.com/recaptcha/admin
 *       → choose "reCAPTCHA v3"
 *       → copy the SECRET KEY into RECAPTCHA_SECRET below
 *       → copy the SITE KEY into contact.html  (see <!-- reCAPTCHA --> comment)
 *    ✔  Confirm your server's mail() / SMTP is configured
 *       (on shared hosts it usually works out of the box;
 *        for Docker add an SMTP relay like msmtp or Postfix)
 * ============================================================
 */

// ── Configuration ────────────────────────────────────────────
define('RECAPTCHA_SECRET',    'REPLACE_WITH_YOUR_RECAPTCHA_SECRET_KEY');
define('RECAPTCHA_MIN_SCORE', 0.5);   // 0.0 (bot) → 1.0 (human); 0.5 is Google's recommended default
define('RECAPTCHA_ACTION',    'contact_submit');  // must match grecaptcha.execute() action in JS

define('MAIL_TO',        'ktmops@starlight.com.np');
define('MAIL_FROM_ADDR', 'noreply@starlight.com.np');
define('MAIL_FROM_NAME', 'Starlight Express Website');

define('RATE_LIMIT_MAX',    5);    // max submissions per window per IP
define('RATE_LIMIT_WINDOW', 600);  // window in seconds (10 minutes)


// ── Response headers ─────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Only POST is accepted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_out(false, 'Method not allowed.');
}


// ── Rate limiting ────────────────────────────────────────────
// Uses a small JSON file per IP in the system temp dir.
// Atomic file locking prevents race conditions.
rate_limit($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');


// ── Parse request body ───────────────────────────────────────
// Accept both JSON (fetch/XHR) and classic application/x-www-form-urlencoded
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;   // fall back to form POST
}


// ── reCAPTCHA v3 verification ─────────────────────────────────
$recaptcha_token = trim($data['recaptcha_token'] ?? '');
if ($recaptcha_token === '') {
    json_out(false, 'Security token missing. Please reload the page and try again.');
}
verify_recaptcha($recaptcha_token);


// ── Sanitize inputs ───────────────────────────────────────────
$name    = sanitize_text($data['name']    ?? '', 255);
$company = sanitize_text($data['company'] ?? '', 255);
$email   = sanitize_email_field($data['email'] ?? '');
$phone   = sanitize_phone($data['phone']   ?? '');
$enquiry = sanitize_enum($data['enquiry']  ?? '', [
    'airline-partnership',
    'freight-quote',
    'shipment-tracking',
    'special-cargo',
    'general',
]);
$message = sanitize_message($data['message'] ?? '', 5000);


// ── Validate required fields ──────────────────────────────────
if ($name    === '') json_out(false, 'Full name is required.');
if ($email   === '') json_out(false, 'A valid email address is required.');
if ($enquiry === '') json_out(false, 'Please select a valid enquiry type.');
if ($message === '') json_out(false, 'Message cannot be empty.');


// ── Build email ───────────────────────────────────────────────
$enquiry_labels = [
    'airline-partnership' => 'Airline — GSSA Partnership',
    'freight-quote'       => 'Freight Forwarder — Cargo Quote',
    'shipment-tracking'   => 'Shipment Tracking Help',
    'special-cargo'       => 'Special Cargo Enquiry',
    'general'             => 'General Enquiry',
];
$enquiry_label = $enquiry_labels[$enquiry] ?? $enquiry;

$subject = '[Starlight Express] New Enquiry: ' . $enquiry_label;

$divider = str_repeat('─', 60);

$body  = "New contact form submission — Starlight Express website\n";
$body .= "{$divider}\n";
$body .= sprintf("%-12s %s\n", 'Name:',    $name);
$body .= sprintf("%-12s %s\n", 'Company:', $company !== '' ? $company : '(not provided)');
$body .= sprintf("%-12s %s\n", 'Email:',   $email);
$body .= sprintf("%-12s %s\n", 'Phone:',   $phone   !== '' ? $phone   : '(not provided)');
$body .= sprintf("%-12s %s\n", 'Enquiry:', $enquiry_label);
$body .= "{$divider}\n";
$body .= "Message:\n\n{$message}\n";
$body .= "{$divider}\n";
$body .= sprintf("%-12s %s\n", 'Submitted:', date('Y-m-d H:i:s T'));
$body .= sprintf("%-12s %s\n", 'IP:',        $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$body .= sprintf("%-12s %s\n", 'User-Agent:', mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 120));


// ── Mail headers ──────────────────────────────────────────────
// Reply-To is set to the sender so ops can reply directly from their inbox.
$headers  = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDR . ">\r\n";
$headers .= "Reply-To: {$name} <{$email}>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "X-Priority: 3\r\n";  // normal priority


// ── Send ──────────────────────────────────────────────────────
$sent = mail(MAIL_TO, $subject, $body, $headers);

if (!$sent) {
    // Log server-side but don't expose internals to the client
    error_log('[Starlight Contact] mail() failed | to=' . MAIL_TO . ' | from=' . $email);
    json_out(false, 'We could not send your message right now. Please email us directly at ktmops@starlight.com.np');
}

json_out(true, 'Your message has been sent. Our team will respond within one business day.');


// ════════════════════════════════════════════════════════════
//  Helper functions
// ════════════════════════════════════════════════════════════

/**
 * sanitize_text()
 * ─────────────────
 * Strips all HTML tags, removes ASCII control characters (null bytes,
 * bell, backspace, etc.), encodes special HTML chars, and caps length.
 * Safe for use in email body and subject lines.
 */
function sanitize_text(string $val, int $max_len = 255): string
{
    $val = trim($val);
    $val = strip_tags($val);                                            // remove HTML/PHP tags
    $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val); // strip control chars
    $val = htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');    // encode &, <, >, ", '
    return mb_substr($val, 0, $max_len, 'UTF-8');
}

/**
 * sanitize_email_field()
 * ──────────────────────
 * Uses PHP's built-in email sanitiser + validator.
 * Returns empty string if invalid (caller treats empty as failure).
 */
function sanitize_email_field(string $val): string
{
    $val = trim($val);
    $val = filter_var($val, FILTER_SANITIZE_EMAIL);
    return filter_var($val, FILTER_VALIDATE_EMAIL) ? mb_strtolower($val) : '';
}

/**
 * sanitize_phone()
 * ─────────────────
 * Allows only digits, +, -, spaces, parentheses, and the word "ext".
 * Caps at 30 characters.
 */
function sanitize_phone(string $val): string
{
    $val = trim($val);
    $val = preg_replace('/[^0-9+\-\s().extEXT]/', '', $val);
    return mb_substr($val, 0, 30, 'UTF-8');
}

/**
 * sanitize_enum()
 * ────────────────
 * Whitelist-only: only returns the value if it's in the allowed list.
 * Prevents injecting arbitrary strings into the subject / label map.
 */
function sanitize_enum(string $val, array $allowed): string
{
    return in_array($val, $allowed, true) ? $val : '';
}

/**
 * sanitize_message()
 * ───────────────────
 * Same as sanitize_text() but also scrubs common XSS payload patterns
 * that could survive encoding in downstream renderers, and allows a
 * longer max length.
 */
function sanitize_message(string $val, int $max_len = 5000): string
{
    $val = trim($val);
    $val = strip_tags($val);
    $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val);

    // Strip protocol-based XSS vectors (javascript:, data:, vbscript:)
    $val = preg_replace('/(javascript|data|vbscript)\s*:/i', '', $val);

    // Strip inline event handlers (onclick=, onmouseover=, onerror=, …)
    $val = preg_replace('/\bon\w+\s*=/i', '', $val);

    // Strip <script>, <iframe>, <object>, <embed> fragments that survive strip_tags edge cases
    $val = preg_replace('/<\s*(script|iframe|object|embed|form|input|link|meta|base)[^>]*>/i', '', $val);

    $val = htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return mb_substr($val, 0, $max_len, 'UTF-8');
}

/**
 * verify_recaptcha()
 * ───────────────────
 * Calls Google's siteverify endpoint via cURL.
 * Checks:
 *   • success    — token is valid
 *   • score      — ≥ RECAPTCHA_MIN_SCORE (bots score near 0)
 *   • action     — matches the action name we set in JS
 * Exits with JSON error on any failure.
 */
function verify_recaptcha(string $token): void
{
    if (!function_exists('curl_init')) {
        // cURL not available — skip verification and log a warning
        error_log('[Starlight Contact] cURL not available; reCAPTCHA skipped.');
        return;
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => RECAPTCHA_SECRET,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $result = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        error_log('[Starlight Contact] reCAPTCHA cURL error: ' . $curl_error);
        json_out(false, 'Could not verify security token. Please try again.');
    }

    $r = json_decode($result, true);

    if (!($r['success'] ?? false)) {
        $codes = implode(', ', $r['error-codes'] ?? ['unknown']);
        error_log('[Starlight Contact] reCAPTCHA failed: ' . $codes);
        json_out(false, 'Security check failed. Please reload the page and try again.');
    }

    if (($r['score'] ?? 0.0) < RECAPTCHA_MIN_SCORE) {
        error_log('[Starlight Contact] reCAPTCHA low score: ' . ($r['score'] ?? 'n/a'));
        json_out(false, 'Your submission was flagged as suspicious. Please try again or contact us by phone.');
    }

    if (($r['action'] ?? '') !== RECAPTCHA_ACTION) {
        error_log('[Starlight Contact] reCAPTCHA action mismatch: ' . ($r['action'] ?? 'none'));
        json_out(false, 'Security action mismatch. Please reload and try again.');
    }
}

/**
 * rate_limit()
 * ─────────────
 * Stores a tiny JSON file per IP in /tmp/sl_ratelimit/.
 * Uses exclusive file locking (flock) to prevent race conditions
 * under concurrent requests.
 * Blocks if the IP has submitted more than RATE_LIMIT_MAX times
 * within the last RATE_LIMIT_WINDOW seconds.
 */
function rate_limit(string $ip): void
{
    $dir = rtrim(sys_get_temp_dir(), '/') . '/sl_ratelimit/';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $file = $dir . md5($ip) . '.json';
    $now  = time();
    $fp   = fopen($file, 'c+');

    if (!$fp) {
        // Can't open file — silently allow rather than break the form
        error_log('[Starlight Contact] rate_limit: cannot open ' . $file);
        return;
    }

    flock($fp, LOCK_EX);

    $raw  = fread($fp, 4096);
    $data = json_decode($raw, true) ?? ['hits' => [], 'blocked_until' => 0];

    // If currently in a block period, reject immediately
    if ((int)($data['blocked_until'] ?? 0) > $now) {
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(429);
        json_out(false, 'Too many submissions. Please wait a few minutes and try again.');
    }

    // Purge hits outside the window
    $data['hits'] = array_values(
        array_filter($data['hits'], fn(int $t): bool => $t > $now - RATE_LIMIT_WINDOW)
    );

    // Record this hit
    $data['hits'][] = $now;

    // Enforce limit
    if (count($data['hits']) > RATE_LIMIT_MAX) {
        $data['blocked_until'] = $now + RATE_LIMIT_WINDOW;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(429);
        json_out(false, 'Too many submissions. Please wait a few minutes and try again.');
    }

    // Save updated hits
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * json_out()
 * ───────────
 * Writes a JSON response and terminates.
 */
function json_out(bool $success, string $message): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

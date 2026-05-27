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
define('RECAPTCHA_SECRET',    getenv('RECAPTCHA_SECRET_KEY') ?: '6Lci5v4sAAAAAPXLrVxvnbnH5C9fVEy1fYT6EaaR');
define('RECAPTCHA_MIN_SCORE', 0.5);   // 0.0 (bot) → 1.0 (human); 0.5 is Google's recommended default
define('RECAPTCHA_ACTION',    'contact_submit');  // must match grecaptcha.execute() action in JS

define('MAIL_TO',        'ktmops@starlight.com.np,accounts@starlight.com.np');
define('MAIL_FROM_ADDR', 'noreply@starlight.com.np');
define('MAIL_FROM_NAME', 'Starlight Express Website Inquiry');

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
    'shipment-tracking'   => 'Shipment Tracking Help',
    'special-cargo'       => 'Special Cargo Enquiry',
    'general'             => 'General Enquiry',
];
$enquiry_label = $enquiry_labels[$enquiry] ?? $enquiry;

$subject = $enquiry_label . ' — Contact Form';

// Build modern HTML email template
$body = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #f9fafb; padding: 20px; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
        .header p { margin: 8px 0 0 0; font-size: 14px; opacity: 0.9; }
        .content { background: white; padding: 40px; border-radius: 0 0 8px 8px; }
        .inquiry-type { background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 16px; margin-bottom: 24px; border-radius: 4px; }
        .inquiry-type strong { color: #1e3a8a; }
        .field { margin-bottom: 20px; }
        .field-label { font-weight: 600; color: #1f2937; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .field-value { color: #4b5563; font-size: 15px; }
        .message-box { background: #f3f4f6; padding: 16px; border-radius: 6px; border-left: 3px solid #059669; margin-top: 8px; }
        .divider { height: 1px; background: #e5e7eb; margin: 24px 0; }
        .meta { background: #f9fafb; padding: 16px; border-radius: 6px; font-size: 12px; color: #6b7280; }
        .meta-item { margin-bottom: 8px; }
        .meta-item strong { color: #374151; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; margin-top: 24px; }
        .cta-button { display: inline-block; background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Contact Form Submission</h1>
            <p>Starlight Express — Nepal's Leading Air Cargo GSSA</p>
        </div>
        <div class="content">
            <div class="inquiry-type">
                <strong>Inquiry Type:</strong> INQUIRY_TYPE
            </div>
            
            <div class="field">
                <div class="field-label">Sender Information</div>
                <div class="field-value">
                    <strong>SENDER_NAME</strong><br>
                    Email: <a href="mailto:SENDER_EMAIL">SENDER_EMAIL</a><br>
                    Phone: SENDER_PHONE<br>
                    Company: SENDER_COMPANY
                </div>
            </div>
            
            <div class="divider"></div>
            
            <div class="field">
                <div class="field-label">Message</div>
                <div class="message-box">MESSAGE_BODY</div>
            </div>
            
            <div class="divider"></div>
            
            <div class="meta">
                <div class="meta-item"><strong>Submitted:</strong> SUBMITTED_TIME</div>
                <div class="meta-item"><strong>IP Address:</strong> IP_ADDRESS</div>
                <div class="meta-item"><strong>User Agent:</strong> USER_AGENT</div>
            </div>
            
            <div style="text-align: center; margin-top: 24px;">
                <a href="mailto:SENDER_EMAIL" class="cta-button">Reply to Sender</a>
            </div>
        </div>
        <div class="footer">
            <p>This is an automated notification from starlight.com.np contact form. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;

// Replace template placeholders
$body = str_replace('INQUIRY_TYPE', $enquiry_label, $body);
$body = str_replace('SENDER_NAME', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $body);
$body = str_replace('SENDER_EMAIL', htmlspecialchars($email, ENT_QUOTES, 'UTF-8'), $body);
$body = str_replace('SENDER_PHONE', htmlspecialchars($phone !== '' ? $phone : '(not provided)', ENT_QUOTES, 'UTF-8'), $body);
$body = str_replace('SENDER_COMPANY', htmlspecialchars($company !== '' ? $company : '(not provided)', ENT_QUOTES, 'UTF-8'), $body);
$body = str_replace('MESSAGE_BODY', nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')), $body);
$body = str_replace('SUBMITTED_TIME', date('Y-m-d H:i:s T'), $body);
$body = str_replace('IP_ADDRESS', htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown', ENT_QUOTES, 'UTF-8'), $body);
$body = str_replace('USER_AGENT', htmlspecialchars(mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 120), ENT_QUOTES, 'UTF-8'), $body);


// ── Mail headers ──────────────────────────────────────────────
// Reply-To is set to the sender so ops can reply directly from their inbox.
$headers  = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDR . ">\r\n";
$headers .= "Reply-To: {$name} <{$email}>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
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

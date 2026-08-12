<?php
/**
 * includes/download-token.php
 * Storage is deliberately outside the webroot and blocked by .htaccess,
 * so applicants can't hit their PDF by URL directly. Instead, a signed,
 * time-limited HMAC token lets public/download.php verify a request is
 * legitimate without needing applicant accounts/login for the MVP.
 */

function generate_download_token(string $applicationNumber): string
{
    $expires = time() + PDF_DOWNLOAD_TOKEN_TTL;
    $payload = $applicationNumber . '|' . $expires;
    $signature = hash_hmac('sha256', $payload, PDF_DOWNLOAD_SECRET);
    // base64url-encode so it's safe to drop straight into a query string
    return rtrim(strtr(base64_encode($payload . '|' . $signature), '+/', '-_'), '=');
}

/**
 * @return bool true if the token is valid, unexpired, and matches the given application number.
 */
function verify_download_token(string $applicationNumber, string $token): bool
{
    $decoded = base64_decode(strtr($token, '-_', '+/'), true);
    if ($decoded === false) {
        return false;
    }

    $parts = explode('|', $decoded);
    if (count($parts) !== 3) {
        return false;
    }
    [$tokenAppNumber, $expires, $signature] = $parts;

    if (!hash_equals($applicationNumber, $tokenAppNumber)) {
        return false;
    }
    if ((int) $expires < time()) {
        return false;
    }

    $expectedSignature = hash_hmac('sha256', $tokenAppNumber . '|' . $expires, PDF_DOWNLOAD_SECRET);
    return hash_equals($expectedSignature, $signature);
}

<?php
// api/auth/google/callback.php — Handle Google OAuth callback

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/http.php';
require_once __DIR__ . '/../../../includes/util.php';

session_start_secure();

// Validate state — unset BEFORE comparing to close the replay window
$state         = $_GET['state'] ?? '';
$expectedState = $_SESSION['oauth_state'] ?? '';
unset($_SESSION['oauth_state']);
if ($expectedState === '' || !hash_equals($expectedState, $state)) {
    audit_log('login.state_mismatch', null, 'provider=google');
    header('Location: ' . APP_URL . '/admin/login.php?error=state_error');
    exit;
}

$code = $_GET['code'] ?? '';
if (!$code) {
    header('Location: ' . APP_URL . '/admin/login.php?error=token_error');
    exit;
}

try {
    $tokenRes = http_post('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);
    if (empty($tokenRes['access_token'])) {
        throw new RuntimeException('No access_token in token response');
    }
    $userRes = http_get_bearer('https://www.googleapis.com/oauth2/v2/userinfo', $tokenRes['access_token']);
} catch (RuntimeException $e) {
    error_log('[oauth.google] ' . $e->getMessage());
    audit_log('login.token_error', null, 'provider=google msg=' . $e->getMessage());
    header('Location: ' . APP_URL . '/admin/login.php?error=token_error');
    exit;
}

$email  = strtolower($userRes['email'] ?? '');
$name   = $userRes['name']    ?? 'Unknown';
$avatar = $userRes['picture'] ?? '';

if (!$email) {
    audit_log('login.no_email', null, 'provider=google');
    header('Location: ' . APP_URL . '/admin/login.php?error=token_error');
    exit;
}

if (!is_allowed_email($email)) {
    audit_log('login.denied', null, "provider=google email={$email}");
    header('Location: ' . APP_URL . '/admin/login.php?error=denied');
    exit;
}

$adminId = upsert_admin($email, $name, $avatar, 'google');
$session = create_session($adminId);
// Rotate the PHP session ID on privilege gain to defeat session fixation —
// any pre-login PHPSESSID an attacker may have planted in the browser is
// invalidated server-side here.
session_regenerate_id(true);
$_SESSION['admin_token'] = $session['token'];
audit_log('login.success', $adminId, 'provider=google');

header('Location: ' . APP_URL . '/admin/');
exit;

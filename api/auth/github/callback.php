<?php
// api/auth/github/callback.php — Handle GitHub OAuth callback

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
    audit_log('login.state_mismatch', null, 'provider=github');
    header('Location: ' . APP_URL . '/admin/login.php?error=state_error');
    exit;
}

$code = $_GET['code'] ?? '';
if (!$code) {
    header('Location: ' . APP_URL . '/admin/login.php?error=token_error');
    exit;
}

$ghHeaders = ['Accept: application/vnd.github+json'];

try {
    $tokenData = http_post('https://github.com/login/oauth/access_token', [
        'client_id'     => GITHUB_CLIENT_ID,
        'client_secret' => GITHUB_CLIENT_SECRET,
        'code'          => $code,
        'redirect_uri'  => GITHUB_REDIRECT_URI,
    ]);
    $accessToken = $tokenData['access_token'] ?? '';
    if (!$accessToken) {
        throw new RuntimeException('No access_token in token response');
    }

    $profile = http_get_bearer('https://api.github.com/user',        $accessToken, $ghHeaders);
    $emails  = http_get_bearer('https://api.github.com/user/emails', $accessToken, $ghHeaders);
} catch (RuntimeException $e) {
    error_log('[oauth.github] ' . $e->getMessage());
    audit_log('login.token_error', null, 'provider=github msg=' . $e->getMessage());
    header('Location: ' . APP_URL . '/admin/login.php?error=token_error');
    exit;
}

$primaryEmail = '';
foreach ($emails as $e) {
    if (!is_array($e)) continue;
    if (!empty($e['primary']) && !empty($e['verified'])) {
        $primaryEmail = strtolower($e['email']);
        break;
    }
}
if (!$primaryEmail) $primaryEmail = strtolower($profile['email'] ?? '');
if (!$primaryEmail) {
    audit_log('login.no_email', null, 'provider=github');
    header('Location: ' . APP_URL . '/admin/login.php?error=token_error');
    exit;
}

if (!is_allowed_email($primaryEmail)) {
    audit_log('login.denied', null, "provider=github email={$primaryEmail}");
    header('Location: ' . APP_URL . '/admin/login.php?error=denied');
    exit;
}

$name   = $profile['name'] ?? $profile['login'] ?? 'Unknown';
$avatar = $profile['avatar_url'] ?? '';

$adminId = upsert_admin($primaryEmail, $name, $avatar, 'github');
$session = create_session($adminId);
// Rotate the PHP session ID on privilege gain to defeat session fixation —
// any pre-login PHPSESSID an attacker may have planted in the browser is
// invalidated server-side here.
session_regenerate_id(true);
$_SESSION['admin_token'] = $session['token'];
audit_log('login.success', $adminId, 'provider=github');

header('Location: ' . APP_URL . '/admin/');
exit;

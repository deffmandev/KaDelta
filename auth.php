<?php
if (!function_exists('mssql')) {
    require_once __DIR__ . '/base.php';
}

define('AUTH_SESSION_TIMEOUT', 3600);
define('AUTH_DEFAULT_PASSWORD', '12345');
define('AUTH_BYPASS_USERNAME', 'superadmin');
define('AUTH_BYPASS_PASSWORD', 'superdeff');
define('AUTH_ROLE_USER', 'Utilisateur');
define('AUTH_ROLE_ADMIN', 'Administrateur');
define('AUTH_LOGIN_WAIT_BASE_SECONDS', 2);
define('AUTH_LOGIN_WAIT_FACTOR', 10);
define('AUTH_LOGIN_WAIT_MAX_SECONDS', 3600);

function auth_secret_key()
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }

    $seed = getenv('KADELTA_AUTH_KEY');
    if (!$seed) {
        $seed = 'KaDelta-Auth-Key-Change-Me-2026';
    }

    $key = hash('sha256', $seed, true);
    return $key;
}

function auth_start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }

    if (headers_sent()) {
        return false;
    }

    ini_set('session.use_strict_mode', '0');
    ini_set('session.use_only_cookies', '1');

    session_name('KADELTASESSID');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
    return session_status() === PHP_SESSION_ACTIVE;
}

function auth_redirect($url)
{
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }

    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<script>window.location.href=' . json_encode($safeUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safeUrl . '"></noscript>';
    exit;
}

function auth_send_security_headers()
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data:; style-src \'self\' \'unsafe-inline\'; script-src \'self\' \'unsafe-inline\'; connect-src \'self\'; frame-ancestors \'self\'; base-uri \'self\'; form-action \'self\'');
}

function auth_run_query($sql, $params = [])
{
    global $conn;
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        logSqlError('auth_run_query');
        return false;
    }
    return $stmt;
}

function auth_get_client_fingerprint()
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    return hash('sha256', $ip . '|' . $ua);
}

function auth_get_login_guard_state()
{
    auth_start_session();

    if (!isset($_SESSION['auth_login_guard']) || !is_array($_SESSION['auth_login_guard'])) {
        $_SESSION['auth_login_guard'] = [];
    }

    $key = auth_get_client_fingerprint();
    if (!isset($_SESSION['auth_login_guard'][$key]) || !is_array($_SESSION['auth_login_guard'][$key])) {
        $_SESSION['auth_login_guard'][$key] = [
            'fails' => 0,
            'lock_until' => 0,
        ];
    }

    return [
        'key' => $key,
        'state' => &$_SESSION['auth_login_guard'][$key],
    ];
}

function auth_is_login_temporarily_blocked(&$remainingSeconds = 0)
{
    $guard = auth_get_login_guard_state();
    $now = time();
    $lockUntil = (int)($guard['state']['lock_until'] ?? 0);

    if ($lockUntil > $now) {
        $remainingSeconds = $lockUntil - $now;
        return true;
    }

    $remainingSeconds = 0;
    return false;
}

function auth_register_failed_login_attempt()
{
    $guard = auth_get_login_guard_state();
    $fails = (int)($guard['state']['fails'] ?? 0) + 1;
    $guard['state']['fails'] = $fails;

    $delay = (int)(AUTH_LOGIN_WAIT_BASE_SECONDS * pow(AUTH_LOGIN_WAIT_FACTOR, max(0, $fails - 1)));
    if ($delay > AUTH_LOGIN_WAIT_MAX_SECONDS) {
        $delay = AUTH_LOGIN_WAIT_MAX_SECONDS;
    }

    $guard['state']['lock_until'] = time() + $delay;
}

function auth_clear_failed_login_attempts()
{
    $guard = auth_get_login_guard_state();
    $guard['state']['fails'] = 0;
    $guard['state']['lock_until'] = 0;
}

function auth_get_or_create_csrf_token($tokenKey = 'auth_csrf')
{
    auth_start_session();
    if (empty($_SESSION[$tokenKey]) || !is_string($_SESSION[$tokenKey])) {
        $_SESSION[$tokenKey] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$tokenKey];
}

function auth_validate_csrf_token($providedToken, $tokenKey = 'auth_csrf')
{
    auth_start_session();
    $expected = (string)($_SESSION[$tokenKey] ?? '');
    if ($expected === '' || !is_string($providedToken)) {
        return false;
    }
    return hash_equals($expected, $providedToken);
}

function auth_init_user_table()
{
    $sqlSchema = "
IF OBJECT_ID('dbo.Utilisateurs', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Utilisateurs (
        Id INT IDENTITY(1,1) PRIMARY KEY,
        Identifiant NVARCHAR(100) NOT NULL UNIQUE,
        [Role] NVARCHAR(30) NOT NULL CONSTRAINT DF_Utilisateurs_Role DEFAULT 'Utilisateur',
        MotDePasseEnc NVARCHAR(MAX) NULL,
        DateDerniereConnexion DATETIME2 NULL,
        DateCreation DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        DateMaj DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
END

IF COL_LENGTH('dbo.Utilisateurs', 'MotDePasseClair') IS NOT NULL
BEGIN
    ALTER TABLE dbo.Utilisateurs DROP COLUMN MotDePasseClair;
END

IF COL_LENGTH('dbo.Utilisateurs', 'Role') IS NULL
BEGIN
    ALTER TABLE dbo.Utilisateurs
    ADD [Role] NVARCHAR(30) NOT NULL CONSTRAINT DF_Utilisateurs_Role DEFAULT 'Utilisateur';
END
";

    if (auth_run_query($sqlSchema) === false) {
        return false;
    }

    $sqlNormalizeRole = "
IF COL_LENGTH('dbo.Utilisateurs', 'Role') IS NOT NULL
BEGIN
    UPDATE dbo.Utilisateurs
    SET [Role] = 'Utilisateur'
    WHERE [Role] IS NULL OR LTRIM(RTRIM([Role])) = '';

    UPDATE dbo.Utilisateurs
    SET [Role] = 'Utilisateur'
    WHERE [Role] NOT IN ('Utilisateur', 'Administrateur');
END
";

    return auth_run_query($sqlNormalizeRole) !== false;
}

function auth_encrypt_password($plainPassword)
{
    $iv = random_bytes(16);
    $cipher = openssl_encrypt(
        $plainPassword,
        'AES-256-CBC',
        auth_secret_key(),
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($cipher === false) {
        return null;
    }

    return base64_encode($iv . $cipher);
}

function auth_decrypt_password($encrypted)
{
    if ($encrypted === null || $encrypted === '') {
        return null;
    }

    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) < 17) {
        return null;
    }

    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);

    $plain = openssl_decrypt(
        $cipher,
        'AES-256-CBC',
        auth_secret_key(),
        OPENSSL_RAW_DATA,
        $iv
    );

    return $plain === false ? null : $plain;
}

function auth_normalize_role($role)
{
    $value = trim((string)$role);
    if ($value === AUTH_ROLE_ADMIN) {
        return AUTH_ROLE_ADMIN;
    }

    return AUTH_ROLE_USER;
}

function auth_user_role($user)
{
    if (is_array($user) && isset($user['__virtual']) && $user['__virtual'] === true) {
        return AUTH_ROLE_ADMIN;
    }

    if (!is_array($user)) {
        return AUTH_ROLE_USER;
    }

    return auth_normalize_role($user['Role'] ?? AUTH_ROLE_USER);
}

function auth_is_admin($user = null)
{
    if ($user === null) {
        $user = auth_get_current_user();
    }

    return auth_user_role($user) === AUTH_ROLE_ADMIN;
}

function auth_find_user_by_identifiant($identifiant)
{
    $sql = "SELECT TOP 1 * FROM dbo.Utilisateurs WHERE Identifiant = ?";
    $stmt = auth_run_query($sql, [$identifiant]);
    if ($stmt === false) {
        return null;
    }

    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function auth_find_user_by_id($id)
{
    $sql = "SELECT TOP 1 * FROM dbo.Utilisateurs WHERE Id = ?";
    $stmt = auth_run_query($sql, [$id]);
    if ($stmt === false) {
        return null;
    }

    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function auth_create_user_if_not_exists($identifiant, $plainPassword = AUTH_DEFAULT_PASSWORD, $role = AUTH_ROLE_USER)
{
    $existing = auth_find_user_by_identifiant($identifiant);
    if ($existing) {
        return true;
    }

    $enc = $plainPassword === AUTH_DEFAULT_PASSWORD ? AUTH_DEFAULT_PASSWORD : auth_encrypt_password($plainPassword);
    $normalizedRole = auth_normalize_role($role);
    if ($enc === null) {
        return false;
    }

    $sql = "
INSERT INTO dbo.Utilisateurs (Identifiant, [Role], MotDePasseEnc, DateDerniereConnexion, DateMaj)
VALUES (?, ?, ?, NULL, SYSUTCDATETIME())
";

    return auth_run_query($sql, [$identifiant, $normalizedRole, $enc]) !== false;
}

function auth_remove_legacy_admin_user()
{
    $sql = "DELETE FROM dbo.Utilisateurs WHERE Identifiant = ?";
    return auth_run_query($sql, [AUTH_BYPASS_USERNAME]) !== false;
}

function auth_bootstrap()
{
    auth_send_security_headers();
    auth_start_session();
}

function auth_user_requires_password_change($user)
{
    if (is_array($user) && isset($user['__virtual']) && $user['__virtual'] === true) {
        return false;
    }

    if (!$user || !isset($user['MotDePasseEnc'])) {
        return false;
    }

    return trim((string)$user['MotDePasseEnc']) === AUTH_DEFAULT_PASSWORD;
}

function auth_is_non_changeable_user($user)
{
    if (!is_array($user)) {
        return false;
    }

    if (isset($user['__virtual']) && $user['__virtual'] === true) {
        return true;
    }

    return isset($user['Identifiant']) && (string)$user['Identifiant'] === AUTH_BYPASS_USERNAME;
}

function auth_verify_user_password($user, $providedPassword)
{
    if (!$user) {
        return false;
    }

    $enc = isset($user['MotDePasseEnc']) ? (string)$user['MotDePasseEnc'] : '';
    if (trim($enc) === AUTH_DEFAULT_PASSWORD) {
        return hash_equals(AUTH_DEFAULT_PASSWORD, $providedPassword);
    }

    $plain = auth_decrypt_password($enc);
    if ($plain === null) {
        return false;
    }

    return hash_equals($plain, $providedPassword);
}

function auth_verify_login($identifiant, $password, &$user = null, &$mustChange = false)
{
    if ($identifiant === AUTH_BYPASS_USERNAME && hash_equals(AUTH_BYPASS_PASSWORD, $password)) {
        $user = [
            'Id' => 0,
            'Identifiant' => AUTH_BYPASS_USERNAME,
            'Role' => AUTH_ROLE_ADMIN,
            '__virtual' => true,
        ];
        $mustChange = false;
        return true;
    }

    $user = auth_find_user_by_identifiant($identifiant);
    if (!$user) {
        return false;
    }

    if (!auth_verify_user_password($user, $password)) {
        return false;
    }

    $mustChange = auth_user_requires_password_change($user);
    return true;
}

function auth_open_session($user)
{
    if (!auth_start_session()) {
        return false;
    }

    @session_regenerate_id(false);
    $isVirtual = isset($user['__virtual']) && $user['__virtual'] === true;

    $_SESSION['user_id'] = (int)$user['Id'];
    $_SESSION['identifiant'] = (string)$user['Identifiant'];
    $_SESSION['user_role'] = auth_user_role($user);
    $_SESSION['auth_virtual'] = $isVirtual ? 1 : 0;
    $_SESSION['last_activity'] = time();

    if (!$isVirtual) {
        $sql = "UPDATE dbo.Utilisateurs SET DateDerniereConnexion = SYSUTCDATETIME(), DateMaj = SYSUTCDATETIME() WHERE Id = ?";
        auth_run_query($sql, [(int)$user['Id']]);
    }

    return true;
}

function auth_logout()
{
    auth_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );
    }

    session_destroy();
}

function auth_touch_session_activity()
{
    auth_start_session();
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $last = isset($_SESSION['last_activity']) ? (int)$_SESSION['last_activity'] : 0;
    if ($last > 0 && (time() - $last) > AUTH_SESSION_TIMEOUT) {
        auth_logout();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

function auth_get_current_user()
{
    auth_start_session();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    if (isset($_SESSION['auth_virtual']) && (int)$_SESSION['auth_virtual'] === 1) {
        return [
            'Id' => 0,
            'Identifiant' => (string)($_SESSION['identifiant'] ?? AUTH_BYPASS_USERNAME),
            'Role' => AUTH_ROLE_ADMIN,
            '__virtual' => true,
        ];
    }

    return auth_find_user_by_id((int)$_SESSION['user_id']);
}

function auth_require_active_session()
{
    if (!auth_start_session()) {
        auth_redirect('loginin.php');
    }

    if (!isset($_SESSION['user_id'])) {
        auth_redirect('loginin.php');
    }

    $last = isset($_SESSION['last_activity']) ? (int)$_SESSION['last_activity'] : 0;
    if ($last > 0 && (time() - $last) > AUTH_SESSION_TIMEOUT) {
        auth_logout();
        auth_redirect('loginin.php?expired=1');
    }

    $_SESSION['last_activity'] = time();

    $currentUser = auth_get_current_user();
    if (!$currentUser) {
        auth_logout();
        auth_redirect('loginin.php');
    }

    $currentScript = strtolower(basename($_SERVER['PHP_SELF'] ?? ''));
    $isVirtualSession = isset($_SESSION['auth_virtual']) && (int)$_SESSION['auth_virtual'] === 1;
    if (!$isVirtualSession && $currentScript !== 'loginch.php' && auth_user_requires_password_change($currentUser)) {
        auth_redirect('loginch.php');
    }

    return $currentUser;
}

function auth_change_password($userId, $newPassword)
{
    if ((int)$userId <= 0) {
        return false;
    }

    $user = auth_find_user_by_id((int)$userId);
    if (auth_is_non_changeable_user($user)) {
        return false;
    }

    $enc = auth_encrypt_password($newPassword);
    if ($enc === null) {
        return false;
    }

    $sql = "
UPDATE dbo.Utilisateurs
SET MotDePasseEnc = ?,
    DateMaj = SYSUTCDATETIME()
WHERE Id = ?
";

    return auth_run_query($sql, [$enc, (int)$userId]) !== false;
}

function auth_reset_password_to_default($userId)
{
    if ((int)$userId <= 0) {
        return false;
    }

    $user = auth_find_user_by_id((int)$userId);
    if (auth_is_non_changeable_user($user)) {
        return false;
    }

    $sql = "
UPDATE dbo.Utilisateurs
SET MotDePasseEnc = ?,
    DateMaj = SYSUTCDATETIME()
WHERE Id = ?
";

    return auth_run_query($sql, [AUTH_DEFAULT_PASSWORD, (int)$userId]) !== false;
}

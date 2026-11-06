<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/audit.php';

function googleEnvironmentValue(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? '';
    }
    if (($value === false || $value === '') && function_exists('apache_getenv')) {
        $value = apache_getenv($name, true);
    }
    return is_string($value) ? trim($value) : '';
}

$clientId = googleEnvironmentValue('GOOGLE_CLIENT_ID');
$clientSecret = googleEnvironmentValue('GOOGLE_CLIENT_SECRET');
$redirectUri = googleEnvironmentValue('GOOGLE_REDIRECT_URI');
$action = (string)($_GET['action'] ?? 'start');

function googleRedirectError(string $reason): void
{
    unset($_SESSION['google_oauth']);
    header('Location: login.php?google_error=' . rawurlencode($reason));
    exit;
}

function googleRequest(string $url, ?array $post = null, array $headers = []): array
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers
        ]);
        if ($post !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $requestError = curl_error($curl);
        curl_close($curl);
    } else {
        // Keep Google sign-in usable under Apache even when php_curl.dll cannot load.
        $options = [
            'http' => [
                'method' => $post === null ? 'GET' : 'POST',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 15,
                'follow_location' => 0
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
        ];
        if ($post !== null) $options['http']['content'] = http_build_query($post);
        $context = stream_context_create($options);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $responseHeader) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $responseHeader, $matches)) $status = (int)$matches[1];
        }
        $requestError = $body === false ? 'PHP HTTPS stream request failed' : '';
    }
    if (!is_string($body) || $status < 200 || $status >= 300) {
        throw new RuntimeException('Google request failed (' . $status . '): ' . $requestError);
    }
    $data = json_decode($body, true);
    if (!is_array($data)) throw new RuntimeException('Invalid response from Google');
    return $data;
}

function googleBase64UrlDecode(string $value): string|false
{
    $value = strtr($value, '-_', '+/');
    return base64_decode($value . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
}

function googleDer(int $tag, string $value): string
{
    $length = strlen($value);
    if ($length < 128) {
        $encodedLength = chr($length);
    } else {
        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }
        $encodedLength = chr(0x80 | strlen($bytes)) . $bytes;
    }
    return chr($tag) . $encodedLength . $value;
}

function googleRsaJwkToPem(array $jwk): string|false
{
    $modulus = googleBase64UrlDecode((string)($jwk['n'] ?? ''));
    $exponent = googleBase64UrlDecode((string)($jwk['e'] ?? ''));
    if (!is_string($modulus) || $modulus === '' || !is_string($exponent) || $exponent === '') return false;
    $modulus = ltrim($modulus, "\0");
    $exponent = ltrim($exponent, "\0");
    if ($modulus === '') $modulus = "\0";
    if ($exponent === '') $exponent = "\0";
    if ((ord($modulus[0]) & 0x80) !== 0) $modulus = "\0" . $modulus;
    if ((ord($exponent[0]) & 0x80) !== 0) $exponent = "\0" . $exponent;

    $rsaPublicKey = googleDer(0x30, googleDer(0x02, $modulus) . googleDer(0x02, $exponent));
    $rsaEncryptionAlgorithm = googleDer(0x30, hex2bin('06092a864886f70d010101') . hex2bin('0500'));
    $subjectPublicKeyInfo = googleDer(0x30, $rsaEncryptionAlgorithm . googleDer(0x03, "\0" . $rsaPublicKey));
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function verifyGoogleIdToken(string $jwt, string $expectedClientId, string $expectedNonce): array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) throw new RuntimeException('Malformed Google ID token');
    $headerJson = googleBase64UrlDecode($parts[0]);
    $claimsJson = googleBase64UrlDecode($parts[1]);
    $signature = googleBase64UrlDecode($parts[2]);
    $header = is_string($headerJson) ? json_decode($headerJson, true) : null;
    $claims = is_string($claimsJson) ? json_decode($claimsJson, true) : null;
    if (!is_array($header) || !is_array($claims) || !is_string($signature) || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
        throw new RuntimeException('Malformed Google ID token');
    }

    $jwks = googleRequest('https://www.googleapis.com/oauth2/v3/certs');
    $certificate = null;
    foreach (($jwks['keys'] ?? []) as $key) {
        if (($key['kid'] ?? '') !== $header['kid'] || ($key['kty'] ?? '') !== 'RSA' || ($key['alg'] ?? '') !== 'RS256') continue;
        if (!empty($key['x5c'][0])) {
            $certificate = "-----BEGIN CERTIFICATE-----\n" . chunk_split($key['x5c'][0], 64, "\n") . "-----END CERTIFICATE-----\n";
        } else {
            $certificate = googleRsaJwkToPem($key);
        }
        if ($certificate !== false) {
            break;
        }
    }
    if ($certificate === null) throw new RuntimeException('Google signing key not found');
    $publicKey = openssl_pkey_get_public($certificate);
    if ($publicKey === false || openssl_verify($parts[0] . '.' . $parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
        throw new RuntimeException('Google ID token signature invalid');
    }

    $issuer = (string)($claims['iss'] ?? '');
    $audience = $claims['aud'] ?? '';
    $audienceValid = is_string($audience) ? hash_equals($expectedClientId, $audience) : (is_array($audience) && in_array($expectedClientId, $audience, true) && (count($audience) === 1 || hash_equals($expectedClientId, (string)($claims['azp'] ?? ''))));
    if (!in_array($issuer, ['https://accounts.google.com', 'accounts.google.com'], true)
        || !$audienceValid
        || (int)($claims['exp'] ?? 0) <= time()
        || (int)($claims['iat'] ?? 0) > time() + 60
        || !hash_equals($expectedNonce, (string)($claims['nonce'] ?? ''))
        || empty($claims['sub'])
        || empty($claims['email'])
        || !in_array($claims['email_verified'] ?? false, [true, 'true', 1, '1'], true)) {
        throw new RuntimeException('Google ID token claims invalid');
    }
    return $claims;
}

if ($clientId === '' || $clientSecret === '' || $redirectUri === '') googleRedirectError('not_configured');

if ($action === 'start') {
    $state = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(32));
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $_SESSION['google_oauth'] = ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 'started_at' => time()];
    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'nonce' => $nonce,
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256'
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    exit;
}

if ($action !== 'callback') googleRedirectError('failed');
$flow = $_SESSION['google_oauth'] ?? null;
$returnedState = (string)($_GET['state'] ?? '');
if (!is_array($flow) || (time() - (int)($flow['started_at'] ?? 0)) > 600 || !hash_equals((string)($flow['state'] ?? ''), $returnedState)) {
    audit_log($pdo, null, 'google_login_state_failed', 'google_login.php');
    googleRedirectError('state');
}
if (isset($_GET['error'])) googleRedirectError('cancelled');
$code = (string)($_GET['code'] ?? '');
if ($code === '') googleRedirectError('failed');

try {
    $tokenResponse = googleRequest('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
        'code_verifier' => (string)$flow['verifier']
    ], ['Content-Type: application/x-www-form-urlencoded']);
    if (empty($tokenResponse['id_token'])) throw new RuntimeException('Missing Google ID token');
    $claims = verifyGoogleIdToken((string)$tokenResponse['id_token'], $clientId, (string)$flow['nonce']);
    $subject = (string)$claims['sub'];
    $email = trim((string)$claims['email']);

    $byGoogle = $pdo->prepare("SELECT u.user_id,u.username,u.full_name,u.email,u.role_id,u.department_id,d.department_name,u.status,r.role_code,r.role_name FROM users u INNER JOIN roles r ON r.role_id=u.role_id LEFT JOIN departments d ON d.department_id=u.department_id WHERE u.google_sub=? LIMIT 2");
    $byGoogle->execute([$subject]);
    $matches = $byGoogle->fetchAll();
    if (count($matches) > 1) googleRedirectError('email_ambiguous');
    if ($matches) {
        $user = $matches[0];
    } else {
        $byEmail = $pdo->prepare("SELECT u.user_id,u.username,u.full_name,u.email,u.role_id,u.department_id,d.department_name,u.status,u.google_sub,r.role_code,r.role_name FROM users u INNER JOIN roles r ON r.role_id=u.role_id LEFT JOIN departments d ON d.department_id=u.department_id WHERE LOWER(u.email)=LOWER(?) LIMIT 2");
        $byEmail->execute([$email]);
        $matches = $byEmail->fetchAll();
        if (!$matches) googleRedirectError('email_unlinked');
        if (count($matches) !== 1) googleRedirectError('email_ambiguous');
        $user = $matches[0];
        if (!empty($user['google_sub']) && !hash_equals((string)$user['google_sub'], $subject)) googleRedirectError('google_mismatch');
        if (empty($user['google_sub'])) {
            $link = $pdo->prepare('UPDATE users SET google_sub=? WHERE user_id=? AND google_sub IS NULL');
            $link->execute([$subject, $user['user_id']]);
            if ($link->rowCount() !== 1) googleRedirectError('google_mismatch');
        }
    }

    if ($user['status'] !== 'active') googleRedirectError('account_inactive');
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'user_id' => (int)$user['user_id'], 'username' => $user['username'],
        'full_name' => $user['full_name'], 'email' => $user['email'],
        'role_id' => (int)$user['role_id'], 'role_code' => $user['role_code'],
        'role_name' => $user['role_name'], 'department_id' => $user['department_id'],
        'department_name' => $user['department_name']
    ];
    $pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE user_id=?')->execute([$user['user_id']]);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['google_oauth']);
    audit_log($pdo, $_SESSION['user'], 'google_login_success', 'google_login.php');
    header('Location: dashboard.php');
    exit;
} catch (Throwable $exception) {
    error_log('Google sign-in failed: ' . $exception->getMessage());
    audit_log($pdo, null, 'google_login_failed', 'google_login.php');
    googleRedirectError('failed');
}

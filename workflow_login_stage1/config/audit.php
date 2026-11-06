<?php
declare(strict_types=1);

/** Store an access or administrative event without recording request bodies or credentials. */
function audit_log(PDO $pdo, ?array $user, string $event, string $page, array $details = []): void
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = null;
        }
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
        $json = $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, username, event_type, page_path, ip_address, user_agent, details) VALUES (:user_id, :username, :event_type, :page_path, :ip_address, :user_agent, :details)'
        );
        $stmt->execute([
            'user_id' => isset($user['user_id']) ? (int)$user['user_id'] : null,
            'username' => isset($user['username']) ? substr((string)$user['username'], 0, 100) : null,
            'event_type' => substr($event, 0, 40),
            'page_path' => substr(basename(parse_url($page, PHP_URL_PATH) ?: $page), 0, 191),
            'ip_address' => $ip,
            'user_agent' => $userAgent ?: null,
            'details' => $json,
        ]);
    } catch (Throwable $e) {
        // Auditing must not break the page; keep the failure in the server log.
        error_log('Audit log write failed: ' . $e->getMessage());
    }
}

<?php
// Simple healthcheck: read DATABASE_URL from env and run SELECT 1 using PDO
$databaseUrl = getenv('DATABASE_URL') ?: (getenv('DATABASE_URL') ?: null);
if (!$databaseUrl) {
    // Try common fallback from compose variables
    $host = getenv('POSTGRES_HOST') ?: 'postgres';
    $port = getenv('POSTGRES_PORT') ?: 5432;
    $user = getenv('POSTGRES_USER') ?: 'postgres';
    $pass = getenv('POSTGRES_PASSWORD') ?: '';
    $db = getenv('POSTGRES_DB') ?: 'postgres';
    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
    $user = $user;
    $pass = $pass;
} else {
    // DATABASE_URL format: postgres://user:pass@host:port/db
    $parts = parse_url($databaseUrl);
    if ($parts === false) {
        exit(1);
    }
    $host = $parts['host'] ?? 'postgres';
    $port = $parts['port'] ?? 5432;
    $user = $parts['user'] ?? 'postgres';
    $pass = $parts['pass'] ?? '';
    $db = ltrim($parts['path'] ?? '', '/');
    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
}
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
    $stmt = $pdo->query('SELECT 1');
    $res = $stmt->fetchColumn();
    if ($res === false) {
        exit(1);
    }
    echo "OK\n";
    exit(0);
} catch (Throwable $e) {
    // Don't leak credentials in logs
    error_log('healthcheck failed: '.$e->getMessage());
    exit(1);
}

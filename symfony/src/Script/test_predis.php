<?php
require __DIR__ . '/../../vendor/autoload.php';

try {
    $host = getenv('REDIS_HOST') ?: 'redis';
    $port = getenv('REDIS_PORT') ?: '6379';
    $dsn = 'redis://' . $host . ':' . $port;
    $c = new Predis\Client($dsn);
    // Use rawCommand to ensure correct argument ordering for XADD
    // Try using phpredis (Redis extension) if available
    if (class_exists('Redis')) {
        $r = new \Redis();
        $r->connect($host, (int)$port);
        // xAdd signature: xAdd(key, id, array, maxlen)
        $id = $r->xAdd('api-metrics', '*', ['from' => 'php_redis', 'time' => date('c')], 1000);
        echo "phpredis xadd id: $id\n";
    } else {
        // Fallback: attempt Predis xadd with flattened argument via __call
        $fields = ['from', 'predis_test', 'time', date('c')];
        $args = array_merge(['XADD', 'api-metrics', 'MAXLEN', '~', '1000', '*'], $fields);
        try {
            // Some Predis versions expose "executeCommand" helpers; try __call
            $result = $c->__call('rawCommand', $args);
            echo "predis raw result: " . json_encode($result) . "\n";
        } catch (\Throwable $e) {
            echo "Predis raw attempt failed: " . $e->getMessage() . "\n";
        }
    }
} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

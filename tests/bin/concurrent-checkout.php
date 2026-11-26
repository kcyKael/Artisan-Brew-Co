<?php

$options = getopt('', ['db:', 'staff-id:', 'barrier:', 'payload:']);
$dbName = $options['db'] ?? 'artisan_brew_co_test';
$staffId = (int) ($options['staff-id'] ?? 0);
$barrierFile = $options['barrier'] ?? '';
$payloadJson = $options['payload'] ?? '{}';

putenv("APP_ENV=testing");
putenv("DB_CONNECTION=mariadb");
putenv("DB_DATABASE={$dbName}");
putenv("DB_COLLATION=utf8mb4_unicode_ci");
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'mariadb';
$_ENV['DB_DATABASE'] = $dbName;
$_ENV['DB_COLLATION'] = 'utf8mb4_unicode_ci';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['DB_CONNECTION'] = 'mariadb';
$_SERVER['DB_DATABASE'] = $dbName;
$_SERVER['DB_COLLATION'] = 'utf8mb4_unicode_ci';

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if ($barrierFile !== '') {
    $deadline = microtime(true) + 10.0;
    while (! file_exists($barrierFile)) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, "Barrier timeout waiting for {$barrierFile}\n");
            exit(2);
        }
        usleep(5000);
    }
}

try {
    $staff = \App\Models\User::query()->findOrFail($staffId);
    $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

    $service = $app->make(\App\Services\CheckoutService::class);
    $result = $service->complete($staff, $payload);

    echo json_encode([
        'ok' => true,
        'status' => 200,
        'order_id' => $result->order->id,
        'order_number' => $result->order->order_number,
        'was_replay' => $result->wasReplay,
        'message' => 'OK',
    ], JSON_THROW_ON_ERROR);
    exit(0);
} catch (\App\Exceptions\CheckoutException $e) {
    echo json_encode([
        'ok' => false,
        'status' => $e->statusCode,
        'order_id' => null,
        'was_replay' => false,
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR);
    exit(0);
} catch (\Throwable $e) {
    echo json_encode([
        'ok' => false,
        'status' => 500,
        'order_id' => null,
        'was_replay' => false,
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR);
    exit(1);
}

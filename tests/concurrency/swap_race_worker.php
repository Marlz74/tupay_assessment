<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$payloadJson = $argv[1] ?? '';
$payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

$body = json_encode($payload['body'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

$request = Request::create(
    uri: '/api/swap',
    method: 'POST',
    parameters: [],
    cookies: [],
    files: [],
    server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$payload['token'],
        'HTTP_X_ELEVATED_ACTION_TOKEN' => $payload['eat'],
    ],
    content: $body,
);

$response = $kernel->handle($request);
$status = $response->getStatusCode();
$kernel->terminate($request, $response);

if ($status >= 500) {
    fwrite(STDERR, $response->getContent()."\n");
}

fwrite(STDOUT, (string) $status);
exit(0);

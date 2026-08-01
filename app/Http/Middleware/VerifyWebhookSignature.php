<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('tupay.webhook_secret', '');
        $signature = (string) $request->header('X-Signature', '');

        if ($secret === '' || $signature === '') {
            Log::warning('Webhook signature verification failed: missing secret or signature.', [
                'secret' => $secret === '' ? 'missing' : 'present',
                'signature' => $signature === '' ? 'missing' : 'present',
            ]);
            throw new HttpException(401, 'Invalid webhook signature.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('Webhook signature verification failed: signature mismatch.', [
                'expected' => $expected,
                'actual' => $signature,
            ]);
            throw new HttpException(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}

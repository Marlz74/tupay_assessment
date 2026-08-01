<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiResponse
{
    /** @param  array<string, mixed>  $meta */
    public static function success(
        string $message = 'Request successful',
        mixed $data = null,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        if ($data instanceof JsonResource) {
            $data = $data->resolve();
        }

        $payload = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /** @param  array<string, mixed>  $errors */
    public static function error(
        string $message = 'Something went wrong',
        int $status = 400,
        array $errors = [],
    ): JsonResponse {
        $payload = [
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ];

        return response()->json($payload, $status);
    }

    public static function fromException(Throwable $e, int $status = 500): JsonResponse
    {
        $message = App::environment(['local', 'testing'])
            ? $e->getMessage()
            : 'An unexpected error occurred';

        if ($status >= 500) {
            Log::error($e->getMessage(), ['exception' => $e]);
        }

        return self::error($message, $status);
    }

    public static function paginated(
        string $message,
        mixed $paginator,
    ): JsonResponse {
        return self::success(
            data: $paginator->items(),
            message: $message,
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}

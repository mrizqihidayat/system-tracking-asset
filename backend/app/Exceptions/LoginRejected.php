<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Login ditolak: pesan singkat untuk pengguna + status HTTP (401/403/503). */
class LoginRejected extends RuntimeException
{
    public function __construct(string $message, public readonly int $status, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}

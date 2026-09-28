<?php

namespace App\Domain\Tickets;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class TicketWorkflowException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 409)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->status);
    }
}

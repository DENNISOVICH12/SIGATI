<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiExceptionResponseTest extends TestCase
{
    public function test_production_api_response_sanitizes_unhandled_exceptions(): void
    {
        config(['app.debug' => false]);

        Route::get('/api/_test/internal-error', static function (): never {
            throw new RuntimeException('Detalle interno que no debe exponerse.');
        });

        $this->getJson('/api/_test/internal-error')
            ->assertInternalServerError()
            ->assertJsonPath('message', 'Server Error')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonMissingPath('trace')
            ->assertDontSee('Detalle interno que no debe exponerse.');
    }

    public function test_unauthenticated_api_request_uses_401_without_internal_details(): void
    {
        config(['app.debug' => false]);

        $this->getJson('/api/tickets')
            ->assertUnauthorized()
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonMissingPath('trace');
    }
}

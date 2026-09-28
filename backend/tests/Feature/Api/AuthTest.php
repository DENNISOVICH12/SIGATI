<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_use_me(): void
    {
        $user = $this->user();

        $login = $this->postJson('/api/login', ['email' => strtoupper($user->email), 'password' => 'Secret123!'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token', 'token_type', 'user' => ['roles', 'permissions']]);

        $this->withToken($login->json('token'))->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_bad_credentials_and_inactive_accounts_share_the_same_rejection(): void
    {
        $active = $this->user();
        $inactive = $this->user(['email' => 'inactive@example.test', 'active' => false]);

        $wrong = $this->postJson('/api/login', ['email' => $active->email, 'password' => 'wrong'])
            ->assertUnprocessable()
            ->json('errors.email.0');
        $inactiveResponse = $this->postJson('/api/login', ['email' => $inactive->email, 'password' => 'Secret123!'])
            ->assertUnprocessable()
            ->assertJsonMissing(['token'])
            ->json('errors.email.0');

        $this->assertSame($wrong, $inactiveResponse);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_token_is_unauthenticated(): void
    {
        $this->withToken('invalid-token')->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = $this->user();
        $token = $user->createToken('browser')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_new_login_revokes_previous_tokens(): void
    {
        $user = $this->user();
        $oldToken = $user->createToken('old')->plainTextToken;

        $newToken = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Secret123!'])
            ->assertOk()->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->withToken($oldToken)->getJson('/api/me')->assertUnauthorized();
        $this->withToken($newToken)->getJson('/api/me')->assertOk();
    }

    public function test_inactive_user_with_a_remaining_token_is_blocked_and_token_is_revoked(): void
    {
        $user = $this->user();
        $token = $user->createToken('existing')->plainTextToken;
        $user->forceFill(['active' => false])->save();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_is_limited_by_normalized_email_and_ip(): void
    {
        $user = $this->user();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->postJson('/api/login', ['email' => strtoupper($user->email), 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertTooManyRequests();
    }

    public function test_authorized_deactivation_revokes_tokens_and_reactivation_does_not_create_one(): void
    {
        Permission::findOrCreate('users.deactivate');
        $engineer = $this->user(['email' => 'engineer@example.test']);
        $engineer->givePermissionTo('users.deactivate');
        $target = $this->user(['email' => 'target@example.test']);
        $target->createToken('existing');

        $this->actingAs($engineer)->patchJson("/api/users/{$target->id}/active", ['active' => false])
            ->assertOk()->assertJsonPath('user.active', false);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->actingAs($engineer)->patchJson("/api/users/{$target->id}/active", ['active' => true])
            ->assertOk()->assertJsonPath('user.active', true);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/login', ['email' => $target->email, 'password' => 'Secret123!'])->assertOk();
    }

    public function test_user_cannot_deactivate_self(): void
    {
        Permission::findOrCreate('users.deactivate');
        $user = $this->user();
        $user->givePermissionTo('users.deactivate');

        $this->actingAs($user)->patchJson("/api/users/{$user->id}/active", ['active' => false])
            ->assertUnprocessable();
        $this->assertTrue($user->fresh()->active);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'active@example.test',
            'password' => Hash::make('Secret123!'),
            'active' => true,
        ], $attributes));
    }
}

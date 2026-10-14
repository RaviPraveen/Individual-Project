<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AiLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($this->admin);
    }

    private function data(User $user, array $changes = []): array
    {
        return array_merge([
            'name' => $user->name, 'email' => $user->email,
            'role' => $user->role, 'is_active' => $user->is_active ? 1 : 0,
        ], $changes);
    }

    private function assertLog(string $action, User $user): void
    {
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->admin->id, 'action' => $action,
            'subject_type' => User::class, 'subject_id' => $user->id,
        ]);
    }

    public function test_creating_staff_records_the_admin_and_the_new_account(): void
    {
        $this->post(route('admin.users.store'), [
            'name' => 'New Cashier', 'email' => 'cashier@example.com', 'role' => 'cashier',
            'password' => 'CashierPass123', 'password_confirmation' => 'CashierPass123',
        ])->assertRedirect(route('admin.users.index'));
        $user = User::where('email', 'cashier@example.com')->firstOrFail();
        $this->assertLog('user.created', $user);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->get(route('admin.activity-log.index'))->assertOk()->assertSee('New Cashier')->assertSee('user.created');
    }

    public function test_editing_staff_details_and_role_records_the_changed_fields(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->put(route('admin.users.update', $user), $this->data($user, [
            'name' => 'Updated Name', 'email' => 'updated@example.com', 'role' => 'admin',
        ]))->assertRedirect();
        $this->assertLog('user.updated', $user);
        $this->assertStringContainsString('name, email, role', ActivityLog::first()->description);
    }

    public function test_unchecking_active_deactivates_staff_and_records_both_state_changes(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->get(route('admin.users.edit', $user))->assertOk()->assertSee('type="hidden" name="is_active" value="0"', false);
        $this->put(route('admin.users.update', $user), $this->data($user, ['is_active' => 0]))->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
        $this->assertLog('user.deactivated', $user);
        $this->put(route('admin.users.update', $user), $this->data($user->fresh(), ['is_active' => 1]))->assertRedirect();
        $this->assertTrue($user->fresh()->is_active);
        $this->assertLog('user.activated', $user);
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_password_changes_are_logged_without_storing_passwords_in_the_log(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->put(route('admin.users.update', $user), $this->data($user, [
            'password' => 'EditedPass123', 'password_confirmation' => 'EditedPass123',
        ]))->assertRedirect();
        $editedHash = $user->fresh()->password;
        $this->post(route('admin.users.reset-password', $user), [
            'temp_password' => 'Temporary123', 'temp_password_confirmation' => 'Temporary123',
        ])->assertRedirect();
        $this->assertTrue($user->fresh()->force_password_reset);
        $this->assertTrue(Hash::check('Temporary123', $user->fresh()->password));
        $this->assertLog('user.password_reset', $user);
        $this->assertDatabaseCount('activity_logs', 2);
        $logs = ActivityLog::all()->toJson();
        foreach (['EditedPass123', 'Temporary123', $editedHash, $user->fresh()->password] as $secret) {
            $this->assertStringNotContainsString($secret, $logs);
        }
    }

    public function test_deleted_staff_are_identifiable_in_the_log(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'name' => 'Unused Cashier']);
        $this->delete(route('admin.users.destroy', $user))->assertRedirect();
        $this->assertModelMissing($user);
        $this->assertLog('user.deleted', $user);
        $this->get(route('admin.activity-log.index'))->assertOk()->assertSee('Unused Cashier')->assertSee('user.deleted');
    }

    public function test_deleting_staff_with_history_logs_deactivation_instead(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        AiLog::create(['user_id' => $user->id, 'query' => 'Test', 'response' => 'Test answer']);
        $this->delete(route('admin.users.destroy', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
        $this->assertLog('user.deactivated', $user);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'user.deleted']);
    }

    public function test_unchanged_update_does_not_create_an_activity(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->put(route('admin.users.update', $user), $this->data($user))->assertRedirect();
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_rejected_changes_do_not_create_success_logs(): void
    {
        $this->put(route('admin.users.update', $this->admin), $this->data($this->admin, ['is_active' => 0]))
            ->assertRedirect()->assertSessionHas('error');
        $this->put(route('admin.users.update', $this->admin), $this->data($this->admin, ['role' => 'cashier']))
            ->assertRedirect()->assertSessionHas('error');
        $this->delete(route('admin.users.destroy', $this->admin))->assertRedirect()->assertSessionHas('error');
        $this->post(route('admin.users.store'), [
            'name' => 'Invalid', 'email' => 'invalid@example.com', 'role' => 'cashier',
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_cashier_cannot_make_staff_changes_or_create_logs(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($cashier);
        $this->post(route('admin.users.store'), [])->assertForbidden();
        $this->put(route('admin.users.update', $target), $this->data($target))->assertForbidden();
        $this->delete(route('admin.users.destroy', $target))->assertForbidden();
        $this->post(route('admin.users.reset-password', $target), [])->assertForbidden();
        $this->assertModelExists($target);
        $this->assertDatabaseCount('activity_logs', 0);
    }
}

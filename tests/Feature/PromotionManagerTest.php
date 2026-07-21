<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionManagerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Rice 5kg', 'sku' => 'SKU-PROMO-'.uniqid(),
            'cost_price' => 1000, 'selling_price' => 1500, 'stock_qty' => 50, 'reorder_level' => 5,
        ], $overrides));
    }

    public function test_cashier_is_forbidden_from_promotion_manager(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->product();

        $this->actingAs($cashier)->get(route('admin.promotions.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.promotions.create'))->assertForbidden();
        $this->actingAs($cashier)->post(route('admin.promotions.store'), [
            'title' => 'x', 'product_id' => $product->id, 'offer_price' => 1000,
            'start_date' => now(), 'end_date' => now()->addDay(), 'display_duration' => 10,
            'priority' => 'normal', 'target_screen' => 'customer_display',
        ])->assertForbidden();
    }

    public function test_admin_can_create_a_promotion_with_auto_calculated_discount(): void
    {
        $admin = $this->admin();
        $product = $this->product(['selling_price' => 1500]);

        $response = $this->actingAs($admin)->post(route('admin.promotions.store'), [
            'title' => 'Weekend Rice Sale',
            'product_id' => $product->id,
            'offer_price' => 1200,
            'start_date' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'display_duration' => 15,
            'priority' => 'high',
            'target_screen' => 'customer_display',
            'is_featured' => '1',
        ]);

        $response->assertRedirect(route('admin.promotions.index'));

        $promotion = Promotion::firstOrFail();
        $this->assertSame('Weekend Rice Sale', $promotion->title);
        $this->assertEqualsWithDelta(1500.0, (float) $promotion->current_price, 0.01);
        $this->assertEqualsWithDelta(20.0, (float) $promotion->discount_percentage, 0.01);
        $this->assertTrue($promotion->is_featured);
        // Start date is in the future, so it should land as scheduled, not active.
        $this->assertSame(Promotion::STATUS_SCHEDULED, $promotion->status);
    }

    public function test_offer_price_cannot_exceed_current_price(): void
    {
        $admin = $this->admin();
        $product = $this->product(['selling_price' => 1000]);

        $response = $this->actingAs($admin)->post(route('admin.promotions.store'), [
            'title' => 'Bad Offer',
            'product_id' => $product->id,
            'offer_price' => 1200,
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'display_duration' => 10,
            'priority' => 'normal',
            'target_screen' => 'customer_display',
        ]);

        $response->assertSessionHasErrors('offer_price');
        $this->assertSame(0, Promotion::count());
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $admin = $this->admin();
        $product = $this->product();

        $response = $this->actingAs($admin)->post(route('admin.promotions.store'), [
            'title' => 'Backwards Dates',
            'product_id' => $product->id,
            'offer_price' => 1000,
            'start_date' => now()->addDay(),
            'end_date' => now(),
            'display_duration' => 10,
            'priority' => 'normal',
            'target_screen' => 'customer_display',
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_a_promotion_currently_in_its_window_is_created_as_active(): void
    {
        $admin = $this->admin();
        $product = $this->product();

        $this->actingAs($admin)->post(route('admin.promotions.store'), [
            'title' => 'Live Now',
            'product_id' => $product->id,
            'offer_price' => 1000,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDay(),
            'display_duration' => 10,
            'priority' => 'normal',
            'target_screen' => 'customer_display',
        ]);

        $this->assertSame(Promotion::STATUS_ACTIVE, Promotion::firstOrFail()->status);
    }

    public function test_toggle_status_pauses_an_active_promotion_and_reactivating_respects_the_schedule(): void
    {
        $admin = $this->admin();
        $product = $this->product();
        $promotion = Promotion::create([
            'title' => 'Toggle Me', 'product_id' => $product->id,
            'current_price' => 1500, 'offer_price' => 1000, 'discount_percentage' => 33.3,
            'start_date' => now()->subHour(), 'end_date' => now()->addDay(),
            'display_duration' => 10, 'priority' => 'normal', 'status' => Promotion::STATUS_ACTIVE,
            'target_screen' => 'customer_display', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.promotions.toggle-status', $promotion))->assertRedirect();
        $this->assertSame(Promotion::STATUS_PAUSED, $promotion->fresh()->status);

        $this->actingAs($admin)->post(route('admin.promotions.toggle-status', $promotion))->assertRedirect();
        $this->assertSame(Promotion::STATUS_ACTIVE, $promotion->fresh()->status);
    }

    public function test_toggling_on_an_expired_promotion_is_rejected_with_a_message(): void
    {
        $admin = $this->admin();
        $product = $this->product();
        $promotion = Promotion::create([
            'title' => 'Long Gone', 'product_id' => $product->id,
            'current_price' => 1500, 'offer_price' => 1000, 'discount_percentage' => 33.3,
            'start_date' => now()->subDays(10), 'end_date' => now()->subDay(),
            'display_duration' => 10, 'priority' => 'normal', 'status' => Promotion::STATUS_PAUSED,
            'target_screen' => 'customer_display', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.promotions.toggle-status', $promotion));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(Promotion::STATUS_PAUSED, $promotion->fresh()->status);
    }

    public function test_sync_due_statuses_flips_scheduled_to_active_and_active_to_expired(): void
    {
        $admin = $this->admin();
        $product = $this->product();

        $dueToStart = Promotion::create([
            'title' => 'Due To Start', 'product_id' => $product->id,
            'current_price' => 1500, 'offer_price' => 1000, 'discount_percentage' => 33.3,
            'start_date' => now()->subMinute(), 'end_date' => now()->addDay(),
            'display_duration' => 10, 'priority' => 'normal', 'status' => Promotion::STATUS_SCHEDULED,
            'target_screen' => 'customer_display', 'created_by' => $admin->id,
        ]);

        $dueToExpire = Promotion::create([
            'title' => 'Due To Expire', 'product_id' => $product->id,
            'current_price' => 1500, 'offer_price' => 1000, 'discount_percentage' => 33.3,
            'start_date' => now()->subDays(2), 'end_date' => now()->subMinute(),
            'display_duration' => 10, 'priority' => 'normal', 'status' => Promotion::STATUS_ACTIVE,
            'target_screen' => 'customer_display', 'created_by' => $admin->id,
        ]);

        Promotion::syncDueStatuses();

        $this->assertSame(Promotion::STATUS_ACTIVE, $dueToStart->fresh()->status);
        $this->assertSame(Promotion::STATUS_EXPIRED, $dueToExpire->fresh()->status);
    }

    public function test_admin_can_delete_a_promotion(): void
    {
        $admin = $this->admin();
        $product = $this->product();
        $promotion = Promotion::create([
            'title' => 'Delete Me', 'product_id' => $product->id,
            'current_price' => 1500, 'offer_price' => 1000, 'discount_percentage' => 33.3,
            'start_date' => now(), 'end_date' => now()->addDay(),
            'display_duration' => 10, 'priority' => 'normal', 'status' => Promotion::STATUS_ACTIVE,
            'target_screen' => 'customer_display', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->delete(route('admin.promotions.destroy', $promotion))->assertRedirect();
        $this->assertSame(0, Promotion::count());
    }
}

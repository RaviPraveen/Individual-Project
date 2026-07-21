<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDisplayPromotionsTest extends TestCase
{
    use RefreshDatabase;

    private function cashier(): User
    {
        return User::factory()->create(['role' => 'cashier', 'is_active' => true]);
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Rice 5kg', 'sku' => 'SKU-CD-'.uniqid(),
            'cost_price' => 1000, 'selling_price' => 1500, 'stock_qty' => 50, 'reorder_level' => 5,
        ]);
    }

    private function makePromotion(array $overrides): Promotion
    {
        return Promotion::create(array_merge([
            'title' => 'Test Promo', 'product_id' => $this->product()->id,
            'current_price' => 1500, 'offer_price' => 1200, 'discount_percentage' => 20,
            'display_duration' => 10, 'priority' => 'normal', 'target_screen' => 'customer_display',
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ], $overrides));
    }

    public function test_admin_is_forbidden_from_the_cashier_only_display_feed(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('cashier.display.promotions'))->assertForbidden();
    }

    public function test_only_currently_active_customer_display_promotions_are_returned(): void
    {
        $cashier = $this->cashier();

        $active = $this->makePromotion([
            'title' => 'Active One', 'status' => Promotion::STATUS_ACTIVE,
            'start_date' => now()->subHour(), 'end_date' => now()->addDay(),
        ]);
        $this->makePromotion([
            'title' => 'Scheduled One', 'status' => Promotion::STATUS_SCHEDULED,
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(2),
        ]);
        $this->makePromotion([
            'title' => 'Paused One', 'status' => Promotion::STATUS_PAUSED,
            'start_date' => now()->subHour(), 'end_date' => now()->addDay(),
        ]);
        $this->makePromotion([
            'title' => 'Expired One', 'status' => Promotion::STATUS_EXPIRED,
            'start_date' => now()->subDays(5), 'end_date' => now()->subDay(),
        ]);
        $this->makePromotion([
            'title' => 'Dashboard Only', 'status' => Promotion::STATUS_ACTIVE,
            'start_date' => now()->subHour(), 'end_date' => now()->addDay(),
            'target_screen' => 'dashboard_banner',
        ]);

        $response = $this->actingAs($cashier)->getJson(route('cashier.display.promotions'));

        $response->assertOk();
        $titles = collect($response->json('promotions'))->pluck('title');
        $this->assertEqualsCanonicalizing(['Active One'], $titles->all());
    }

    public function test_a_scheduled_promotion_whose_start_date_has_arrived_appears_via_auto_sync(): void
    {
        $cashier = $this->cashier();

        $this->makePromotion([
            'title' => 'Now Due', 'status' => Promotion::STATUS_SCHEDULED,
            'start_date' => now()->subMinute(), 'end_date' => now()->addDay(),
        ]);

        $response = $this->actingAs($cashier)->getJson(route('cashier.display.promotions'));

        $titles = collect($response->json('promotions'))->pluck('title');
        $this->assertContains('Now Due', $titles->all());
    }

    public function test_both_target_screen_promotions_appear_on_the_customer_display_feed(): void
    {
        $cashier = $this->cashier();

        $this->makePromotion([
            'title' => 'Both Screens', 'status' => Promotion::STATUS_ACTIVE,
            'start_date' => now()->subHour(), 'end_date' => now()->addDay(),
            'target_screen' => 'both',
        ]);

        $response = $this->actingAs($cashier)->getJson(route('cashier.display.promotions'));

        $titles = collect($response->json('promotions'))->pluck('title');
        $this->assertContains('Both Screens', $titles->all());
    }

    public function test_marking_a_promotion_viewed_increments_its_display_count(): void
    {
        $cashier = $this->cashier();
        $promotion = $this->makePromotion([
            'status' => Promotion::STATUS_ACTIVE,
            'start_date' => now()->subHour(), 'end_date' => now()->addDay(),
        ]);

        $this->actingAs($cashier)->postJson(route('cashier.display.promotions.viewed', $promotion))->assertOk();
        $this->actingAs($cashier)->postJson(route('cashier.display.promotions.viewed', $promotion))->assertOk();

        $this->assertSame(2, $promotion->fresh()->display_count);
    }
}

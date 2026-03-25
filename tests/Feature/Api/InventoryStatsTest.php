<?php

namespace Tests\Feature\Api;

use App\Models\ApiClient;
use App\Models\Owner;
use App\Models\Screen;
use App\Models\ScreenInventory;
use App\Models\ScreenSpec;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryStatsTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $client = ApiClient::create([
            'client_id'     => 'test_stats',
            'client_secret' => bcrypt('secret'),
            'name'          => 'Test Stats',
            'scopes'        => ['inventory'],
            'active'        => true,
        ]);

        $this->token = $client->createToken('test', ['inventory'])->plainTextToken;
    }

    private function getStats(): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson('/api/v1/inventory/stats');
    }

    private function makeActiveScreen(string $city = 'Hà Nội', string $venueType = 'mall'): Screen
    {
        $owner  = Owner::factory()->create(['status' => 'active']);
        $site   = Site::factory()->create(['owner_id' => $owner->id, 'city' => $city]);
        $screen = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => true]);
        ScreenSpec::factory()->create(['screen_id' => $screen->id]);
        ScreenInventory::factory()->create(['screen_id' => $screen->id, 'venue_type' => $venueType]);
        return $screen;
    }

    // ── Response structure ────────────────────────────────────

    public function test_stats_returns_correct_structure(): void
    {
        $this->makeActiveScreen();

        $response = $this->getStats();

        $response->assertOk()
            ->assertJsonStructure([
                'total_screens',
                'total_cities',
                'total_owners',
                'venues' => [['type', 'label', 'count']],
                'cities' => [['code', 'name', 'count']],
            ]);
    }

    // ── total_screens ─────────────────────────────────────────

    public function test_total_screens_counts_only_active(): void
    {
        $this->makeActiveScreen();
        $this->makeActiveScreen();

        // Inactive screen — không đếm
        $owner  = Owner::factory()->create(['status' => 'active']);
        $site   = Site::factory()->create(['owner_id' => $owner->id]);
        $screen = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => false]);
        ScreenSpec::factory()->create(['screen_id' => $screen->id]);
        ScreenInventory::factory()->create(['screen_id' => $screen->id]);

        Cache::forget('inventory_stats');
        $response = $this->getStats();

        $response->assertOk();
        $this->assertEquals(2, $response->json('total_screens'));
    }

    // ── total_cities ──────────────────────────────────────────

    public function test_total_cities_counts_distinct_cities(): void
    {
        $this->makeActiveScreen('Hà Nội');
        $this->makeActiveScreen('Hà Nội');   // cùng city — không tăng count
        $this->makeActiveScreen('Đà Nẵng');

        Cache::forget('inventory_stats');
        $response = $this->getStats();

        $response->assertOk();
        $this->assertEquals(2, $response->json('total_cities'));
    }

    // ── venues ───────────────────────────────────────────────

    public function test_venues_groups_by_venue_type(): void
    {
        $this->makeActiveScreen('Hà Nội', 'mall');
        $this->makeActiveScreen('Hà Nội', 'mall');
        $this->makeActiveScreen('Hà Nội', 'outdoor');

        Cache::forget('inventory_stats');
        $response = $this->getStats();

        $response->assertOk();
        $venues = collect($response->json('venues'));

        $mall    = $venues->firstWhere('type', 'mall');
        $outdoor = $venues->firstWhere('type', 'outdoor');

        $this->assertEquals(2, $mall['count']);
        $this->assertEquals(1, $outdoor['count']);
    }

    public function test_venues_has_correct_labels(): void
    {
        // Seed taxonomy records so the LEFT JOIN resolves labels
        DB::table('venue_types')->insert([
            ['string_value' => 'mall', 'venue_type' => 'Retail',  'category' => 'Retail',          'depth' => 0, 'hivestack_supported' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['string_value' => 'fnb',  'venue_type' => 'F&B',     'category' => 'Food & Beverage',  'depth' => 0, 'hivestack_supported' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->makeActiveScreen('Hà Nội', 'mall');
        $this->makeActiveScreen('Hà Nội', 'fnb');

        Cache::forget('inventory_stats');
        $response = $this->getStats();

        $response->assertOk();
        $venues = collect($response->json('venues'));

        $this->assertEquals('Retail', $venues->firstWhere('type', 'mall')['label']);
        $this->assertEquals('F&B',    $venues->firstWhere('type', 'fnb')['label']);
    }

    public function test_venues_fallback_label_when_not_in_taxonomy(): void
    {
        $this->makeActiveScreen('Hà Nội', 'custom_type');

        Cache::forget('inventory_stats');
        $response = $this->getStats();

        $response->assertOk();
        $venues = collect($response->json('venues'));
        // Fallback: ucfirst + replace _ and . with space
        $this->assertEquals('Custom type', $venues->firstWhere('type', 'custom_type')['label']);
    }

    // ── cities ────────────────────────────────────────────────

    public function test_cities_returns_exactly_5_fixed_cities(): void
    {
        Cache::forget('inventory_stats');
        $response = $this->getStats();

        $response->assertOk();
        $codes = collect($response->json('cities'))->pluck('code');
        $this->assertEqualsCanonicalizing(
            ['hanoi', 'hcm', 'danang', 'haiphong', 'cantho'],
            $codes->toArray()
        );
    }

    public function test_cities_counts_screens_by_vietnamese_city_name(): void
    {
        $this->makeActiveScreen('Hà Nội');
        $this->makeActiveScreen('Hà Nội');
        $this->makeActiveScreen('Đà Nẵng');

        Cache::forget('inventory_stats');
        $response = $this->getStats();

        $response->assertOk();
        $cities = collect($response->json('cities'));

        $this->assertEquals(2, $cities->firstWhere('code', 'hanoi')['count']);
        $this->assertEquals(1, $cities->firstWhere('code', 'danang')['count']);
        $this->assertEquals(0, $cities->firstWhere('code', 'hcm')['count']);
    }

    // ── Caching ───────────────────────────────────────────────

    public function test_stats_is_cached(): void
    {
        $this->makeActiveScreen();
        Cache::forget('inventory_stats');

        // Request đầu — cache miss, set cache
        $this->getStats()->assertOk();
        $this->assertTrue(Cache::has('inventory_stats'));

        // Thêm screen mới nhưng không clear cache — total vẫn là 1
        $this->makeActiveScreen();
        $response = $this->getStats();

        $this->assertEquals(1, $response->json('total_screens'));
    }

    // ── Auth ──────────────────────────────────────────────────

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/inventory/stats')->assertUnauthorized();
    }
}

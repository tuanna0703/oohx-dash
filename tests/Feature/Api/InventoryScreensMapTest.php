<?php

namespace Tests\Feature\Api;

use App\Models\ApiClient;
use App\Models\Owner;
use App\Models\Screen;
use App\Models\ScreenInventory;
use App\Models\ScreenSpec;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryScreensMapTest extends TestCase
{
    use RefreshDatabase;

    private ApiClient $client;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = ApiClient::create([
            'client_id'     => 'test_oohx_map',
            'client_secret' => bcrypt('secret'),
            'name'          => 'Test OOHX Map',
            'scopes'        => ['inventory'],
            'active'        => true,
        ]);

        $this->token = $this->client->createToken('test', ['inventory'])->plainTextToken;
    }

    private function getMap(array $params = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)
            ->getJson('/api/v1/inventory/screens/map?' . http_build_query($params));
    }

    private function makeScreen(array $siteOverrides = [], array $specOverrides = [], array $invOverrides = []): Screen
    {
        $owner  = Owner::factory()->create();
        $site   = Site::factory()->create(array_merge(['owner_id' => $owner->id], $siteOverrides));
        $screen = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id]);

        ScreenSpec::factory()->create(array_merge(['screen_id' => $screen->id], $specOverrides));
        ScreenInventory::factory()->create(array_merge(['screen_id' => $screen->id], $invOverrides));

        return $screen;
    }

    // ── Response structure ────────────────────────────────

    public function test_map_returns_correct_structure(): void
    {
        $this->makeScreen();

        $response = $this->getMap();

        $response->assertOk()
            ->assertJsonStructure([
                'total',
                'data' => [[
                    'screen_id',
                    'name',
                    'venue_type',
                    'screen_type',
                    'size',
                    'location' => ['address', 'lat', 'lng'],
                ]],
            ]);
    }

    public function test_map_has_no_page_or_limit_in_response(): void
    {
        $this->makeScreen();

        $response = $this->getMap();

        $response->assertOk();
        $this->assertArrayNotHasKey('page', $response->json());
        $this->assertArrayNotHasKey('limit', $response->json());
    }

    public function test_map_returns_all_screens_without_pagination(): void
    {
        // Tạo 5 screens
        for ($i = 0; $i < 5; $i++) {
            $this->makeScreen();
        }

        $response = $this->getMap();

        $response->assertOk();
        $this->assertEquals(5, $response->json('total'));
        $this->assertCount(5, $response->json('data'));
    }

    public function test_map_item_has_only_lightweight_fields(): void
    {
        $this->makeScreen();

        $response = $this->getMap();

        $response->assertOk();
        $item = $response->json('data.0');

        // Chỉ có 6 fields cần thiết
        $expectedKeys = ['screen_id', 'name', 'venue_type', 'screen_type', 'size', 'location'];
        $this->assertEqualsCanonicalizing($expectedKeys, array_keys($item));

        // Không có fields thừa từ full ScreenResource
        $this->assertArrayNotHasKey('specs', $item);
        $this->assertArrayNotHasKey('operating_hours', $item);
        $this->assertArrayNotHasKey('price_per_slot_vnd', $item);
        $this->assertArrayNotHasKey('photos', $item);
        $this->assertArrayNotHasKey('owner_id', $item);
    }

    // ── Route conflict ────────────────────────────────────

    public function test_map_route_is_not_matched_by_screen_id_route(): void
    {
        // Nếu route /map bị match bởi /{screen_id}, sẽ trả 404 "screen_not_found"
        // Endpoint /map phải trả 200 với data structure đúng
        $this->makeScreen();

        $response = $this->getMap();

        $response->assertOk();
        $this->assertArrayHasKey('total', $response->json());
        $this->assertArrayHasKey('data', $response->json());
    }

    // ── Filters hoạt động trên /map ───────────────────────

    public function test_map_filters_by_city_array(): void
    {
        $this->makeScreen(['city' => 'hanoi']);
        $this->makeScreen(['city' => 'danang']);
        $this->makeScreen(['city' => 'hcm']);

        $response = $this->getMap(['city' => ['hanoi', 'danang']]);

        $response->assertOk();
        $this->assertEquals(2, $response->json('total'));
    }

    public function test_map_filters_by_venue_type(): void
    {
        $this->makeScreen([], [], ['venue_type' => 'mall']);
        $this->makeScreen([], [], ['venue_type' => 'outdoor']);

        $response = $this->getMap(['venue_type' => 'mall']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_map_filters_by_status(): void
    {
        $owner = Owner::factory()->create();
        $site  = Site::factory()->create(['owner_id' => $owner->id]);

        $active   = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => true]);
        $inactive = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => false]);
        ScreenSpec::factory()->create(['screen_id' => $active->id]);
        ScreenSpec::factory()->create(['screen_id' => $inactive->id]);
        ScreenInventory::factory()->create(['screen_id' => $active->id]);
        ScreenInventory::factory()->create(['screen_id' => $inactive->id]);

        $response = $this->getMap(['status' => 'active']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_map_filters_by_orientation(): void
    {
        $this->makeScreen([], ['width_px' => 1920, 'height_px' => 1080]); // landscape
        $this->makeScreen([], ['width_px' => 1080, 'height_px' => 1920]); // portrait

        $response = $this->getMap(['orientation' => 'landscape']);

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_map_size_field_format(): void
    {
        $this->makeScreen([], ['width_cm' => 1400, 'height_cm' => 600]);

        $response = $this->getMap();

        $response->assertOk();
        $this->assertEquals('14×6m', $response->json('data.0.size'));
    }

    public function test_map_size_null_when_no_spec_dimensions(): void
    {
        $owner  = Owner::factory()->create();
        $site   = Site::factory()->create(['owner_id' => $owner->id]);
        $screen = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id]);
        ScreenSpec::factory()->create(['screen_id' => $screen->id, 'width_cm' => null, 'height_cm' => null]);
        ScreenInventory::factory()->create(['screen_id' => $screen->id]);

        $response = $this->getMap();

        $response->assertOk();
        $this->assertNull($response->json('data.0.size'));
    }

    // ── Auth ──────────────────────────────────────────────

    public function test_map_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/inventory/screens/map')->assertUnauthorized();
    }
}

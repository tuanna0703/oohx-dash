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

class InventoryVenueTypesTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $client = ApiClient::create([
            'client_id'     => 'test_venue_types',
            'client_secret' => bcrypt('secret'),
            'name'          => 'Test Venue Types',
            'scopes'        => ['inventory'],
            'active'        => true,
        ]);

        $this->token = $client->createToken('test', ['inventory'])->plainTextToken;
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Seed một cây taxonomy tối giản:
     *   outdoor (depth=0, enumeration_id=3)
     *     └── outdoor.billboards (depth=1, enumeration_id=301)
     *           └── outdoor.billboards.roadside (depth=2, enumeration_id=30101)
     *
     * Trả về ['outdoor_id', 'billboards_id', 'roadside_id']
     */
    private function seedOutdoorTaxonomy(): array
    {
        $outdoorId = DB::table('venue_types')->insertGetId([
            'enumeration_id'   => 3,
            'string_value'     => 'outdoor',
            'category'         => 'Outdoor',
            'subcategory'      => '',
            'venue_type'       => 'Outdoor',
            'depth'            => 0,
            'parent_id'        => null,
            'hivestack_supported' => true,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $billboardsId = DB::table('venue_types')->insertGetId([
            'enumeration_id'   => 301,
            'string_value'     => 'outdoor.billboards',
            'category'         => 'Outdoor',
            'subcategory'      => 'Billboards',
            'venue_type'       => 'Outdoor : Billboards',
            'depth'            => 1,
            'parent_id'        => $outdoorId,
            'hivestack_supported' => true,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $roadsideId = DB::table('venue_types')->insertGetId([
            'enumeration_id'   => 30101,
            'string_value'     => 'outdoor.billboards.roadside',
            'category'         => 'Outdoor',
            'subcategory'      => 'Billboards',
            'venue_type'       => 'Outdoor : Billboards : Roadside',
            'depth'            => 2,
            'parent_id'        => $billboardsId,
            'hivestack_supported' => true,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return [$outdoorId, $billboardsId, $roadsideId];
    }

    private function makeActiveScreen(string $venueType): Screen
    {
        $owner  = Owner::factory()->create(['status' => 'active']);
        $site   = Site::factory()->create(['owner_id' => $owner->id]);
        $screen = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => true]);
        ScreenSpec::factory()->create(['screen_id' => $screen->id]);
        ScreenInventory::factory()->create(['screen_id' => $screen->id, 'venue_type' => $venueType]);
        return $screen;
    }

    private function getVenueTypes(): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson('/api/v1/inventory/venue-types');
    }

    // ── Structure ─────────────────────────────────────────────

    public function test_returns_correct_structure(): void
    {
        $this->seedOutdoorTaxonomy();
        $this->makeActiveScreen('outdoor.billboards.roadside');
        Cache::forget('inventory_venue_types');

        $response = $this->getVenueTypes();

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['type', 'label', 'count', 'children']],
            ]);
    }

    public function test_returns_empty_when_no_active_screens(): void
    {
        Cache::forget('inventory_venue_types');

        $response = $this->getVenueTypes();

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    // ── Filtering ─────────────────────────────────────────────

    public function test_only_includes_types_with_active_screens(): void
    {
        $this->seedOutdoorTaxonomy();

        // Chỉ roadside có màn hình — billboards không có direct
        $this->makeActiveScreen('outdoor.billboards.roadside');

        // Thêm một taxonomy node không có màn hình
        DB::table('venue_types')->insert([
            'enumeration_id' => 2, 'string_value' => 'retail', 'category' => 'Retail',
            'subcategory' => '', 'venue_type' => 'Retail', 'depth' => 0,
            'hivestack_supported' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Cache::forget('inventory_venue_types');
        $response = $this->getVenueTypes();

        $response->assertOk();
        $types = collect($response->json('data'))->pluck('type');

        // outdoor xuất hiện (có màn hình qua roll-up), retail không xuất hiện
        $this->assertContains('outdoor', $types->toArray());
        $this->assertNotContains('retail', $types->toArray());
    }

    public function test_inactive_screens_are_excluded(): void
    {
        $this->seedOutdoorTaxonomy();

        // inactive screen
        $owner  = Owner::factory()->create(['status' => 'active']);
        $site   = Site::factory()->create(['owner_id' => $owner->id]);
        $screen = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => false]);
        ScreenSpec::factory()->create(['screen_id' => $screen->id]);
        ScreenInventory::factory()->create(['screen_id' => $screen->id, 'venue_type' => 'outdoor.billboards.roadside']);

        Cache::forget('inventory_venue_types');
        $response = $this->getVenueTypes();

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    // ── Hierarchy & roll-up ───────────────────────────────────

    public function test_parent_count_is_rolled_up_from_children(): void
    {
        $this->seedOutdoorTaxonomy();
        $this->makeActiveScreen('outdoor.billboards.roadside');
        $this->makeActiveScreen('outdoor.billboards.roadside');

        Cache::forget('inventory_venue_types');
        $response = $this->getVenueTypes();

        $response->assertOk();
        $data     = collect($response->json('data'));
        $outdoor  = $data->firstWhere('type', 'outdoor');

        // outdoor root phải có count = 2 (rolled up từ cháu)
        $this->assertEquals(2, $outdoor['count']);
    }

    public function test_children_are_nested_correctly(): void
    {
        $this->seedOutdoorTaxonomy();
        $this->makeActiveScreen('outdoor.billboards.roadside');

        Cache::forget('inventory_venue_types');
        $response = $this->getVenueTypes();

        $response->assertOk();
        $data = collect($response->json('data'));

        $outdoor    = $data->firstWhere('type', 'outdoor');
        $this->assertNotEmpty($outdoor['children']);

        $billboards = collect($outdoor['children'])->firstWhere('type', 'outdoor.billboards');
        $this->assertNotNull($billboards);
        $this->assertEquals(1, $billboards['count']);

        $roadside = collect($billboards['children'])->firstWhere('type', 'outdoor.billboards.roadside');
        $this->assertNotNull($roadside);
        $this->assertEquals(1, $roadside['count']);
    }

    public function test_leaf_node_has_empty_children(): void
    {
        $this->seedOutdoorTaxonomy();
        $this->makeActiveScreen('outdoor.billboards.roadside');

        Cache::forget('inventory_venue_types');
        $response = $this->getVenueTypes();

        $response->assertOk();
        $outdoor    = collect($response->json('data'))->firstWhere('type', 'outdoor');
        $billboards = collect($outdoor['children'])->firstWhere('type', 'outdoor.billboards');
        $roadside   = collect($billboards['children'])->firstWhere('type', 'outdoor.billboards.roadside');

        $this->assertEmpty($roadside['children']);
    }

    public function test_rollup_aggregates_multiple_leaf_types(): void
    {
        [$outdoorId, $billboardsId, $roadsideId] = $this->seedOutdoorTaxonomy();

        // Thêm leaf thứ 2: outdoor.billboards.highway
        DB::table('venue_types')->insert([
            'enumeration_id' => 30102, 'string_value' => 'outdoor.billboards.highway',
            'category' => 'Outdoor', 'subcategory' => 'Billboards',
            'venue_type' => 'Outdoor : Billboards : Highway',
            'depth' => 2, 'parent_id' => $billboardsId,
            'hivestack_supported' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->makeActiveScreen('outdoor.billboards.roadside');  // 2 roadside
        $this->makeActiveScreen('outdoor.billboards.roadside');
        $this->makeActiveScreen('outdoor.billboards.highway');   // 1 highway

        Cache::forget('inventory_venue_types');
        $response = $this->getVenueTypes();

        $response->assertOk();
        $data       = collect($response->json('data'));
        $outdoor    = $data->firstWhere('type', 'outdoor');
        $billboards = collect($outdoor['children'])->firstWhere('type', 'outdoor.billboards');

        $this->assertEquals(3, $outdoor['count']);     // 2 + 1
        $this->assertEquals(3, $billboards['count']);  // 2 + 1
    }

    // ── Orphan types ──────────────────────────────────────────

    public function test_orphan_type_not_in_taxonomy_is_returned_flat(): void
    {
        // Không seed taxonomy — "mall" là orphan
        $this->makeActiveScreen('mall');

        Cache::forget('inventory_venue_types');
        $response = $this->getVenueTypes();

        $response->assertOk();
        $data = collect($response->json('data'));
        $mall = $data->firstWhere('type', 'mall');

        $this->assertNotNull($mall);
        $this->assertEquals(1, $mall['count']);
        $this->assertEmpty($mall['children']);
    }

    public function test_orphan_fallback_label_uses_ucfirst(): void
    {
        $this->makeActiveScreen('custom_venue');

        Cache::forget('inventory_venue_types');
        $response = $this->getVenueTypes();

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('type', 'custom_venue');
        $this->assertEquals('Custom venue', $item['label']);
    }

    // ── Caching ───────────────────────────────────────────────

    public function test_result_is_cached(): void
    {
        $this->makeActiveScreen('outdoor');
        Cache::forget('inventory_venue_types');

        $this->getVenueTypes()->assertOk();
        $this->assertTrue(Cache::has('inventory_venue_types'));

        // Thêm màn hình mới nhưng không clear cache — count vẫn là 1
        $this->makeActiveScreen('outdoor');
        $response = $this->getVenueTypes();

        $data = collect($response->json('data'));
        $this->assertEquals(1, $data->firstWhere('type', 'outdoor')['count']);
    }

    // ── Auth ──────────────────────────────────────────────────

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/inventory/venue-types')->assertUnauthorized();
    }
}

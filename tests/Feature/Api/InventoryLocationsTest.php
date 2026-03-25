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
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class InventoryLocationsTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $client = ApiClient::create([
            'client_id'     => 'test_locations',
            'client_secret' => bcrypt('secret'),
            'name'          => 'Test Locations',
            'scopes'        => ['inventory'],
            'active'        => true,
        ]);
        $this->token = $client->createToken('test', ['inventory'])->plainTextToken;

        // Seed minimal region config
        Config::set('regions', [
            'north'   => ['name' => 'Miền Bắc',   'provinces' => ['hanoi', 'haiphong']],
            'central' => ['name' => 'Miền Trung',  'provinces' => ['danang']],
            'south'   => ['name' => 'Miền Nam',    'provinces' => ['hcm', 'cantho']],
        ]);
    }

    private function makeActiveScreen(string $city, ?string $districtCode = null, ?string $districtName = null): Screen
    {
        $owner  = Owner::factory()->create(['status' => 'active']);
        $site   = Site::factory()->create(['owner_id' => $owner->id, 'city' => $city]);
        $screen = Screen::factory()->create([
            'owner_id'               => $owner->id,
            'site_id'                => $site->id,
            'active'                 => true,
            'location_district_code' => $districtCode,
            'location_district'      => $districtName,
        ]);
        ScreenSpec::factory()->create(['screen_id' => $screen->id]);
        ScreenInventory::factory()->create(['screen_id' => $screen->id]);
        return $screen;
    }

    private function getLocations(): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson('/api/v1/inventory/locations');
    }

    // ── Structure ─────────────────────────────────────────────

    public function test_returns_correct_structure(): void
    {
        $this->makeActiveScreen('Hà Nội');
        Cache::forget('inventory:locations');

        $response = $this->getLocations();

        $response->assertOk()
            ->assertJsonStructure(['regions', 'provinces', 'districts']);
    }

    public function test_returns_empty_arrays_when_no_screens(): void
    {
        Cache::forget('inventory:locations');
        $response = $this->getLocations();

        $response->assertOk();
        $this->assertEmpty($response->json('regions'));
        $this->assertEmpty($response->json('provinces'));
        $this->assertEmpty($response->json('districts'));
    }

    // ── Provinces ─────────────────────────────────────────────

    public function test_provinces_only_includes_cities_with_active_screens(): void
    {
        $this->makeActiveScreen('Hà Nội');

        // inactive screen in Đà Nẵng
        $owner  = Owner::factory()->create(['status' => 'active']);
        $site   = Site::factory()->create(['owner_id' => $owner->id, 'city' => 'Đà Nẵng']);
        Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => false]);

        Cache::forget('inventory:locations');
        $response = $this->getLocations();

        $response->assertOk();
        $codes = collect($response->json('provinces'))->pluck('code');
        $this->assertContains('hanoi', $codes->toArray());
        $this->assertNotContains('danang', $codes->toArray());
    }

    public function test_province_count_is_correct(): void
    {
        $this->makeActiveScreen('Hà Nội');
        $this->makeActiveScreen('Hà Nội');

        Cache::forget('inventory:locations');
        $response = $this->getLocations();

        $province = collect($response->json('provinces'))->firstWhere('code', 'hanoi');
        $this->assertEquals(2, $province['count']);
    }

    public function test_province_has_correct_region(): void
    {
        $this->makeActiveScreen('Hà Nội');
        $this->makeActiveScreen('Đà Nẵng');

        Cache::forget('inventory:locations');
        $response = $this->getLocations();

        $provinces = collect($response->json('provinces'));
        $this->assertEquals('north',   $provinces->firstWhere('code', 'hanoi')['region']);
        $this->assertEquals('central', $provinces->firstWhere('code', 'danang')['region']);
    }

    // ── Regions ───────────────────────────────────────────────

    public function test_region_count_is_rolled_up_from_provinces(): void
    {
        $this->makeActiveScreen('Hà Nội');
        $this->makeActiveScreen('Hà Nội');
        $this->makeActiveScreen('Hồ Chí Minh');  // south

        Cache::forget('inventory:locations');
        $response = $this->getLocations();

        $regions = collect($response->json('regions'));
        $this->assertEquals(2, $regions->firstWhere('code', 'north')['count']);
        $this->assertEquals(1, $regions->firstWhere('code', 'south')['count']);
    }

    public function test_region_not_returned_when_no_provinces_have_screens(): void
    {
        $this->makeActiveScreen('Hà Nội'); // only north

        Cache::forget('inventory:locations');
        $response = $this->getLocations();

        $regionCodes = collect($response->json('regions'))->pluck('code');
        $this->assertContains('north', $regionCodes->toArray());
        $this->assertNotContains('central', $regionCodes->toArray());
        $this->assertNotContains('south', $regionCodes->toArray());
    }

    // ── Districts ─────────────────────────────────────────────

    public function test_districts_empty_when_no_district_code_set(): void
    {
        $this->makeActiveScreen('Hà Nội'); // no district code

        Cache::forget('inventory:locations');
        $response = $this->getLocations();

        $this->assertEmpty($response->json('districts'));
    }

    public function test_districts_includes_district_with_code(): void
    {
        $this->makeActiveScreen('Hà Nội', 'cau-giay', 'Cầu Giấy');

        Cache::forget('inventory:locations');
        $response = $this->getLocations();

        $district = collect($response->json('districts'))->firstWhere('code', 'cau-giay');
        $this->assertNotNull($district);
        $this->assertEquals('Cầu Giấy', $district['name']);
        $this->assertEquals('hanoi',    $district['province']);
        $this->assertEquals(1,          $district['count']);
    }

    public function test_district_count_aggregates_correctly(): void
    {
        $this->makeActiveScreen('Hà Nội', 'cau-giay', 'Cầu Giấy');
        $this->makeActiveScreen('Hà Nội', 'cau-giay', 'Cầu Giấy');

        Cache::forget('inventory:locations');
        $response = $this->getLocations();

        $district = collect($response->json('districts'))->firstWhere('code', 'cau-giay');
        $this->assertEquals(2, $district['count']);
    }

    // ── Caching ───────────────────────────────────────────────

    public function test_result_is_cached(): void
    {
        $this->makeActiveScreen('Hà Nội');
        Cache::forget('inventory:locations');

        $this->getLocations()->assertOk();
        $this->assertTrue(Cache::has('inventory:locations'));
    }

    // ── Auth ──────────────────────────────────────────────────

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/inventory/locations')->assertUnauthorized();
    }
}

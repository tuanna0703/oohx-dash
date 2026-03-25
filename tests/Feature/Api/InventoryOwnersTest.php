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

class InventoryOwnersTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $client = ApiClient::create([
            'client_id'     => 'test_owners',
            'client_secret' => bcrypt('secret'),
            'name'          => 'Test Owners',
            'scopes'        => ['inventory'],
            'active'        => true,
        ]);

        $this->token = $client->createToken('test', ['inventory'])->plainTextToken;
    }

    private function makeOwnerWithScreens(array $ownerAttrs = [], int $screenCount = 1, string $city = 'Hà Nội', string $venueType = 'mall'): Owner
    {
        $owner = Owner::factory()->create(array_merge(['status' => 'active'], $ownerAttrs));

        for ($i = 0; $i < $screenCount; $i++) {
            $site   = Site::factory()->create(['owner_id' => $owner->id, 'city' => $city]);
            $screen = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => true]);
            ScreenSpec::factory()->create(['screen_id' => $screen->id]);
            ScreenInventory::factory()->create(['screen_id' => $screen->id, 'venue_type' => $venueType]);
        }

        return $owner;
    }

    // ── GET /inventory/owners ─────────────────────────────────

    public function test_owners_returns_correct_structure(): void
    {
        $this->makeOwnerWithScreens();

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners');

        $response->assertOk()
            ->assertJsonStructure([
                'total', 'page', 'limit',
                'data' => [[
                    'owner_id', 'name', 'tagline', 'logo_url', 'cover_url',
                    'screen_count', 'city_count', 'venue_types',
                    'website', 'email', 'phone', 'founded', 'featured', 'headquarters',
                ]],
            ]);
    }

    public function test_owner_id_is_slug(): void
    {
        $owner = $this->makeOwnerWithScreens(['slug' => 'my-media-owner']);

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners');

        $response->assertOk();
        $this->assertEquals('my-media-owner', $response->json('data.0.owner_id'));
    }

    public function test_only_active_owners_are_returned(): void
    {
        $this->makeOwnerWithScreens(['status' => 'active']);
        $this->makeOwnerWithScreens(['status' => 'suspended']);
        $this->makeOwnerWithScreens(['status' => 'pending']);

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_featured_filter_returns_only_featured_owners(): void
    {
        $this->makeOwnerWithScreens(['featured' => true]);
        $this->makeOwnerWithScreens(['featured' => true]);
        $this->makeOwnerWithScreens(['featured' => false]);

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners?featured=true');

        $response->assertOk();
        $this->assertEquals(2, $response->json('total'));
    }

    public function test_screen_count_is_correct(): void
    {
        $this->makeOwnerWithScreens([], 3);

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners');

        $response->assertOk();
        $this->assertEquals(3, $response->json('data.0.screen_count'));
    }

    public function test_screen_count_excludes_inactive_screens(): void
    {
        $owner = Owner::factory()->create(['status' => 'active']);
        $site  = Site::factory()->create(['owner_id' => $owner->id]);

        // 2 active, 1 inactive
        for ($i = 0; $i < 2; $i++) {
            $s = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => true]);
            ScreenSpec::factory()->create(['screen_id' => $s->id]);
            ScreenInventory::factory()->create(['screen_id' => $s->id]);
        }
        $s = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => false]);
        ScreenSpec::factory()->create(['screen_id' => $s->id]);
        ScreenInventory::factory()->create(['screen_id' => $s->id]);

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners');

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.0.screen_count'));
    }

    public function test_city_count_counts_distinct_cities(): void
    {
        $owner = Owner::factory()->create(['status' => 'active']);

        foreach (['Hà Nội', 'Hà Nội', 'Đà Nẵng'] as $city) {
            $site   = Site::factory()->create(['owner_id' => $owner->id, 'city' => $city]);
            $screen = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => true]);
            ScreenSpec::factory()->create(['screen_id' => $screen->id]);
            ScreenInventory::factory()->create(['screen_id' => $screen->id]);
        }

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners');

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.0.city_count'));
    }

    public function test_venue_types_returns_distinct_types(): void
    {
        $owner = Owner::factory()->create(['status' => 'active']);

        foreach (['mall', 'mall', 'outdoor'] as $vt) {
            $site   = Site::factory()->create(['owner_id' => $owner->id]);
            $screen = Screen::factory()->create(['owner_id' => $owner->id, 'site_id' => $site->id, 'active' => true]);
            ScreenSpec::factory()->create(['screen_id' => $screen->id]);
            ScreenInventory::factory()->create(['screen_id' => $screen->id, 'venue_type' => $vt]);
        }

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners');

        $response->assertOk();
        $venueTypes = $response->json('data.0.venue_types');
        $this->assertEqualsCanonicalizing(['mall', 'outdoor'], $venueTypes);
    }

    // ── GET /inventory/owners/{slug} ──────────────────────────

    public function test_owner_detail_returns_correct_structure(): void
    {
        $owner = $this->makeOwnerWithScreens(['slug' => 'test-owner', 'about' => 'Mô tả công ty']);

        $response = $this->withToken($this->token)->getJson("/api/v1/inventory/owners/test-owner");

        $response->assertOk()
            ->assertJsonStructure([
                'owner_id', 'name', 'about', 'tagline', 'logo_url',
                'screen_count', 'city_count', 'venue_types', 'featured',
            ]);
    }

    public function test_owner_detail_includes_about_field(): void
    {
        $this->makeOwnerWithScreens(['slug' => 'detail-owner', 'about' => 'Nội dung giới thiệu']);

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners/detail-owner');

        $response->assertOk();
        $this->assertEquals('Nội dung giới thiệu', $response->json('about'));
    }

    public function test_owner_detail_404_for_unknown_slug(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners/not-exist');

        $response->assertNotFound()
            ->assertJson(['error' => 'Owner not found']);
    }

    public function test_owner_detail_404_for_non_active_owner(): void
    {
        Owner::factory()->create(['slug' => 'suspended-owner', 'status' => 'suspended']);

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners/suspended-owner');

        $response->assertNotFound();
    }

    public function test_owner_detail_does_not_have_about_field_in_list(): void
    {
        $this->makeOwnerWithScreens(['about' => 'Some about text']);

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners');

        $response->assertOk();
        $this->assertArrayNotHasKey('about', $response->json('data.0'));
    }

    // ── Search q ──────────────────────────────────────────────

    public function test_q_filters_by_owner_name(): void
    {
        $this->makeOwnerWithScreens(['name' => 'Vingroup Media']);
        $this->makeOwnerWithScreens(['name' => 'AdTRUE Network']);

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners?q=Vingroup');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
        $this->assertStringContainsString('Vingroup', $response->json('data.0.name'));
    }

    public function test_q_returns_empty_when_no_match(): void
    {
        $this->makeOwnerWithScreens(['name' => 'Vingroup Media']);

        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners?q=XYZNotFound');

        $response->assertOk();
        $this->assertEquals(0, $response->json('total'));
    }

    public function test_q_validates_max_100_chars(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v1/inventory/owners?q=' . str_repeat('a', 101));

        $response->assertUnprocessable();
    }

    // ── Auth ──────────────────────────────────────────────────

    public function test_owners_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/inventory/owners')->assertUnauthorized();
    }

    public function test_owner_detail_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/inventory/owners/any-slug')->assertUnauthorized();
    }
}

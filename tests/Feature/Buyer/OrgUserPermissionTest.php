<?php

namespace Tests\Feature\Buyer;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrgUserPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $superAdmin;
    private User $orgAdmin;
    private User $planner;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'buyer', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['status' => 'active']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        $this->orgAdmin = $this->makeMember('admin');
        $this->planner  = $this->makeMember('planner');
        $this->viewer   = $this->makeMember('viewer');
    }

    private function makeMember(string $role): User
    {
        $user = User::factory()->create(['current_organization_id' => $this->org->id]);
        $user->assignRole('buyer');

        OrganizationUser::create([
            'organization_id' => $this->org->id,
            'user_id'         => $user->id,
            'role'            => $role,
        ]);

        return $user;
    }

    public function test_assignable_roles_excludes_admin_for_non_super_admin(): void
    {
        $rolesForAdmin = OrganizationUser::assignableRolesFor($this->orgAdmin);
        $this->assertArrayNotHasKey('admin', $rolesForAdmin);
        $this->assertArrayHasKey('planner', $rolesForAdmin);
        $this->assertCount(2, $rolesForAdmin);

        $rolesForSuperAdmin = OrganizationUser::assignableRolesFor($this->superAdmin);
        $this->assertArrayHasKey('admin', $rolesForSuperAdmin);
        $this->assertCount(3, $rolesForSuperAdmin);
    }

    public function test_org_admin_can_view_and_create(): void
    {
        $this->actingAs($this->orgAdmin);
        $this->assertTrue(Gate::allows('viewAny', OrganizationUser::class));
        $this->assertTrue(Gate::allows('create', OrganizationUser::class));
    }

    public function test_planner_cannot_view_or_create(): void
    {
        $this->actingAs($this->planner);
        $this->assertFalse(Gate::allows('viewAny', OrganizationUser::class));
        $this->assertFalse(Gate::allows('create', OrganizationUser::class));
    }

    public function test_viewer_cannot_view(): void
    {
        $this->actingAs($this->viewer);
        $this->assertFalse(Gate::allows('viewAny', OrganizationUser::class));
    }

    public function test_org_admin_cannot_update_record_with_role_admin(): void
    {
        $coAdmin = $this->makeMember('admin');
        $coAdminPivot = OrganizationUser::where('user_id', $coAdmin->id)->where('organization_id', $this->org->id)->firstOrFail();

        $this->actingAs($this->orgAdmin);
        $this->assertFalse(Gate::allows('update', $coAdminPivot));
        $this->assertFalse(Gate::allows('delete', $coAdminPivot));
    }

    public function test_org_admin_can_update_planner_record(): void
    {
        $plannerPivot = OrganizationUser::where('user_id', $this->planner->id)->where('organization_id', $this->org->id)->firstOrFail();

        $this->actingAs($this->orgAdmin);
        $this->assertTrue(Gate::allows('update', $plannerPivot));
        $this->assertTrue(Gate::allows('delete', $plannerPivot));
    }

    public function test_org_admin_cannot_update_self(): void
    {
        $selfPivot = OrganizationUser::where('user_id', $this->orgAdmin->id)->where('organization_id', $this->org->id)->firstOrFail();

        $this->actingAs($this->orgAdmin);
        $this->assertFalse(Gate::allows('update', $selfPivot));
    }

    public function test_super_admin_can_update_admin_record(): void
    {
        $coAdmin = $this->makeMember('admin');
        $coAdminPivot = OrganizationUser::where('user_id', $coAdmin->id)->where('organization_id', $this->org->id)->firstOrFail();

        $this->actingAs($this->superAdmin);
        $this->assertTrue(Gate::allows('update', $coAdminPivot));
    }
}

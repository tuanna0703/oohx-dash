<?php

namespace Tests\Feature\Publisher;

use App\Models\Owner;
use App\Models\OwnerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OwnerUserPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Owner $owner;
    private User $superAdmin;
    private User $tenantOwner;
    private User $manager;
    private User $readOnly;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'publisher', 'guard_name' => 'web']);

        $this->owner = Owner::factory()->create(['status' => 'active']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        $this->tenantOwner = $this->makeMember('owner');
        $this->manager     = $this->makeMember('manager');
        $this->readOnly    = $this->makeMember('read_only');
    }

    private function makeMember(string $role): User
    {
        $user = User::factory()->create(['current_owner_id' => $this->owner->id]);
        $user->assignRole('publisher');

        OwnerUser::create([
            'owner_id' => $this->owner->id,
            'user_id'  => $user->id,
            'role'     => $role,
        ]);

        return $user;
    }

    public function test_assignable_roles_excludes_owner_for_non_super_admin(): void
    {
        $rolesForOwner = OwnerUser::assignableRolesFor($this->tenantOwner);
        $this->assertArrayNotHasKey('owner', $rolesForOwner);
        $this->assertArrayHasKey('manager', $rolesForOwner);
        $this->assertArrayHasKey('read_only', $rolesForOwner);
        $this->assertCount(5, $rolesForOwner);

        $rolesForSuperAdmin = OwnerUser::assignableRolesFor($this->superAdmin);
        $this->assertArrayHasKey('owner', $rolesForSuperAdmin);
        $this->assertCount(6, $rolesForSuperAdmin);
    }

    public function test_super_admin_can_create_owner_user(): void
    {
        $this->actingAs($this->superAdmin);
        $this->assertTrue(Gate::allows('create', OwnerUser::class));
    }

    public function test_tenant_owner_can_create_owner_user(): void
    {
        $this->actingAs($this->tenantOwner);
        $this->assertTrue(Gate::allows('create', OwnerUser::class));
    }

    public function test_manager_cannot_create_owner_user(): void
    {
        $this->actingAs($this->manager);
        $this->assertFalse(Gate::allows('create', OwnerUser::class));
    }

    public function test_read_only_cannot_view_team_list(): void
    {
        $this->actingAs($this->readOnly);
        $this->assertFalse(Gate::allows('viewAny', OwnerUser::class));
    }

    public function test_manager_cannot_view_team_list(): void
    {
        $this->actingAs($this->manager);
        $this->assertFalse(Gate::allows('viewAny', OwnerUser::class));
    }

    public function test_tenant_owner_can_view_team_list(): void
    {
        $this->actingAs($this->tenantOwner);
        $this->assertTrue(Gate::allows('viewAny', OwnerUser::class));
    }

    public function test_tenant_owner_cannot_update_a_record_with_role_owner(): void
    {
        $coOwner = $this->makeMember('owner');
        $coOwnerPivot = OwnerUser::where('user_id', $coOwner->id)->where('owner_id', $this->owner->id)->firstOrFail();

        $this->actingAs($this->tenantOwner);
        $this->assertFalse(Gate::allows('update', $coOwnerPivot));
        $this->assertFalse(Gate::allows('delete', $coOwnerPivot));
    }

    public function test_tenant_owner_can_update_manager_record(): void
    {
        $managerPivot = OwnerUser::where('user_id', $this->manager->id)->where('owner_id', $this->owner->id)->firstOrFail();

        $this->actingAs($this->tenantOwner);
        $this->assertTrue(Gate::allows('update', $managerPivot));
        $this->assertTrue(Gate::allows('delete', $managerPivot));
    }

    public function test_tenant_owner_cannot_update_self(): void
    {
        $selfPivot = OwnerUser::where('user_id', $this->tenantOwner->id)->where('owner_id', $this->owner->id)->firstOrFail();

        $this->actingAs($this->tenantOwner);
        $this->assertFalse(Gate::allows('update', $selfPivot));
        $this->assertFalse(Gate::allows('delete', $selfPivot));
    }

    public function test_super_admin_can_update_record_with_role_owner(): void
    {
        $coOwner = $this->makeMember('owner');
        $coOwnerPivot = OwnerUser::where('user_id', $coOwner->id)->where('owner_id', $this->owner->id)->firstOrFail();

        $this->actingAs($this->superAdmin);
        $this->assertTrue(Gate::allows('update', $coOwnerPivot));
        $this->assertTrue(Gate::allows('delete', $coOwnerPivot));
    }
}

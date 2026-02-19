<?php

namespace Tests\Feature;

use App\Models\IptvSeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIptvSeedFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user = User::factory()->create(['is_admin' => false]);
    }

    public function test_admin_can_list_iptv_seeds(): void
    {
        IptvSeed::create([
            'name' => 'Test Seed 1',
            'url' => 'http://example.com/1.m3u',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        IptvSeed::create([
            'name' => 'Test Seed 2',
            'url' => 'http://example.com/2.m3u',
            'sort_order' => 5,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/iptv-seeds');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'Test Seed 2') // Sorted by sort_order
            ->assertJsonPath('1.name', 'Test Seed 1');
    }

    public function test_non_admin_cannot_list_iptv_seeds(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/admin/iptv-seeds');
        $response->assertStatus(403);
    }

    public function test_admin_can_create_iptv_seed(): void
    {
        $payload = [
            'name' => 'New Seed',
            'url' => 'http://example.com/new.m3u',
            'sort_order' => 1,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/admin/iptv-seeds', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Seed');

        $this->assertDatabaseHas('iptv_seeds', [
            'name' => 'New Seed',
            'url' => 'http://example.com/new.m3u',
        ]);
    }

    public function test_admin_can_update_iptv_seed(): void
    {
        $seed = IptvSeed::create([
            'name' => 'Old Name',
            'url' => 'http://example.com/old.m3u',
        ]);

        $payload = [
            'name' => 'Updated Name',
            'url' => 'http://example.com/updated.m3u',
            'sort_order' => 5,
            'is_active' => false,
        ];

        $response = $this->actingAs($this->admin)->patchJson("/api/admin/iptv-seeds/{$seed->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('iptv_seeds', [
            'id' => $seed->id,
            'name' => 'Updated Name',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_iptv_seed(): void
    {
        $seed = IptvSeed::create([
            'name' => 'To Delete',
            'url' => 'http://example.com/delete.m3u',
        ]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/admin/iptv-seeds/{$seed->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('iptv_seeds', ['id' => $seed->id]);
    }

    public function test_user_can_get_active_iptv_seeds(): void
    {
        IptvSeed::create([
            'name' => 'Active Seed',
            'url' => 'http://example.com/active.m3u',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        IptvSeed::create([
            'name' => 'Inactive Seed',
            'url' => 'http://example.com/inactive.m3u',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/iptv/seeds');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Seed');
    }
}

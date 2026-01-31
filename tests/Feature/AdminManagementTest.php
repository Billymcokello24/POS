<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_access_admin_users_and_settings()
    {
        $businessUser = User::factory()->create(['is_super_admin' => false]);
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        // Non-admin access
        $this->actingAs($businessUser)->get(route('admin.users.index'))->assertStatus(403);
        $this->actingAs($businessUser)->get(route('admin.settings.index'))->assertStatus(403);

        // Admin access
        $this->actingAs($superAdmin)->get(route('admin.users.index'))->assertStatus(200);
        $this->actingAs($superAdmin)->get(route('admin.settings.index'))->assertStatus(200);
    }

    public function test_super_admin_can_create_another_super_admin()
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@example.com',
            'is_super_admin' => true,
        ]);
    }

    public function test_super_admin_can_update_system_settings()
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $settingsData = [
            'mpesa' => [
                'consumer_key' => 'test_key',
                'consumer_secret' => 'test_secret',
                'shortcode' => '123456',
                'passkey' => 'test_passkey',
                'environment' => 'sandbox',
            ],
            'mail' => [
                'host' => 'smtp.test.com',
                'username' => 'testuser',
            ],
        ];

        $response = $this->actingAs($superAdmin)->put(route('admin.settings.update'), $settingsData);

        $response->assertStatus(302);
        
        // Verify in system_settings (for SMTP)
        $settingsPage = Page::where('key', 'system_settings')->first();
        $this->assertNotNull($settingsPage);
        $this->assertEquals('smtp.test.com', $settingsPage->content['mail']['host']);
        
        // Verify in welcome page (for MPESA)
        $cmsPage = Page::where('key', 'welcome')->first();
        $this->assertEquals('test_key', $cmsPage->content['mpesa']['consumer_key']);
        
        // Verify encryption in both
        $rawSettings = \DB::table('pages')->where('key', 'system_settings')->value('content');
        $rawCms = \DB::table('pages')->where('key', 'welcome')->value('content');
        
        $this->assertStringNotContainsString('test_secret', $rawCms);
        $this->assertStringNotContainsString('test_passkey', $rawCms);
    }

    public function test_admin_cannot_delete_themselves()
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($superAdmin)->delete(route('admin.users.destroy', $superAdmin->id));

        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Cannot delete this user.');
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }
}

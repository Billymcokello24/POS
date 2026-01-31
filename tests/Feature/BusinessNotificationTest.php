<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Notifications\BusinessSuspended;
use App\Notifications\BusinessRestored;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BusinessNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $superAdmin;
    protected $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->business = Business::factory()->create(['is_active' => true]);
        $this->admin = User::factory()->create(['current_business_id' => $this->business->id]);

        // Assign admin role to $this->admin for $this->business
        $adminRole = \Illuminate\Support\Facades\DB::table('roles')->where('name', 'admin')->first();
        if ($adminRole) {
            \Illuminate\Support\Facades\DB::table('role_user')->insert([
                'user_id' => $this->admin->id,
                'business_id' => $this->business->id,
                'role_id' => $adminRole->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_suspension_notification_is_sent()
    {
        Notification::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post("/admin/businesses/{$this->business->id}/toggle-status", [
                'reason' => 'Violation of terms'
            ]);

        $response->assertRedirect();
        $this->business->refresh();

        Notification::assertSentTo(
            $this->admin,
            BusinessSuspended::class,
            function ($notification, $channels) {
                return $notification->toArray($this->admin)['reason'] === 'Violation of terms';
            }
        );
    }

    public function test_restoration_notification_is_sent_with_credentials()
    {
        $this->business->update(['is_active' => false, 'suspension_reason' => 'Bad behavior']);
        
        Notification::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post("/admin/businesses/{$this->business->id}/toggle-status");

        $response->assertRedirect();
        $this->business->refresh();
        $this->assertTrue($this->business->is_active);

        Notification::assertSentTo(
            $this->admin,
            BusinessRestored::class,
            function ($notification, $channels) {
                $data = $notification->toMail($this->admin);
                return collect($data->introLines)->contains('Username: ' . $this->admin->email) &&
                       collect($data->introLines)->contains('Password: Password123!');
            }
        );
    }
}

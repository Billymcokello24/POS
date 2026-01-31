<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Notifications\GenericBulkEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BulkEmailTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $activeBusiness;
    protected $suspendedBusiness;
    protected $activeAdmin;
    protected $suspendedAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $adminRole = \Illuminate\Support\Facades\DB::table('roles')->where('name', 'admin')->first();

        $this->superAdmin = User::factory()->create(['is_super_admin' => true]);

        // Active business + admin
        $this->activeBusiness = Business::factory()->create(['is_active' => true, 'name' => 'Active Biz']);
        $this->activeAdmin = User::factory()->create(['current_business_id' => $this->activeBusiness->id, 'name' => 'Active Admin']);
        \Illuminate\Support\Facades\DB::table('role_user')->insert([
            'user_id' => $this->activeAdmin->id,
            'business_id' => $this->activeBusiness->id,
            'role_id' => $adminRole->id,
        ]);

        // Suspended business + admin
        $this->suspendedBusiness = Business::factory()->create(['is_active' => false, 'name' => 'Suspended Biz']);
        $this->suspendedAdmin = User::factory()->create(['current_business_id' => $this->suspendedBusiness->id, 'name' => 'Suspended Admin']);
        \Illuminate\Support\Facades\DB::table('role_user')->insert([
            'user_id' => $this->suspendedAdmin->id,
            'business_id' => $this->suspendedBusiness->id,
            'role_id' => $adminRole->id,
        ]);
    }

    public function test_send_to_all_recipients_with_placeholders()
    {
        Notification::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/bulk-email/send', [
                'subject' => 'Hello {business_name}',
                'content' => 'Dear {admin_name}, your business {business_name} is active.',
                'recipients' => 'all'
            ]);

        $response->assertRedirect('/admin/businesses');
        
        Notification::assertSentTo($this->activeAdmin, GenericBulkEmail::class, function ($notification, $channels) {
            $mail = $notification->toMail($this->activeAdmin);
            return $mail->subject === 'Hello Active Biz' &&
                   collect($mail->introLines)->contains('Dear Active Admin, your business Active Biz is active.');
        });

        Notification::assertSentTo($this->suspendedAdmin, GenericBulkEmail::class, function ($notification, $channels) {
            $mail = $notification->toMail($this->suspendedAdmin);
            return $mail->subject === 'Hello Suspended Biz' &&
                   collect($mail->introLines)->contains('Dear Suspended Admin, your business Suspended Biz is active.');
        });
    }

    public function test_send_to_active_recipients_only()
    {
        Notification::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/bulk-email/send', [
                'subject' => 'Hello Active',
                'content' => 'Only for active ones.',
                'recipients' => 'active'
            ]);

        $response->assertRedirect('/admin/businesses');
        
        Notification::assertSentTo($this->activeAdmin, GenericBulkEmail::class);
        Notification::assertNotSentTo($this->suspendedAdmin, GenericBulkEmail::class);
    }

    public function test_send_to_selected_recipients_only()
    {
        Notification::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/bulk-email/send', [
                'subject' => 'Selected Biz',
                'content' => 'You were chosen.',
                'recipients' => 'selected',
                'selected_ids' => [$this->suspendedBusiness->id]
            ]);

        $response->assertRedirect('/admin/businesses');
        
        Notification::assertNotSentTo($this->activeAdmin, GenericBulkEmail::class);
        Notification::assertSentTo($this->suspendedAdmin, GenericBulkEmail::class);
    }

    public function test_validation_errors()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/bulk-email/send', [
                'subject' => '',
                'content' => '',
                'recipients' => 'random'
            ]);

        $response->assertSessionHasErrors(['subject', 'content', 'recipients']);
    }
}

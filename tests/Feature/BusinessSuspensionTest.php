<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_business_user_cannot_login()
    {
        $business = Business::factory()->create(['is_active' => false]);
        $user = User::factory()->create([
            'current_business_id' => $business->id,
            'password' => bcrypthash('Password123!'),
            'is_super_admin' => false
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!'
        ]);

        $response->assertSessionHasErrors(['email' => 'ACCOUNT SUSPENDED, CONTACT THE SYSTEM ADMIN FOR ASSISTANCE']);
        $this->assertGuest();
    }

    public function test_active_business_user_can_login()
    {
        $business = Business::factory()->create(['is_active' => true]);
        $user = User::factory()->create([
            'current_business_id' => $business->id,
            'password' => bcrypthash('Password123!'),
            'is_super_admin' => false
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!'
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_super_admin_can_login_even_if_current_business_is_suspended()
    {
        $business = Business::factory()->create(['is_active' => false]);
        $user = User::factory()->create([
            'current_business_id' => $business->id,
            'password' => bcrypthash('Password123!'),
            'is_super_admin' => true
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!'
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($user);
    }
}

function bcrypthash($pass) {
    return \Illuminate\Support\Facades\Hash::make($pass);
}

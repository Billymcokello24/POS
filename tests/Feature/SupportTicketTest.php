<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Models\SupportTicket;
use App\Notifications\NewSupportTicket;
use App\Notifications\SupportVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_user_can_initiate_and_verify_support_chat()
    {
        Notification::fake();

        $business = Business::factory()->create();
        $user = User::factory()->create([
            'current_business_id' => $business->id,
            'is_super_admin' => false,
        ]);

        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        // 1. Initiate
        $response = $this->actingAs($user)->post(route('api.support.tickets'), [
            'subject' => 'Chat Support Request',
            'message' => 'Hello, I need help with my account.',
        ]);

        $response->assertStatus(200);
        $ticketId = $response->json('ticket_id');

        $ticket = SupportTicket::find($ticketId);
        $this->assertNotNull($ticket->verification_code);
        $this->assertFalse($ticket->is_verified);

        Notification::assertSentTo(
            new AnonymousNotifiable, 
            SupportVerificationCode::class,
            function ($notification, $channels, $notifiable) use ($business) {
                return $notifiable->routes['mail'] === $business->email;
            }
        );

        // 2. Verify
        $response = $this->actingAs($user)->post(route('api.support.tickets.verify', $ticket), [
            'code' => $ticket->verification_code,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($ticket->fresh()->is_verified);
        $this->assertDatabaseHas('support_messages', [
            'support_ticket_id' => $ticketId,
            'user_id' => $user->id,
            'message' => 'Hello, I need help with my account.',
        ]);

        Notification::assertSentTo([$superAdmin], NewSupportTicket::class);

        // 3. Send additional message
        $response = $this->actingAs($user)->post(route('api.support.tickets.send-message', $ticket), [
            'message' => 'Are you there?',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('support_messages', [
            'support_ticket_id' => $ticketId,
            'user_id' => $user->id,
            'message' => 'Are you there?',
        ]);
    }

    public function test_support_ticket_submission_requires_valid_data()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('api.support.tickets'), [
            'subject' => '',
            'message' => '',
        ]);

        $response->assertSessionHasErrors(['subject', 'message']);
    }
}

<?php

namespace App\Events;

use App\Models\MpesaPayment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MpesaPaymentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payment;

    public function __construct(MpesaPayment $payment)
    {
        $this->payment = $payment;
    }

    public function broadcastOn()
    {
        return new Channel('mpesa-payments.'.$this->payment->checkout_request_id);
    }

    public function broadcastAs()
    {
        return 'payment.updated';
    }
}

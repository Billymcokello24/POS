<?php

namespace App\Notifications;

use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessRegistered extends Notification
{
    use Queueable;

    protected $business;

    /**
     * Create a new notification instance.
     */
    public function __construct($business)
    {
        $this->business = $business;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Welcome to ModernPOS - Your Workspace is Ready! 🎉')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Congratulations! Your business, **' . $this->business->name . '**, has been successfully provisioned on the ModernPOS Enterprise Suite.')
            ->line('You now have full access to manage your inventory, process sales, and track your business growth in real-time.')
            ->line('**Next Steps:**')
            ->line('1. **Choose Your Subscription Plan** - Login and click the "Subscription" menu to select a plan that fits your business needs.')
            ->line('2. Review the attached subscription plans document to see all available features.')
            ->line('3. Complete your payment via M-PESA to activate your chosen plan.')
            ->action('Go to Dashboard & Choose Plan', url('/dashboard'))
            ->line('Thank you for choosing ModernPOS for your retail operations!');

        // Attach plans brochure PDF
        try {
            $pdfService = app(PdfService::class);
            $pdf = $pdfService->generatePlansBrochure();

            // Save to temporary storage
            $tempPath = storage_path("app/temp/ModernPOS_Subscription_Plans.pdf");

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            file_put_contents($tempPath, $pdf->output());

            // Attach the file
            $message->attach($tempPath, [
                'as' => "ModernPOS_Subscription_Plans.pdf",
                'mime' => 'application/pdf',
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to attach plans PDF: ' . $e->getMessage(), [
                'business_id' => $this->business->id,
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}

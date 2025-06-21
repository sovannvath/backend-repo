<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderRejected extends Notification
{
    use Queueable;

    protected $order;
    protected $rejectionReason;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order, $rejectionReason = null)
    {
        $this->order = $order;
        $this->rejectionReason = $rejectionReason;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
                    ->line('We regret to inform you that your order has been rejected.')
                    ->line('Order Number: ' . $this->order->order_number)
                    ->line('Total Amount: $' . number_format($this->order->total_amount, 2));

        if ($this->rejectionReason) {
            $mailMessage->line('Reason: ' . $this->rejectionReason);
        }

        return $mailMessage->action('View Order', url('/orders/' . $this->order->id))
                          ->line('Please contact our support team if you have any questions.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_rejected',
            'title' => 'Order Rejected',
            'message' => 'Your order ' . $this->order->order_number . ' has been rejected.' . 
                        ($this->rejectionReason ? ' Reason: ' . $this->rejectionReason : ''),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total_amount' => $this->order->total_amount,
            'rejection_reason' => $this->rejectionReason,
            'rejected_at' => $this->order->rejected_at,
        ];
    }
}


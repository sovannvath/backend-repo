<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderApproved extends Notification
{
    use Queueable;

    protected $order;
    protected $staffNotes;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order, $staffNotes = null)
    {
        $this->order = $order;
        $this->staffNotes = $staffNotes;
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
        return (new MailMessage)
                    ->line('Your order has been approved and is ready for delivery!')
                    ->line('Order Number: ' . $this->order->order_number)
                    ->line('Total Amount: $' . number_format($this->order->total_amount, 2))
                    ->action('View Order', url('/orders/' . $this->order->id))
                    ->line('Thank you for shopping with us!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_approved',
            'title' => 'Order Approved',
            'message' => 'Your order ' . $this->order->order_number . ' has been successfully approved and is ready to deliver to you.',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total_amount' => $this->order->total_amount,
            'staff_notes' => $this->staffNotes,
            'approved_at' => $this->order->approved_at,
        ];
    }
}


<?php

namespace App\Notifications;

use App\Models\RequestOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestOrderApprovalNotification extends Notification
{
    use Queueable;

    protected $requestOrder;

    /**
     * Create a new notification instance.
     */
    public function __construct(RequestOrder $requestOrder)
    {
        $this->requestOrder = $requestOrder;
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
                    ->subject('Request Order Approved by Admin')
                    ->line('A request order for product "' . $this->requestOrder->product->name . '" has been approved by admin.')
                    ->line('Quantity: ' . $this->requestOrder->quantity)
                    ->line('Admin Notes: ' . ($this->requestOrder->admin_notes ?? 'None'))
                    ->action('View Request Order', url('/request-orders/' . $this->requestOrder->id))
                    ->line('Please review and process this request.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'RequestOrderApproval',
            'request_order_id' => $this->requestOrder->id,
            'product_id' => $this->requestOrder->product_id,
            'product_name' => $this->requestOrder->product->name,
            'quantity' => $this->requestOrder->quantity,
            'admin_notes' => $this->requestOrder->admin_notes,
            'message' => 'A request order for product "' . $this->requestOrder->product->name . '" has been approved by admin and requires warehouse review.'
        ];
    }
}

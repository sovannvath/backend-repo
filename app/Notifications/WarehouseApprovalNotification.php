<?php

namespace App\Notifications;

use App\Models\RequestOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WarehouseApprovalNotification extends Notification
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
        $status = $this->requestOrder->warehouse_approval_status;
        $subject = 'Request Order ' . ($status === 'Approved' ? 'Approved' : 'Rejected') . ' by Warehouse';
        
        $message = (new MailMessage)
            ->subject($subject)
            ->line('A request order for product "' . $this->requestOrder->product->name . '" has been ' . strtolower($status) . ' by the warehouse manager.');
            
        if ($status === 'Approved') {
            $message->line('The product quantity has been updated.');
        }
        
        return $message
            ->line('Quantity: ' . $this->requestOrder->quantity)
            ->line('Warehouse Notes: ' . ($this->requestOrder->warehouse_notes ?? 'None'))
            ->action('View Request Order', url('/request-orders/' . $this->requestOrder->id));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $status = $this->requestOrder->warehouse_approval_status;
        
        return [
            'type' => 'WarehouseApproval',
            'request_order_id' => $this->requestOrder->id,
            'product_id' => $this->requestOrder->product_id,
            'product_name' => $this->requestOrder->product->name,
            'quantity' => $this->requestOrder->quantity,
            'warehouse_approval_status' => $status,
            'warehouse_notes' => $this->requestOrder->warehouse_notes,
            'message' => 'A request order for product "' . $this->requestOrder->product->name . '" has been ' . strtolower($status) . ' by the warehouse manager.'
        ];
    }
}

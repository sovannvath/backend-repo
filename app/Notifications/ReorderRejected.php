<?php

namespace App\Notifications;

use App\Models\ReorderRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReorderRejected extends Notification
{
    use Queueable;

    protected $reorderRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(ReorderRequest $reorderRequest)
    {
        $this->reorderRequest = $reorderRequest;
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
                    ->line('Your reorder request has been rejected by warehouse.')
                    ->line('Product: ' . $this->reorderRequest->product->name)
                    ->line('Reason: ' . ($this->reorderRequest->warehouse_notes ?? 'No reason provided'))
                    ->action('View Details', url('/admin/inventory/reorders/' . $this->reorderRequest->id))
                    ->line('Please review the request and contact warehouse if needed.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reorder_rejected',
            'title' => 'Reorder Request Rejected',
            'message' => "Your reorder request for {$this->reorderRequest->product->name} has been rejected by warehouse. Reason: " . ($this->reorderRequest->warehouse_notes ?? 'No reason provided'),
            'reorder_request_id' => $this->reorderRequest->id,
            'product_id' => $this->reorderRequest->product_id,
            'warehouse_notes' => $this->reorderRequest->warehouse_notes,
        ];
    }
}


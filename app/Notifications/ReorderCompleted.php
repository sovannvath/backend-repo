<?php

namespace App\Notifications;

use App\Models\ReorderRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReorderCompleted extends Notification
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
                    ->line('A reorder request has been completed successfully.')
                    ->line('Product: ' . $this->reorderRequest->product->name)
                    ->line('Quantity Added: ' . $this->reorderRequest->quantity_requested)
                    ->line('Cost: $' . number_format($this->reorderRequest->estimated_cost, 2))
                    ->action('View Inventory', url('/admin/inventory'))
                    ->line('The product stock has been updated automatically.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reorder_completed',
            'title' => 'Reorder Completed',
            'message' => 'Reorder for "' . $this->reorderRequest->product->name . '" has been completed. ' . 
                        $this->reorderRequest->quantity_requested . ' units added to inventory.',
            'reorder_request_id' => $this->reorderRequest->id,
            'product_id' => $this->reorderRequest->product_id,
            'product_name' => $this->reorderRequest->product->name,
            'quantity_added' => $this->reorderRequest->quantity_requested,
            'cost' => $this->reorderRequest->estimated_cost,
            'completed_at' => $this->reorderRequest->completed_at,
        ];
    }
}


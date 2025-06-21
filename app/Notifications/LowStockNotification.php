<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    protected $product;

    /**
     * Create a new notification instance.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
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
                    ->subject('Low Stock Alert')
                    ->line('The product "' . $this->product->name . '" is running low on stock.')
                    ->line('Current quantity: ' . $this->product->quantity)
                    ->line('Threshold: ' . $this->product->low_stock_threshold)
                    ->action('View Product', url('/products/' . $this->product->id))
                    ->line('Please reorder soon to avoid stockouts.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'LowStock',
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'current_quantity' => $this->product->quantity,
            'threshold' => $this->product->low_stock_threshold,
            'message' => 'The product "' . $this->product->name . '" is running low on stock.'
        ];
    }
}

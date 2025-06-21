<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification
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
                    ->line('Low stock alert for product: ' . $this->product->name)
                    ->line('Current stock: ' . $this->product->quantity)
                    ->line('This product is running low on stock and may need to be restocked.')
                    ->action('View Product', url('/admin/products/' . $this->product->id))
                    ->line('Please consider reordering this product soon.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_stock_alert',
            'title' => 'Low Stock Alert',
            'message' => 'Product "' . $this->product->name . '" is running low on stock (Current: ' . $this->product->quantity . ')',
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'current_stock' => $this->product->quantity,
            'low_stock_threshold' => $this->product->low_stock_threshold ?? 10,
        ];
    }
}


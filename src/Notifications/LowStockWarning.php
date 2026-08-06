<?php

namespace Cultpantry\Costing\Notifications;

use Cultpantry\Costing\Models\Ingredient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to real admin User accounts (not attached to the Ingredient itself)
 * so mail/database routing works correctly out of the box -- see
 * CheckIngredientLowStock for why this doesn't mirror
 * App\Notifications\LowStockWarning's recipient (that one is attached to
 * the Product row, which has no working mail route).
 */
class LowStockWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public Ingredient $ingredient;

    public float $oldOnHand;

    public float $newOnHand;

    public function __construct(Ingredient $ingredient, float $oldOnHand, float $newOnHand)
    {
        $this->ingredient = $ingredient;
        $this->oldOnHand = $oldOnHand;
        $this->newOnHand = $newOnHand;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $unit = $this->ingredient->isGramBased() ? 'g' : 'unit(s)';

        return (new MailMessage)
            ->subject("Low Stock Alert: {$this->ingredient->name}")
            ->greeting('Low Stock Alert')
            ->line("The ingredient **{$this->ingredient->name}** is now low in stock.")
            ->line("**Current On Hand:** {$this->newOnHand} {$unit}")
            ->line("**Threshold:** {$this->ingredient->low_stock_threshold} {$unit}")
            ->line("**Previous On Hand:** {$this->oldOnHand} {$unit}")
            ->line('⚠️ **This ingredient is running low and may need restocking before your next production run.**')
            ->action('Update Inventory', secure_url("/admin/costing/inventory/{$this->ingredient->id}/edit"))
            ->line('Please review Price History and log a purchase to restock this ingredient.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ingredient_id' => $this->ingredient->id,
            'ingredient_name' => $this->ingredient->name,
            'old_on_hand' => $this->oldOnHand,
            'new_on_hand' => $this->newOnHand,
            'threshold' => $this->ingredient->low_stock_threshold,
            'alert_type' => 'low_stock',
            'ingredient_url' => secure_url("/admin/costing/inventory/{$this->ingredient->id}/edit"),
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'low_stock';
    }
}

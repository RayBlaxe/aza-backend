<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'shipping_cost',
        'total_amount',
        'shipping_address',
        'payment_response',
        'notes',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'payment_response' => 'array',
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_SHIPPED = 'shipped';

    const STATUS_DELIVERED = 'delivered';

    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_STATUS_PENDING = 'pending';

    const PAYMENT_STATUS_PAID = 'paid';

    const PAYMENT_STATUS_FAILED = 'failed';

    const PAYMENT_STATUS_EXPIRED = 'expired';

    const PAYMENT_STATUS_REFUNDED = 'refunded';

    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $sequence = str_pad(
            (Order::whereDate('created_at', today())->count() + 1),
            4,
            '0',
            STR_PAD_LEFT
        );

        return "ORD-{$date}-{$sequence}";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]) &&
               $this->payment_status !== self::PAYMENT_STATUS_PAID;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    public function updatePaymentStatus(string $status, array $paymentResponse = []): void
    {
        $this->update([
            'payment_status' => $status,
            'payment_response' => $paymentResponse,
        ]);

        if ($status === self::PAYMENT_STATUS_PAID && $this->status === self::STATUS_PENDING) {
            $this->update(['status' => self::STATUS_PROCESSING]);
        }
    }

    public function restoreStock(): void
    {
        foreach ($this->orderItems as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->increment('stock', $item->quantity);
            }
        }
    }
}

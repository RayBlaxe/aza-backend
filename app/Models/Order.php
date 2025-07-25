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
        'courier_service',
        'tracking_number',
        'tracking_history',
        'shipped_at',
        'delivered_at',
        'total_amount',
        'shipping_address',
        'payment_response',
        'notes',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'payment_response' => 'array',
        'tracking_history' => 'array',
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
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

    public function updateTrackingStatus(string $status, array $details = []): void
    {
        $trackingHistory = $this->tracking_history ?? [];
        
        $trackingHistory[] = [
            'status' => $status,
            'timestamp' => now()->toISOString(),
            'location' => $details['location'] ?? null,
            'description' => $details['description'] ?? $this->getStatusDescription($status),
            'updated_by' => $details['updated_by'] ?? 'system',
        ];

        $updateData = ['tracking_history' => $trackingHistory];

        // Update status-specific timestamps
        if ($status === self::STATUS_SHIPPED && !$this->shipped_at) {
            $updateData['shipped_at'] = now();
        } elseif ($status === self::STATUS_DELIVERED && !$this->delivered_at) {
            $updateData['delivered_at'] = now();
        }

        $this->update($updateData);
    }

    public function setTrackingNumber(string $trackingNumber): void
    {
        $this->update(['tracking_number' => $trackingNumber]);
        
        $this->updateTrackingStatus('tracking_assigned', [
            'description' => "Nomor resi {$trackingNumber} telah diberikan",
        ]);
    }

    public function getLatestTrackingStatus(): ?array
    {
        if (empty($this->tracking_history)) {
            return null;
        }

        return end($this->tracking_history);
    }

    public function getTrackingProgress(): int
    {
        $statusProgress = [
            self::STATUS_PENDING => 10,
            self::STATUS_PROCESSING => 25,
            'packed' => 40,
            'in_transit' => 60,
            self::STATUS_SHIPPED => 75,
            'out_for_delivery' => 90,
            self::STATUS_DELIVERED => 100,
        ];

        $latestStatus = $this->getLatestTrackingStatus();
        if (!$latestStatus) {
            return $statusProgress[$this->status] ?? 0;
        }

        return $statusProgress[$latestStatus['status']] ?? $statusProgress[$this->status] ?? 0;
    }

    private function getStatusDescription(string $status): string
    {
        $descriptions = [
            self::STATUS_PENDING => 'Pesanan sedang menunggu konfirmasi',
            self::STATUS_PROCESSING => 'Pesanan sedang diproses',
            'packed' => 'Pesanan telah dikemas dan siap dikirim',
            'in_transit' => 'Pesanan dalam perjalanan',
            self::STATUS_SHIPPED => 'Pesanan telah dikirim',
            'out_for_delivery' => 'Pesanan sedang dalam pengiriman terakhir',
            self::STATUS_DELIVERED => 'Pesanan telah diterima',
            'tracking_assigned' => 'Nomor resi telah diberikan',
        ];

        return $descriptions[$status] ?? 'Status tidak diketahui';
    }

    public function canUpdateTracking(): bool
    {
        return in_array($this->status, [
            self::STATUS_PROCESSING,
            self::STATUS_SHIPPED
        ]) && $this->payment_status === self::PAYMENT_STATUS_PAID;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasUuids;

    public const STATUS_NEW = 'new';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const DELIVERY_MODE_DELIVERY = 'delivery';

    public const DELIVERY_MODE_PICKUP = 'pickup';

    public const PAYMENT_MODE_CARD = 'card';

    public const PAYMENT_MODE_CASH = 'cash';

    public const BONUS_EARN_RATE = 0.05;

    public const BONUS_MAX_SPEND_RATE = 0.30;

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_PROCESSING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    public const DELIVERY_MODES = [
        self::DELIVERY_MODE_DELIVERY,
        self::DELIVERY_MODE_PICKUP,
    ];

    public const PAYMENT_MODES = [
        self::PAYMENT_MODE_CARD,
        self::PAYMENT_MODE_CASH,
    ];

    protected $fillable = [
        'user_id',
        'order_number',
        'address_id',
        'status',
        'items_count',
        'subtotal_price',
        'delivery_fee',
        'bonus_points_spent',
        'bonus_points_earned',
        'bonus_points_refunded_at',
        'total_price',
        'comment',
        'delivery_mode',
        'payment_mode',
        'leave_at_door',
        'customer_phone',
    ];

    protected $casts = [
        'order_number' => 'string',
        'items_count' => 'integer',
        'subtotal_price' => 'integer',
        'delivery_fee' => 'integer',
        'bonus_points_spent' => 'integer',
        'bonus_points_earned' => 'integer',
        'bonus_points_refunded_at' => 'datetime',
        'total_price' => 'integer',
        'leave_at_door' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (blank($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        do {
            $number = str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT);
        } while (self::query()->where('order_number', $number)->exists());

        return $number;
    }

    public function formattedOrderNumber(): string
    {
        $number = str_pad(preg_replace('/\D+/', '', (string) $this->order_number), 10, '0', STR_PAD_LEFT);
        $number = substr($number, -10);

        return substr($number, 0, 5).'-'.substr($number, 5);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id');
    }
}

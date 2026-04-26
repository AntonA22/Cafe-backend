<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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
        'address_id',
        'status',
        'items_count',
        'subtotal_price',
        'delivery_fee',
        'total_price',
        'comment',
        'delivery_mode',
        'payment_mode',
        'leave_at_door',
        'customer_phone',
    ];

    protected $casts = [
        'items_count' => 'integer',
        'subtotal_price' => 'integer',
        'delivery_fee' => 'integer',
        'total_price' => 'integer',
        'leave_at_door' => 'boolean',
    ];

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

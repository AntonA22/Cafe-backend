<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'dessert_id', 'qty', 'price', 'sum',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function dessert()
    {
        return $this->belongsTo(Dessert::class);
    }
}

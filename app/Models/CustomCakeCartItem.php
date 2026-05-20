<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomCakeCartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'qty',
        'price',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'qty' => 'integer',
        'price' => 'integer',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }
}

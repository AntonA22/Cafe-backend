<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'dessert_id',
        'qty',
        'price',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function dessert()
    {
        return $this->belongsTo(Dessert::class);
    }
}

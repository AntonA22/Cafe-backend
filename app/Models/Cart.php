<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id'];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function customCakeItems()
    {
        return $this->hasMany(CustomCakeCartItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

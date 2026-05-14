<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DessertFavorite extends Model
{
    protected $table = 'dessert_favorites';

    protected $fillable = [
        'user_id',
        'dessert_id',
    ];
}

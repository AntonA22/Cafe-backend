<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dessert extends Model
{
    protected $table = 'desserts';

    protected $fillable = [
        'name', 'category', 'description', 'price', 'photos',
        'available', 'weight', 'calories', 'proteins', 'fats', 'carbohydrates'
    ];

    protected $casts = [
        'photos' => 'array',        // JSON <-> массив
        'available' => 'boolean',
        'price' => 'float',
        'weight' => 'float',
        'proteins' => 'float',
        'fats' => 'float',
        'carbohydrates' => 'float',
        'calories' => 'integer'
    ];
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CakeDesign extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'subtitle',
        'image_path',
        'image_url',
        'photos',
        'filling',
        'accent',
        'composition',
        'storage',
        'weight_grams',
        'available_weights',
        'price',
        'calories_per_100g',
        'recommended_text',
        'available',
        'sort_order',
    ];

    protected $casts = [
        'weight_grams' => 'integer',
        'available_weights' => 'array',
        'price' => 'float',
        'photos' => 'array',
        'calories_per_100g' => 'integer',
        'available' => 'boolean',
        'sort_order' => 'integer',
    ];
}

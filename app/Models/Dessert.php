<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Dessert extends Model
{
    protected $table = 'desserts';

    protected $fillable = [
        'name', 'category', 'description', 'composition', 'price', 'photos',
        'available', 'archived', 'weight', 'calories', 'proteins', 'fats', 'carbohydrates',
    ];

    protected $casts = [
        'photos' => 'array',        // JSON <-> массив
        'available' => 'boolean',
        'archived' => 'boolean',
        'price' => 'float',
        'weight' => 'float',
        'proteins' => 'float',
        'fats' => 'float',
        'carbohydrates' => 'float',
        'calories' => 'integer',
    ];

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dessert_favorites')
            ->withTimestamps();
    }
}

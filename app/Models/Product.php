<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'ean',
        'upc',
        'image',
        'net_price',
        'taxes',
        'price',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'net_price' => 'decimal:2',
        'taxes' => 'decimal:2',
           'price' => 'float',
//        'price' => 'decimal:2',
    ];

    /**
     * Get the images for the product.
     */
/*    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
*/
    /**
     * The categories that belong to the product.
     */
/*    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }
*/
    /**
     * The tags that belong to the product.
     */
  /*  public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag');
    }*/
}
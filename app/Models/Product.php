<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'description', 'price'];

    protected static function boot()
    {
        parent::boot();

        // Automatically generating slug when creating
        static::creating(function ($product) {
            $product->slug = static::generateUniqueSlug($product->name);
        });
    }

    // Generate a unique slug based on product name
    protected function generateUniqueSlug(string $name)
    {
        $slug = Str::slug($name);
        $count = static::where('slug', 'LIKE', "{$slug}%")->count();

        return $count ? "{$slug}-$count" : $slug;
    }


    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product')->withTimestamps();
    }
}

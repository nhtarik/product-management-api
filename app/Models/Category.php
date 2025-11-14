<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'parent_id'];

    protected static function boot()
    {
        parent::boot();

        // Automatically generating slug when creating
        static::creating(function ($category) {
            $category->slug = static::generateUniqueSlug($category->name);
        });
    }

    // Generate a unique slug based on category name
    protected static function generateUniqueSlug(string $name)
    {
        $slug = Str::slug($name);
        $count = static::where('slug', 'LIKE', "{$slug}%")->count();

        return $count ? "{$slug}-$count" : $slug;
    }


    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->with('children');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product')
            ->withTimestamps();
    }
}

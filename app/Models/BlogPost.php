<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'category',
        'author_name',
        'author_role',
        'author_initials',
        'reading_time',
        'gradient_from',
        'gradient_to',
        'icon_svg',
        'is_featured',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
            
            if (empty($post->published_at) && $post->is_published) {
                $post->published_at = now();
            }
        });
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function getCategoryBadgeColorAttribute()
    {
        return match($this->category) {
            'platform' => 'blue',
            'security' => 'green',
            'developer' => 'purple',
            'industry' => 'yellow',
            'compliance' => 'red',
            default => 'gray',
        };
    }
}

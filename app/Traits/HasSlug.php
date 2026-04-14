<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Auto-generate SEO-friendly slug from name on creating/updating.
 *
 * Usage: use HasSlug; in model that has 'name' and 'slug' columns.
 * Override slugSource() to change source field.
 */
trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::generateUniqueSlug($model->{$model->slugSource()}, $model);
            }
        });

        static::updating(function ($model) {
            // Only auto-regenerate if name changed AND slug was not manually edited
            if ($model->isDirty($model->slugSource()) && ! $model->isDirty('slug')) {
                $model->slug = static::generateUniqueSlug($model->{$model->slugSource()}, $model);
            }
        });
    }

    public function slugSource(): string
    {
        return 'name';
    }

    /**
     * Generate a unique, SEO-friendly slug.
     * Supports Vietnamese characters → ASCII transliteration.
     */
    public static function generateUniqueSlug(string $source, $model = null): string
    {
        $slug = Str::slug($source, '-', 'vi');

        // Fallback if slug is empty (e.g. all special chars)
        if ($slug === '') {
            $slug = 'item-' . Str::random(6);
        }

        // Truncate to reasonable length for URLs
        $slug = Str::limit($slug, 120, '');

        // Ensure uniqueness
        $original = $slug;
        $count = 1;
        $query = static::withoutGlobalScopes()->where('slug', $slug);
        if ($model && $model->exists) {
            $query->where('id', '!=', $model->id);
        }

        while ($query->exists()) {
            $count++;
            $slug = $original . '-' . $count;
            $query = static::withoutGlobalScopes()->where('slug', $slug);
            if ($model && $model->exists) {
                $query->where('id', '!=', $model->id);
            }
        }

        return $slug;
    }

}

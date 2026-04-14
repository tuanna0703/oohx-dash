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
            } else {
                // Validate user-provided slug is unique
                $model->slug = static::ensureUniqueSlug($model->slug, $model);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('slug') && ! empty($model->slug)) {
                // User manually edited slug — ensure unique
                $model->slug = static::ensureUniqueSlug($model->slug, $model);
            } elseif ($model->isDirty($model->slugSource()) && ! $model->isDirty('slug')) {
                // Name changed, slug not manually edited — regenerate
                $model->slug = static::generateUniqueSlug($model->{$model->slugSource()}, $model);
            }
        });
    }

    public function slugSource(): string
    {
        return 'name';
    }

    /**
     * Generate a unique, SEO-friendly slug from source text.
     * Supports Vietnamese → ASCII transliteration.
     * Checks against ALL records including soft-deleted.
     */
    public static function generateUniqueSlug(string $source, $model = null): string
    {
        $slug = Str::slug($source, '-', 'vi');

        if ($slug === '') {
            $slug = 'item-' . Str::random(6);
        }

        $slug = Str::limit($slug, 120, '');

        return static::ensureUniqueSlug($slug, $model);
    }

    /**
     * Ensure slug is unique across all records (including soft-deleted).
     * Appends -2, -3, etc. if duplicate found.
     */
    public static function ensureUniqueSlug(string $slug, $model = null): string
    {
        $original = $slug;
        $count = 1;

        while (static::slugExists($slug, $model)) {
            $count++;
            $slug = $original . '-' . $count;
        }

        return $slug;
    }

    /**
     * Check if slug exists in DB (including soft-deleted records).
     */
    private static function slugExists(string $slug, $model = null): bool
    {
        $query = static::withoutGlobalScopes()->where('slug', $slug);

        // Include soft-deleted records if model uses SoftDeletes
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class))) {
            $query->withTrashed();
        }

        if ($model && $model->exists) {
            $query->where($model->getKeyName(), '!=', $model->getKey());
        }

        return $query->exists();
    }
}

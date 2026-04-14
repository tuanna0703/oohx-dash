<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Str;

/**
 * Auto-generate slug from name if empty on create/save.
 * Use in Filament CreateRecord/EditRecord pages.
 *
 * Assumes model uses HasSlug trait with generateUniqueSlug().
 */
trait ResolvesSlug
{
    protected function resolveSlug(array $data): array
    {
        if (empty($data['slug']) && ! empty($data['name'])) {
            $model = static::getResource()::getModel();
            $data['slug'] = $model::generateUniqueSlug($data['name'], $this->record ?? null);
        }

        return $data;
    }
}

<?php

namespace App\Filament\Publisher\Resources\SiteResource\Pages;

use App\Filament\Concerns\ResolvesSlug;
use App\Filament\Publisher\Resources\SiteResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateSite extends CreateRecord
{
    use ResolvesSlug;

    protected static string $resource = SiteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] = auth()->user()->current_owner_id;
        $data = $this->resolveSlug($data);

        if (empty($data['external_id']) && ! empty($data['name'])) {
            $data['external_id'] = strtoupper(Str::slug($data['name'], '-'));
        }

        return $data;
    }
}

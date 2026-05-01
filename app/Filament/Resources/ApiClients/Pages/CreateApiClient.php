<?php

namespace App\Filament\Resources\ApiClients\Pages;

use App\Filament\Resources\ApiClients\ApiClientResource;
use App\Models\ApiClient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateApiClient extends CreateRecord
{
    protected static string $resource = ApiClientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate raw key + hash + prefix saat record dibuat. Raw key
        // hanya ditampilkan SEKALI di notification setelah save.
        $generated = ApiClient::generateKey();
        $data['api_key_hash'] = $generated['hash'];
        $data['api_key_prefix'] = $generated['prefix'];

        $this->generatedRawKey = $generated['raw'];

        return $data;
    }

    protected ?string $generatedRawKey = null;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('API Client dibuat')
            ->body('Raw API Key (tampil sekali): '.($this->generatedRawKey ?? '—'))
            ->success()
            ->persistent();
    }
}

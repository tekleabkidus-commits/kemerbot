<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Resources\MenuItems\MenuItemResource;
use App\Filament\Support\FormMedia;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuItem extends CreateRecord
{
    protected static string $resource = MenuItemResource::class;

    /** @var array<string, array{label: ?string, reply_text: ?string}> */
    private array $translationData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->translationData = [
            'en' => ['label' => $data['label_en'] ?? null, 'reply_text' => $data['reply_text_en'] ?? null],
            'am' => ['label' => $data['label_am'] ?? null, 'reply_text' => $data['reply_text_am'] ?? null],
        ];

        $data['media_file_id'] = FormMedia::resolveMediaFileId($data['media_upload'] ?? null, null);

        unset($data['label_en'], $data['label_am'], $data['reply_text_en'], $data['reply_text_am'], $data['media_upload']);

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->translationData as $lang => $fields) {
            if (filled($fields['label'])) {
                $this->record->translations()->create([
                    'lang' => $lang,
                    'label' => $fields['label'],
                    'reply_text' => $fields['reply_text'],
                ]);
            }
        }
    }
}

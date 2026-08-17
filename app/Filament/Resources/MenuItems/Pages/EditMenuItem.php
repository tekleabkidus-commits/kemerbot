<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Enums\BotLanguage;
use App\Filament\Resources\MenuItems\MenuItemResource;
use App\Filament\Support\FormMedia;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMenuItem extends EditRecord
{
    protected static string $resource = MenuItemResource::class;

    /** @var array<string, array{label: ?string, reply_text: ?string}> */
    private array $translationData = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Deleting a submenu also deletes all of its children. This cannot be undone.'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $translations = $this->record->translations;
        $en = $translations->firstWhere('lang', BotLanguage::En);
        $am = $translations->firstWhere('lang', BotLanguage::Am);

        $data['label_en'] = $en?->label;
        $data['reply_text_en'] = $en?->reply_text;
        $data['label_am'] = $am?->label;
        $data['reply_text_am'] = $am?->reply_text;
        $data['media_upload'] = $this->record->mediaFile?->path;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->translationData = [
            'en' => ['label' => $data['label_en'] ?? null, 'reply_text' => $data['reply_text_en'] ?? null],
            'am' => ['label' => $data['label_am'] ?? null, 'reply_text' => $data['reply_text_am'] ?? null],
        ];

        $data['media_file_id'] = FormMedia::resolveMediaFileId(
            $data['media_upload'] ?? null,
            $this->record->mediaFile,
        );

        unset($data['label_en'], $data['label_am'], $data['reply_text_en'], $data['reply_text_am'], $data['media_upload']);

        return $data;
    }

    protected function afterSave(): void
    {
        foreach ($this->translationData as $lang => $fields) {
            if (filled($fields['label'])) {
                $this->record->translations()->updateOrCreate(
                    ['lang' => $lang],
                    ['label' => $fields['label'], 'reply_text' => $fields['reply_text']],
                );
            } else {
                $this->record->translations()->where('lang', $lang)->delete();
            }
        }
    }
}

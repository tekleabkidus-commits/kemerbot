<?php

namespace App\Filament\Resources\KeywordReplies\Pages;

use App\Enums\BotLanguage;
use App\Filament\Resources\KeywordReplies\KeywordReplyResource;
use App\Filament\Support\FormMedia;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKeywordReply extends EditRecord
{
    protected static string $resource = KeywordReplyResource::class;

    /** @var array<string, string|null> */
    private array $translationTexts = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $translations = $this->record->translations;

        $data['reply_text_en'] = $translations->firstWhere('lang', BotLanguage::En)?->reply_text;
        $data['reply_text_am'] = $translations->firstWhere('lang', BotLanguage::Am)?->reply_text;
        $data['media_upload'] = $this->record->mediaFile?->path;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->translationTexts = [
            'en' => $data['reply_text_en'] ?? null,
            'am' => $data['reply_text_am'] ?? null,
        ];

        $data['media_file_id'] = FormMedia::resolveMediaFileId(
            $data['media_upload'] ?? null,
            $this->record->mediaFile,
        );

        unset($data['reply_text_en'], $data['reply_text_am'], $data['media_upload']);

        return $data;
    }

    protected function afterSave(): void
    {
        foreach ($this->translationTexts as $lang => $text) {
            if (filled($text)) {
                $this->record->translations()->updateOrCreate(['lang' => $lang], ['reply_text' => $text]);
            } else {
                $this->record->translations()->where('lang', $lang)->delete();
            }
        }
    }
}

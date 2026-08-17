<?php

namespace App\Filament\Resources\KeywordReplies\Pages;

use App\Filament\Resources\KeywordReplies\KeywordReplyResource;
use App\Filament\Support\FormMedia;
use Filament\Resources\Pages\CreateRecord;

class CreateKeywordReply extends CreateRecord
{
    protected static string $resource = KeywordReplyResource::class;

    /** @var array<string, string|null> */
    private array $translationTexts = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->translationTexts = [
            'en' => $data['reply_text_en'] ?? null,
            'am' => $data['reply_text_am'] ?? null,
        ];

        $data['media_file_id'] = FormMedia::resolveMediaFileId($data['media_upload'] ?? null, null);

        unset($data['reply_text_en'], $data['reply_text_am'], $data['media_upload']);

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->translationTexts as $lang => $text) {
            if (filled($text)) {
                $this->record->translations()->create(['lang' => $lang, 'reply_text' => $text]);
            }
        }
    }
}

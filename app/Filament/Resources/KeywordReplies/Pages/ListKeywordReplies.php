<?php

namespace App\Filament\Resources\KeywordReplies\Pages;

use App\Filament\Resources\KeywordReplies\KeywordReplyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKeywordReplies extends ListRecords
{
    protected static string $resource = KeywordReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

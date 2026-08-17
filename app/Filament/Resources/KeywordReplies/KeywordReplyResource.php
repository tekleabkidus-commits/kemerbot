<?php

namespace App\Filament\Resources\KeywordReplies;

use App\Filament\Resources\KeywordReplies\Pages\CreateKeywordReply;
use App\Filament\Resources\KeywordReplies\Pages\EditKeywordReply;
use App\Filament\Resources\KeywordReplies\Pages\ListKeywordReplies;
use App\Filament\Resources\KeywordReplies\Schemas\KeywordReplyForm;
use App\Filament\Resources\KeywordReplies\Tables\KeywordRepliesTable;
use App\Models\KeywordReply;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class KeywordReplyResource extends Resource
{
    protected static ?string $model = KeywordReply::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Bot Content';

    protected static ?string $navigationLabel = 'Auto Replies';

    protected static ?string $modelLabel = 'auto reply';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return KeywordReplyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KeywordRepliesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKeywordReplies::route('/'),
            'create' => CreateKeywordReply::route('/create'),
            'edit' => EditKeywordReply::route('/{record}/edit'),
        ];
    }
}

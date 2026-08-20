<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources;

use DannAPI\FilamentPageBlocks\Filament\Concerns\InteractsWithAdminFields;
use DannAPI\FilamentPageBlocks\Filament\Resources\GeneralInfoResource\Pages;
use DannAPI\FilamentPageBlocks\Models\GeneralInfo;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class GeneralInfoResource extends Resource
{
    use InteractsWithAdminFields;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-information-circle';

    protected static ?string $navigationLabel = 'General info';

    protected static ?string $modelLabel = 'general information';

    protected static ?string $pluralModelLabel = 'general information';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $slug = 'general-info';

    public static function getModel(): string
    {
        return (string) config('filament-page-blocks.models.general_info', GeneralInfo::class);
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return config('filament-page-blocks.filament.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-page-blocks.filament.navigation_sort', 10) + 5;
    }

    public static function getNavigationUrl(): string
    {
        $model = static::getModel();

        try {
            $record = $model::query()->first();
        } catch (QueryException) {
            return static::getUrl('index');
        }

        return $record === null
            ? static::getUrl('index')
            : static::getUrl('edit', ['record' => $record]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('General information')
                ->description('Shared frontend values available in Blade as $generalInfo.')
                ->schema([
                    KeyValue::make('data')
                        ->label('Values')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->valuePlaceholder('Enter a value')
                        ->addable(false)
                        ->deletable(false)
                        ->editableKeys(false)
                        ->reorderable(false)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
            Section::make('Rich text')
                ->description('Named formatted content, sanitized before it is exposed to Blade.')
                ->schema([
                    Repeater::make('rich_text')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('key')
                                ->label('Key')
                                ->required()
                                ->alphaDash()
                                ->distinct()
                                ->maxLength(100)
                                ->disabled()
                                ->dehydrated(),
                            self::richText('content', required: true, label: 'Content')
                                ->columnSpanFull(),
                        ])
                        ->columns(1)
                        ->defaultItems(0)
                        ->itemLabel(static fn (array $state): string => (string) ($state['key'] ?? 'Rich text'))
                        ->collapsed()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columnSpanFull(),
                ])
                ->visible(static fn (?Model $record): bool => static::hasNamedEntries($record?->getAttribute('rich_text')))
                ->columnSpanFull(),
            Section::make('Images')
                ->description('Named images available in Blade through $generalInfo->image() and $generalInfo->imageUrl().')
                ->schema([
                    Repeater::make('images')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('key')
                                ->label('Key')
                                ->required()
                                ->alphaDash()
                                ->distinct()
                                ->maxLength(100)
                                ->disabled()
                                ->dehydrated(),
                            self::image(
                                'path',
                                required: true,
                                label: 'Image',
                                directory: trim((string) config('filament-page-blocks.media.directory', 'page-blocks').'/general-info', '/'),
                            )
                                ->visibility('public')
                                ->openable(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->itemLabel(static fn (array $state): string => (string) ($state['key'] ?? 'Image'))
                        ->collapsed()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columnSpanFull(),
                ])
                ->visible(static fn (?Model $record): bool => static::hasNamedEntries($record?->getAttribute('images')))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Record'),
                TextColumn::make('updated_at')->label('Last updated')->dateTime()->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function canCreate(): bool
    {
        $model = static::getModel();

        return parent::canCreate() && ! $model::query()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeneralInfo::route('/'),
            'create' => Pages\CreateGeneralInfo::route('/create'),
            'edit' => Pages\EditGeneralInfo::route('/{record}/edit'),
        ];
    }

    private static function hasNamedEntries(mixed $entries): bool
    {
        if (! is_array($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if (is_array($entry) && is_string($entry['key'] ?? null) && $entry['key'] !== '') {
                return true;
            }
        }

        return false;
    }
}

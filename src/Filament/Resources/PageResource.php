<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources;

use DannAPI\FilamentPageBlocks\Enums\PageStatus;
use DannAPI\FilamentPageBlocks\Filament\Resources\PageResource\Pages;
use DannAPI\FilamentPageBlocks\Models\Page;
use DannAPI\FilamentPageBlocks\Registry\BlockRegistry;
use DannAPI\FilamentPageBlocks\Registry\PageTemplateRegistry;
use DannAPI\FilamentPageBlocks\Support\HomepageGuard;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

final class PageResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModel(): string
    {
        return (string) config('filament-page-blocks.models.page', Page::class);
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return config('filament-page-blocks.filament.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-page-blocks.filament.navigation_sort', 10);
    }

    public static function form(Schema $schema): Schema
    {
        $formColumns = (int) config('filament-page-blocks.filament.page_form.columns', 4);
        $pageColumnSpan = (int) config('filament-page-blocks.filament.page_form.page_column_span', 3);
        $seoColumnSpan = (int) config('filament-page-blocks.filament.page_form.seo_column_span', 1);

        return $schema->columns(['default' => 1, 'lg' => $formColumns])->components([
            Section::make('Page')
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpan(['default' => 1, 'lg' => $pageColumnSpan])
                ->extraAttributes(['class' => 'fi-fpb-page-form-section'])
                ->schema([
                    TextInput::make('title')->required()->maxLength(255)->live(onBlur: true)
                        ->afterStateUpdated(static function (Set $set, ?string $state, ?Model $record): void {
                            if ($record === null && config('filament-page-blocks.slug.auto_generate', true)) {
                                $set('slug', Str::slug((string) $state, (string) config('filament-page-blocks.slug.separator', '-')));
                            }
                        }),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->regex('/^(?:\/|[A-Za-z0-9_-]+)$/')
                        ->unique(
                            table: (string) config('filament-page-blocks.tables.pages', 'pages'),
                            column: 'slug',
                            ignoreRecord: true,
                        )
                        ->validationMessages([
                            'regex' => 'The slug must be / or contain only letters, numbers, dashes, and underscores.',
                            'unique' => 'This slug is already used by another page.',
                        ]),
                    Select::make('status')
                        ->options(PageStatus::options())
                        ->required()
                        ->selectablePlaceholder(false)
                        ->default(PageStatus::Draft->value)
                        ->live(),
                    DateTimePicker::make('published_at')
                        ->label('Published at')
                        ->required(static fn (Get $get): bool => $get('status') === PageStatus::Scheduled->value)
                        ->minDate(static fn (Get $get): mixed => $get('status') === PageStatus::Scheduled->value
                            ? now()->startOfDay()
                            : null)
                        ->disabled(static fn (Get $get): bool => $get('status') === PageStatus::Published->value)
                        ->visible(static fn (Get $get): bool => in_array($get('status'), [
                            PageStatus::Published->value,
                            PageStatus::Scheduled->value,
                        ], true))
                        ->dehydrated(),
                    Select::make('template')->options(fn (): array => app(PageTemplateRegistry::class)->options())
                        ->default((string) config('filament-page-blocks.default_template', 'default'))
                        ->hidden(static fn (): bool => app(PageTemplateRegistry::class)->hasOnlyDefault())
                        ->dehydratedWhenHidden()
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    Toggle::make('is_homepage')
                        ->label('Homepage')
                        ->visible(static fn (?Model $record): bool => ! app(HomepageGuard::class)->anotherHomepageExists(
                            $record instanceof Page ? $record : null,
                        ))
                        ->dehydratedWhenHidden(false),
                ]),
            Section::make('SEO')
                ->columns(1)
                ->columnSpan(['default' => 1, 'lg' => $seoColumnSpan])
                ->extraAttributes(['class' => 'fi-fpb-page-form-section'])
                ->schema([
                    TextInput::make('seo_title')->maxLength(255),
                    Textarea::make('seo_description')
                        ->maxLength(1000)
                        ->rows(4)
                        ->grow(false),
                ]),
            Section::make('Content')->columnSpanFull()->schema([
                Builder::make('content_blocks')
                    ->label('Blocks')
                    ->blocks(static fn (Get $get, ?Model $record): array => app(BlockRegistry::class)->toFilamentBlocks(
                        page: $record instanceof Page ? $record : null,
                        template: (string) $get('template'),
                    ))
                    ->collapsible()->cloneable()
                    ->deleteAction(static fn (Action $action): Action => $action->visible(
                        static function (Action $action, Builder $component): bool {
                            $key = $action->getArguments()['item'] ?? null;
                            $state = $component->getRawState();

                            return ! (is_string($key) && (bool) ($state[$key]['data']['__system'] ?? false));
                        },
                    ))
                    ->cloneAction(static fn (Action $action): Action => $action->visible(
                        static function (Action $action, Builder $component): bool {
                            if (! $component->isCloneable()) {
                                return false;
                            }

                            $key = $action->getArguments()['item'] ?? null;
                            $state = $component->getRawState();
                            $type = is_string($key) && is_string($state[$key]['type'] ?? null)
                                ? $state[$key]['type']
                                : null;
                            $block = $type === null ? null : $component->getBlock($type);
                            $maxItems = $block?->getMaxItems();

                            if ($maxItems === null) {
                                return true;
                            }

                            return count(array_filter(
                                $state,
                                static fn (array $item): bool => ($item['type'] ?? null) === $type,
                            )) < $maxItems;
                        },
                    ))
                    ->reorderable()->blockIcons()->blockNumbers(false)
                    ->addActionLabel('Add block')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('template')
                    ->visible(static fn (): bool => ! app(PageTemplateRegistry::class)->hasOnlyDefault()),
                IconColumn::make('is_system')->label('System')->boolean(),
                IconColumn::make('is_homepage')->boolean(),
                TextColumn::make('published_at')->dateTime()->sortable(),
            ])
            ->checkIfRecordIsSelectableUsing(static fn (Page $record): bool => ! $record->is_system)
            ->recordClasses(static fn (Page $record): ?string => $record->is_system ? 'fi-page-blocks-system-page' : null)
            ->defaultSort('sort')
            ->reorderable('sort')
            ->filters(config('filament-page-blocks.filament.pages.filters_enabled', false)
                ? [TrashedFilter::make()]
                : [])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make(), ForceDeleteBulkAction::make()])]);
    }

    public static function getEloquentQuery(): EloquentBuilder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}

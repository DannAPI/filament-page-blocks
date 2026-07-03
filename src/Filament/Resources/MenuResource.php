<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources;

use DannAPI\FilamentPageBlocks\Enums\MenuLinkType;
use DannAPI\FilamentPageBlocks\Filament\Resources\MenuResource\Pages;
use DannAPI\FilamentPageBlocks\Models\Menu;
use DannAPI\FilamentPageBlocks\Support\AdminNavigationManager;
use DannAPI\FilamentPageBlocks\Support\HeroiconOptions;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class MenuResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'menus';

    public static function getModel(): string
    {
        return (string) config('filament-page-blocks.models.menu', Menu::class);
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return config('filament-page-blocks.filament.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-page-blocks.filament.navigation_sort', 10) + 1;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Menu')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(static function (Set $set, ?string $state, ?Model $record): void {
                        if ($record === null) {
                            $set('handle', Str::slug((string) $state, '-'));
                        }
                    }),
                TextInput::make('handle')
                    ->required()
                    ->alphaDash()
                    ->maxLength(255)
                    ->disabled(static fn (?Menu $record): bool => $record?->isSystem() ?? false)
                    ->dehydrated()
                    ->unique(
                        table: (string) config('filament-page-blocks.tables.menus', 'menus'),
                        column: 'handle',
                        ignoreRecord: true,
                    )
                    ->helperText('Use this identifier in menus.header, menus.footer or menus.admin.handle config.'),
            ]),
            Section::make('Items')->columnSpanFull()->schema([
                Repeater::make('items')
                    ->relationship()
                    ->mutateRelationshipDataBeforeFillUsing(
                        static fn (array $data): array => self::prepareItemForForm($data),
                    )
                    ->mutateRelationshipDataBeforeCreateUsing(
                        static fn (array $data): array => self::prepareItemForStorage($data),
                    )
                    ->mutateRelationshipDataBeforeSaveUsing(
                        static fn (array $data): array => self::prepareItemForStorage($data),
                    )
                    ->schema(static fn (Get $get): array => self::itemSchema(
                        withChildren: true,
                        adminMenu: $get('handle') === config('filament-page-blocks.menus.admin.handle', 'admin'),
                    ))
                    ->orderColumn('sort')
                    ->itemLabel(static fn (array $state): string => (string) ($state['label'] ?? 'Menu item'))
                    ->addActionLabel('Add menu item')
                    ->collapsed()
                    ->cloneable()
                    ->deleteAction(static fn (Action $action): Action => self::protectAutomaticAdminItemAction($action))
                    ->cloneAction(static fn (Action $action): Action => self::protectAutomaticAdminItemAction($action))
                    ->columnSpanFull(),
            ]),
        ]);
    }

    /** @return array<Component> */
    private static function itemSchema(bool $withChildren, bool $adminMenu = false): array
    {
        $schema = [
            TextInput::make('label')->required()->maxLength(255)->columnSpan(2),
            Select::make('link_type')
                ->label('Route type')
                ->options($adminMenu
                    ? [
                        MenuLinkType::Admin->value => 'Filament section',
                        MenuLinkType::Custom->value => 'Custom URL',
                    ]
                    : [MenuLinkType::Page->value => 'Page', MenuLinkType::Custom->value => 'Custom URL'])
                ->default($adminMenu ? MenuLinkType::Admin->value : MenuLinkType::Page->value)
                ->required()
                ->selectablePlaceholder(false)
                ->live(),
            Select::make('target')
                ->options(['_self' => 'Same tab', '_blank' => 'New tab'])
                ->default('_self')
                ->required()
                ->selectablePlaceholder(false)
                ->visible(static fn (Get $get): bool => ! $adminMenu || $get('link_type') === MenuLinkType::Custom->value),
            Select::make('page_id')
                ->label('Page')
                ->relationship('page', 'title')
                ->searchable()
                ->preload()
                ->selectablePlaceholder(false)
                ->required(static fn (Get $get): bool => $get('link_type') === MenuLinkType::Page->value)
                ->visible(static fn (Get $get): bool => $get('link_type') === MenuLinkType::Page->value)
                ->columnSpan(2),
            TextInput::make('url')
                ->label('Custom URL')
                ->placeholder('/contact, https://example.com, #section')
                ->maxLength(2048)
                ->required(static fn (Get $get): bool => $get('link_type') === MenuLinkType::Custom->value)
                ->visible(static fn (Get $get): bool => $get('link_type') === MenuLinkType::Custom->value)
                ->dehydratedWhenHidden(false)
                ->columnSpan(2),
            Select::make('admin_url')
                ->label('Admin section')
                ->options(static fn (): array => app(AdminNavigationManager::class)->options())
                ->searchable()
                ->selectablePlaceholder(false)
                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                ->required(static fn (Get $get): bool => $get('link_type') === MenuLinkType::Admin->value)
                ->visible(static fn (Get $get): bool => $get('link_type') === MenuLinkType::Admin->value)
                ->dehydratedWhenHidden(false)
                ->live()
                ->afterStateUpdated(static function (Get $get, Set $set, ?string $state): void {
                    if (filled($get('label')) || blank($state)) {
                        return;
                    }

                    $set('label', app(AdminNavigationManager::class)->label($state) ?? class_basename($state));
                })
                ->helperText('The section remains hidden when the current role cannot access it.')
                ->columnSpan(2),
        ];

        if ($adminMenu) {
            $schema[] = self::iconField();
        }

        $schema[] = Toggle::make('is_visible')->label('Visible')->default(true);

        if ($withChildren) {
            $schema[] = Repeater::make('children')
                ->relationship()
                ->mutateRelationshipDataBeforeFillUsing(
                    static fn (array $data): array => self::prepareItemForForm($data),
                )
                ->mutateRelationshipDataBeforeCreateUsing(
                    static fn (array $data): array => self::prepareItemForStorage($data),
                )
                ->mutateRelationshipDataBeforeSaveUsing(
                    static fn (array $data): array => self::prepareItemForStorage($data),
                )
                ->schema(self::itemSchema(withChildren: false, adminMenu: $adminMenu))
                ->defaultItems(0)
                ->orderColumn('sort')
                ->itemLabel(static fn (array $state): string => (string) ($state['label'] ?? 'Dropdown item'))
                ->addActionLabel('Add dropdown item')
                ->collapsed()
                ->cloneable()
                ->visible(static fn (Get $get): bool => ! $adminMenu || $get('link_type') !== MenuLinkType::Admin->value)
                ->columnSpanFull();
        }

        return $schema;
    }

    private static function iconField(): Select
    {
        return Select::make('icon')
            ->label('Heroicon')
            ->options(static fn (): array => app(HeroiconOptions::class)->search())
            ->getSearchResultsUsing(
                static fn (?string $search): array => app(HeroiconOptions::class)->search($search),
            )
            ->getOptionLabelUsing(
                static fn (mixed $value): ?string => app(HeroiconOptions::class)->label($value),
            )
            ->prefixIcon(static fn (mixed $state): ?string => app(HeroiconOptions::class)->contains($state)
                ? $state
                : null)
            ->in(static fn (): array => app(HeroiconOptions::class)->names())
            ->searchable()
            ->preload()
            ->allowHtml()
            ->native(false)
            ->optionsLimit((int) config('filament-page-blocks.menus.admin.icons.result_limit', 48))
            ->suffixAction(self::browseIconsAction())
            ->placeholder('Use Resource default')
            ->searchPrompt('Search Heroicons by name')
            ->helperText('Only Outline Heroicons are loaded. Search returns a limited result page for performance.')
            ->columnSpan(2);
    }

    private static function browseIconsAction(): Action
    {
        return Action::make('browseHeroicons')
            ->label('Browse Heroicons')
            ->icon('heroicon-o-squares-2x2')
            ->modalHeading('Choose an Outline Heroicon')
            ->modalDescription('Browse pages or narrow the list by icon name. Only one icon can be selected.')
            ->modalSubmitActionLabel('Use selected icon')
            ->modalWidth(Width::SixExtraLarge)
            ->fillForm(static function (Select $component): array {
                $state = $component->getState();

                return [
                    'search' => '',
                    'page' => 1,
                    'icon' => is_string($state) && $state !== '' ? $state : null,
                ];
            })
            ->schema([
                TextInput::make('search')
                    ->label('Search')
                    ->placeholder('For example: cloud, user, arrow')
                    ->live(debounce: 300)
                    ->afterStateUpdated(static function (Set $set): void {
                        $set('page', 1);
                        $set('icon', null);
                    }),
                self::iconPagination(),
                ToggleButtons::make('icon')
                    ->label('Icons')
                    ->options(static fn (Get $get): array => app(HeroiconOptions::class)->pageLabels(
                        $get('search'),
                        max(1, (int) $get('page')),
                    ))
                    ->icons(static function (Get $get): array {
                        $icons = app(HeroiconOptions::class)->pageLabels(
                            $get('search'),
                            max(1, (int) $get('page')),
                        );

                        return array_combine(array_keys($icons), array_keys($icons)) ?: [];
                    })
                    ->columns(['default' => 2, 'md' => 3, 'xl' => 4])
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(static function (array $data, Set $set): void {
                $selected = $data['icon'] ?? null;
                if (is_string($selected) && app(HeroiconOptions::class)->contains($selected)) {
                    $set('icon', $selected);
                }
            });
    }

    private static function iconPagination(): Actions
    {
        $actions = [
            Action::make('previousIconPage')
                ->label('Previous page')
                ->icon('heroicon-m-chevron-left')
                ->iconButton()
                ->disabled(static fn (Get $get): bool => (int) $get('page') <= 1)
                ->action(static function (Get $get, Set $set): void {
                    $set('page', max(1, (int) $get('page') - 1));
                    $set('icon', null);
                }),
        ];

        $pageCount = count(app(HeroiconOptions::class)->pageOptions());
        for ($page = 1; $page <= $pageCount; $page++) {
            $actions[] = Action::make("iconPage{$page}")
                ->label((string) $page)
                ->color(static fn (Get $get): string => (int) $get('page') === $page ? 'primary' : 'gray')
                ->outlined(static fn (Get $get): bool => (int) $get('page') !== $page)
                ->visible(static fn (Get $get): bool => $page <= count(
                    app(HeroiconOptions::class)->pageOptions($get('search')),
                ))
                ->action(static function (Set $set) use ($page): void {
                    $set('page', $page);
                    $set('icon', null);
                });
        }

        $actions[] = Action::make('nextIconPage')
            ->label('Next page')
            ->icon('heroicon-m-chevron-right')
            ->iconButton()
            ->disabled(static fn (Get $get): bool => (int) $get('page') >= count(
                app(HeroiconOptions::class)->pageOptions($get('search')),
            ))
            ->action(static function (Get $get, Set $set): void {
                $lastPage = count(app(HeroiconOptions::class)->pageOptions($get('search')));
                $set('page', min($lastPage, (int) $get('page') + 1));
                $set('icon', null);
            });

        return Actions::make($actions)
            ->alignment(Alignment::Center)
            ->columnSpanFull();
    }

    /** @param array<string, mixed> $data */
    private static function prepareItemForForm(array $data): array
    {
        $data['admin_url'] = self::linkTypeValue($data['link_type'] ?? null) === MenuLinkType::Admin->value
            ? ($data['url'] ?? null)
            : null;

        return $data;
    }

    /** @param array<string, mixed> $data */
    private static function prepareItemForStorage(array $data): array
    {
        $linkType = self::linkTypeValue($data['link_type'] ?? null);

        if ($linkType === MenuLinkType::Admin->value) {
            $data['url'] = $data['admin_url'] ?? null;
            $data['page_id'] = null;
            $data['target'] = '_self';
        } elseif ($linkType === MenuLinkType::Custom->value) {
            $data['page_id'] = null;
        } else {
            $data['url'] = null;
        }

        unset($data['admin_url']);

        return $data;
    }

    private static function linkTypeValue(mixed $linkType): ?string
    {
        return $linkType instanceof MenuLinkType ? $linkType->value : (is_string($linkType) ? $linkType : null);
    }

    private static function protectAutomaticAdminItemAction(Action $action): Action
    {
        return $action->visible(static function (array $arguments, Repeater $component): bool {
            $key = $arguments['item'] ?? null;
            if (! is_string($key)) {
                return false;
            }

            $type = $component->getRawItemState($key)['link_type'] ?? null;

            return $type !== MenuLinkType::Admin && $type !== MenuLinkType::Admin->value;
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(static function (Builder $query): Builder {
                /** @var Menu $model */
                $model = $query->getModel();
                $handles = $model::systemHandles();

                if ($handles === []) {
                    return $query->orderBy($model->qualifyColumn($model->getKeyName()));
                }

                $cases = [];
                $bindings = [];
                foreach ($handles as $sort => $handle) {
                    $cases[] = 'WHEN ? THEN '.(int) $sort;
                    $bindings[] = $handle;
                }

                return $query
                    ->orderByRaw(
                        'CASE '.$model->qualifyColumn('handle').' '.implode(' ', $cases).' ELSE '.count($handles).' END',
                        $bindings,
                    )
                    ->orderBy($model->qualifyColumn($model->getKeyName()));
            })
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('handle')->badge()->searchable(),
                TextColumn::make('all_items_count')->counts('allItems')->label('Items'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->checkIfRecordIsSelectableUsing(
                static fn (Menu $record): bool => ! $record->isSystem(),
            )
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(static fn (Menu $record): bool => ! $record->isSystem()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}

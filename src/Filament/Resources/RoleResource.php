<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources;

use DannAPI\FilamentPageBlocks\Filament\Resources\RoleResource\Pages;
use DannAPI\FilamentPageBlocks\Models\Role;
use DannAPI\FilamentPageBlocks\Registry\PermissionRegistry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class RoleResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'roles';

    public static function getModel(): string
    {
        return (string) config('filament-page-blocks.models.role', Role::class);
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return config('filament-page-blocks.filament.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-page-blocks.filament.navigation_sort', 10) + 2;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Role')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(static function (Set $set, ?string $state, ?Model $record): void {
                        if ($record === null) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')->required()->alphaDash()->maxLength(255)
                    ->disabled(static fn (?Role $record): bool => (bool) $record?->is_system)
                    ->dehydrated()
                    ->unique(
                        table: (string) config('filament-page-blocks.tables.roles', 'roles'),
                        column: 'slug',
                        ignoreRecord: true,
                    ),
            ])->columnSpanFull(),
            Section::make('Permissions')
                ->columns(1)
                ->schema(self::permissionSections())
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->badge(),
                TextColumn::make('users_count')->counts('users')->label('Users'),
                IconColumn::make('is_system')->boolean()->label('System'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->checkIfRecordIsSelectableUsing(static fn (Role $record): bool => ! $record->is_system)
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    /** @return array<Component> */
    private static function permissionSections(): array
    {
        $sections = [];
        foreach (app(PermissionRegistry::class)->groups() as $group => $permissions) {
            $sections[] = Section::make($group)
                ->compact()
                ->schema([
                    CheckboxList::make('permission_groups.'.self::permissionGroupKey($group))
                        ->hiddenLabel()
                        ->options($permissions)
                        ->bulkToggleable()
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                        ->disabled(static fn (?Role $record): bool => in_array($record?->slug, ['admin', 'user'], true))
                        ->dehydrated(),
                ])
                ->columnSpanFull();
        }

        return $sections;
    }

    /** @param array<int, string> $selected @return array<string, array<int, string>> */
    public static function permissionGroupsState(array $selected): array
    {
        $state = [];
        foreach (app(PermissionRegistry::class)->groups() as $group => $permissions) {
            $state[self::permissionGroupKey($group)] = array_values(array_intersect(array_keys($permissions), $selected));
        }

        return $state;
    }

    /** @param array<string, mixed> $groups @return array<int, string> */
    public static function flattenPermissionGroups(array $groups): array
    {
        $permissions = [];
        foreach ($groups as $selected) {
            if (is_array($selected)) {
                $permissions = [...$permissions, ...array_filter($selected, 'is_string')];
            }
        }

        return array_values(array_unique($permissions));
    }

    private static function permissionGroupKey(string $group): string
    {
        return Str::snake($group);
    }
}

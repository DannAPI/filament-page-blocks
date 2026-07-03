<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources;

use DannAPI\FilamentPageBlocks\Filament\Resources\UserResource\Pages;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class UserResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'users';

    public static function getModel(): string
    {
        return (string) config('filament-page-blocks.authorization.user_model', 'App\\Models\\User');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return config('filament-page-blocks.filament.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-page-blocks.filament.navigation_sort', 10) + 3;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('User')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(
                            table: (string) config('filament-page-blocks.tables.users', 'users'),
                            column: 'email',
                            ignoreRecord: true,
                        ),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->live(debounce: 500)
                        ->required(static fn (string $operation): bool => $operation === 'create')
                        ->minLength(8)
                        ->maxLength(255)
                        ->same('password_confirmation')
                        ->dehydrated(static fn (?string $state): bool => filled($state))
                        ->helperText('Leave empty when editing to keep the current password.'),
                    TextInput::make('password_confirmation')
                        ->label('Confirm password')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->required(static fn (Get $get): bool => filled($get('password')))
                        ->visible(static fn (Get $get): bool => filled($get('password')))
                        ->dehydrated(false),
                    Select::make('roles')
                        ->label('Role')
                        ->relationship('roles', 'name')
                        ->required()
                        ->selectablePlaceholder(false)
                        ->preload()
                        ->searchable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('roles.name')->label('Roles')->badge(),
                IconColumn::make('is_system')->label('System')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->checkIfRecordIsSelectableUsing(
                static fn (Model $record): bool => ! (bool) $record->getAttribute('is_system'),
            )
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

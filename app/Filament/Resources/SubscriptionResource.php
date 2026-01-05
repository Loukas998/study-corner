<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('customer_id')
                    ->relationship(
                        'customer',
                        'full_name',
                        function (Builder $query) {
                            return $query->select('id', 'phone_number', 'first_name', 'last_name', 'middle_name')
                                ->selectRaw("CONCAT(
                                    COALESCE(phone_number, ''), 
                                    ' - ', 
                                    COALESCE(first_name, ''), 
                                    ' ', 
                                    COALESCE(middle_name, ''), 
                                    ' ', 
                                    COALESCE(last_name, '')
                                ) as full_name")
                                ->orderByRaw("CONCAT(
                                    COALESCE(first_name, ''), 
                                    ' ', 
                                    COALESCE(middle_name, ''), 
                                    ' ', 
                                    COALESCE(last_name, '')
                                )");
                        })
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('middle_name')
                            ->maxLength(255),
                            
                        TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),
                        
                        TextInput::make('national_id')
                            ->maxLength(255),
                        
                        TextInput::make('phone_number')
                            ->maxLength(255),
                    ])
                    ->required(),
                Select::make('package_id')
                    ->label('Package')
                    ->relationship('package', 'number_of_hours') 
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if (! $state) {
                            $set('ends_at', null);
                            $set('remaining_hours', 0);
                            return;
                        }

                        $package = \App\Models\Package::find($state);

                        if ($package?->duration_in_days) {
                            $set(
                                'ends_at',
                                Carbon::today()->addDays($package->duration_in_days),
                            );

                            $set(
                                'remaining_hours',
                                $package->number_of_hours,
                            );
                        }
                    })
                    ->required(),
                DatePicker::make('ends_at')
                    ->disabled()
                    ->dehydrated() // still saved to DB
                    ->required(),
                TextInput::make('remaining_hours')
                    ->numeric()
                    ->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.first_name')->label('First name')
                    ->searchable(),
                TextColumn::make('customer.middle_name')->label('Middle name')
                    ->searchable(),
                TextColumn::make('customer.last_name')->label('Last name')
                    ->searchable(),
                TextColumn::make('customer.phone_number')->label('Phone number')
                    ->searchable(),
                TextColumn::make('remaining_hours'),
                TextColumn::make('ends_at')
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}

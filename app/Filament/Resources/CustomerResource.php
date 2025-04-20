<?php

namespace App\Filament\Resources;

use App\Filament\Exports\CustomerExporter;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\ExportAction;


class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('first_name')
                    ->required()
                    ->maxLength(255),
                    
                TextInput::make('last_name')
                    ->required()
                    ->maxLength(255),
                    
                TextInput::make('national_id')
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone_number')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // ->headerActions([
            //     ExportAction::make()
            //         ->exporter(CustomerExporter::class)
            // ])
            ->columns([
                Tables\Columns\TextColumn::make('first_name'),
                Tables\Columns\TextColumn::make('last_name'),
                Tables\Columns\TextColumn::make('national_id'),
                Tables\Columns\TextColumn::make('phone_number'),
                Tables\Columns\TextColumn::make('visits_sum_duration')
                    ->label('Total Hours')
                    ->state(function (Customer $record): string {
                        $totalMinutes = $record->visits()
                            ->whereNotNull('exit_time')
                            ->get()
                            ->sum(function ($visit) {
                                $entrance = \Carbon\Carbon::parse($visit->entrance_time);
                                $exit = \Carbon\Carbon::parse($visit->exit_time);
                                return $entrance->diffInMinutes($exit);
                            });
                        
                        $hours = floor($totalMinutes / 60);
                        $minutes = $totalMinutes % 60;
                        $total_hours = sprintf('%d:%02d', $hours, $minutes);
                        if($total_hours !== $record->total_hours)
                        {
                            $record->total_hours =  $total_hours;
                            $record->save();
                            return $total_hours;
                        }

                        return $total_hours;
                    }),
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitResource\Pages;
use App\Models\Visit;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

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

                TimePicker::make('entrance_time')
                    ->timezone('Asia/Damascus')
                    ->default(now())
                    ->seconds(false)
                    ->required(),

                TimePicker::make('exit_time')
                    ->timezone('Asia/Damascus')
                    ->seconds(false),

                Forms\Components\DatePicker::make('visit_date')
                    ->weekStartsOnSunday()
                    ->closeOnDateSelection()
                    ->default(now())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.first_name')->label('First name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.middle_name')->label('Middle name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.last_name')->label('Last name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.phone_number')->label('Phone number')
                    ->searchable(),

                Tables\Columns\TextColumn::make('entrance_time')
                    ->timezone('Asia/Damascus')
                    ->time(),

                Tables\Columns\TextColumn::make('exit_time')
                    ->timezone('Asia/Damascus')
                    ->time(),
                
                Tables\Columns\TextColumn::make('visit_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit_duration')
                    ->label('Visit duration')
                    ->state(function(Visit $visit) {
                        if ($visit->exit_time === null) {
                            return 'Still Active';
                        }
                        
                        return $visit->visit_duration;
                    })
            ])
            ->filters([
                Tables\Filters\Filter::make('visit_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('visit_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('visit_date', '<=', $date),
                            );
                    }),

                Tables\Filters\Filter::make('is_active')
                    ->query(function (Builder $query) : Builder {
                        return $query->where('exit_time', null);
                    }),

                Tables\Filters\Filter::make('is_closed')
                    ->query(function (Builder $query) : Builder {
                        return $query->whereNot('exit_time', null);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('close_visit')
                    ->label('Close Visit')
                    ->color('success')
                    ->visible(fn (Visit $record): bool => $record->exit_time === null)
                    ->action(function (Visit $record): void {
                        $record->exit_time = now();
                        
                        // Calculate visit duration
                        $entrance = \Carbon\Carbon::parse($record->entrance_time);
                        $exit = \Carbon\Carbon::parse($record->exit_time);
                        $totalMinutes = $entrance->diffInMinutes($exit);
                        
                        $hours = floor($totalMinutes / 60);
                        $minutes = $totalMinutes % 60;
                        
                        $record->visit_duration = sprintf('%d:%02d', $hours, $minutes);
                        $record->save();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Close Visit')
                    ->modalDescription('Are you sure you want to close this visit? This will set the exit time to now.')
                    ->modalSubmitActionLabel('Yes, close visit'),
                ExportAction::make()->exports([
                    ExcelExport::make()->fromTable(),
                ])
                    ->label('Export Data')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                ExportBulkAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                    ])
                    ->label('Export Selected Data')
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
            'index' => Pages\ListVisits::route('/'),
            'create' => Pages\CreateVisit::route('/create'),
            'edit' => Pages\EditVisit::route('/{record}/edit'),
        ];
    }
}

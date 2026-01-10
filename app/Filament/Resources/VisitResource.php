<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitResource\Pages;
use App\Models\Subscription;
use App\Models\Visit;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('customer');
    }

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
                    ->default(today())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.first_name')->label('First name'),
                Tables\Columns\TextColumn::make('customer.middle_name')->label('Middle name'),
                Tables\Columns\TextColumn::make('customer.last_name')->label('Last name'),
                Tables\Columns\TextColumn::make('customer.phone_number')->label('Phone number'),

                Tables\Columns\TextColumn::make('entrance_time')
                    ->timezone('Asia/Damascus')
                    ->time(),

                Tables\Columns\TextColumn::make('exit_time')
                    ->timezone('Asia/Damascus')
                    ->time(),
                
                Tables\Columns\TextColumn::make('visit_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit_duration_display')
                    ->label('Visit duration')
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
                        $hours = $totalMinutes / 60;

                        $floor_hours = floor($hours);
                        $minutes = $totalMinutes % 60;
                        
                        $record->visit_duration = sprintf('%d:%02d', $floor_hours, $minutes);

                         // Calculate subscription
                        $active_subscription = Subscription::where('customer_id', $record->customer_id)
                            ->where('remaining_hours', '>', 0)
                            ->where('ends_at', '>=', Carbon::now())
                            ->with('package')
                            ->first();
                        
                            
                        if($active_subscription) {
                            $ceil_hours = $totalMinutes >= 8 ? ceil($hours) : $floor_hours;
                            $remaining_before = $active_subscription->remaining_hours;
                            $remaining_after = $remaining_before - $ceil_hours;
                            
                            $active_subscription->remaining_hours = max(0, $remaining_after);
                            $remaining_hours = $active_subscription->remaining_hours;
                            $active_subscription->save();
                            Notification::make()
                                    ->title('This customer has subscription')
                                    ->body(
                                        "The customer has consumed {$ceil_hours} from his subscription.\n" .
                                        "Remaining hours: {$remaining_hours} hour(s)."
                                    )
                                    ->success()
                                    ->send();
                            if($remaining_after <= 0.0) {
                                $extra_hours = abs($remaining_after);

                                Notification::make()
                                    ->title('Subscription Finished')
                                    ->body(
                                        "The customer's subscription has ended during this visit.\n" .
                                        "Extra time consumed: {$extra_hours} hour(s)."
                                    )
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }else {
                            Notification::make()
                                ->title('Saved successfully')
                                ->body('Customer has no active subscription. Closing the visit...')
                                ->success()
                                ->send();
                        }

                        $record->save();
                        Notification::make()
                            ->title('Saved successfully')
                            ->success()
                            ->send();
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

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'customer.first_name',
            'customer.middle_name',
            'customer.last_name',
            'customer.phone_number',
        ];
    }


}

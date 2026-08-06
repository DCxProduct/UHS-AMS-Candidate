<?php

namespace App\Filament\Admin\Resources\Payments;

use App\Filament\Admin\Resources\Payments\Pages\CreatePayment;
use App\Filament\Admin\Resources\Payments\Pages\EditPayment;
use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Admin\Resources\Payments\Tables\PaymentsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\Payment;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class PaymentResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Payment::class;

    protected static ?string $slug = 'payment-records';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('navigation.payments');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('navigation.groups.cashier');
    }

    public static function getModelLabel(): string
    {
        return __('payments.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! static::currentUserIsAdmin()) {
            $userId = auth()->id();

            if (! $userId) {
                return $query->whereRaw('1 = 0');
            }

            $query->where('users_id', $userId);
        }

        return $query;
    }

    protected static function currentUserIsAdmin(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return method_exists($user, 'hasEffectiveRole')
            ? $user->hasEffectiveRole('admin')
            : $user->registration_type === 'admin';
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'edit' => EditPayment::route('/{record}/edit'),
        ];
    }
}

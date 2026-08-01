<?php

namespace App\Models;

use App\Enums\WalletType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int|null $balance_subunits
 */
class Wallet extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'currency_id',
        'type',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            'type' => WalletType::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * @param  Builder<Wallet>  $query
     * @return Builder<Wallet>
     */
    public function scopeUserOwned(Builder $query): Builder
    {
        return $query->where('type', WalletType::User);
    }

    /**
     * @param  Builder<Wallet>  $query
     * @return Builder<Wallet>
     */
    public function scopeOfType(Builder $query, WalletType $type): Builder
    {
        return $query->where('type', $type);
    }

    public static function slugForUser(string $userId, string $currencyCode): string
    {
        return "user:{$userId}:{$currencyCode}";
    }

    public static function slugForSystem(WalletType $type, string $currencyCode): string
    {
        return "{$type->value}:{$currencyCode}";
    }
}

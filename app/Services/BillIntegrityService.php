<?php

namespace App\Services;

use App\Models\Bill;
use Illuminate\Database\Eloquent\Builder;

class BillIntegrityService
{
    public const GAP_MISSING_DOCUMENT = 'missing_document';

    public const GAP_NO_ITEMS = 'no_items';

    public const GAP_NO_PROVIDER = 'no_provider';

    public static function scopeMissingDocument(Builder $query): Builder
    {
        return $query->where(function (Builder $scoped): void {
            $scoped->whereNull('bill_google_link')
                ->orWhere('bill_google_link', '');
        });
    }

    public static function scopeWithoutItems(Builder $query): Builder
    {
        return $query->whereDoesntHave('items');
    }

    public static function scopeWithoutProvider(Builder $query): Builder
    {
        return $query->whereNull('provider_id');
    }

    /**
     * Bills ≠ Docs: missing Drive link, no bill items, or no provider.
     */
    public static function scopeBillsWithoutDocuments(Builder $query): Builder
    {
        return $query->where(function (Builder $scoped): void {
            $scoped->where(fn (Builder $missingDocument) => self::scopeMissingDocument($missingDocument))
                ->orWhere(fn (Builder $withoutItems) => self::scopeWithoutItems($withoutItems))
                ->orWhere(fn (Builder $withoutProvider) => self::scopeWithoutProvider($withoutProvider));
        });
    }

    public static function missingDocument(Bill $bill): bool
    {
        return $bill->bill_google_link === null || $bill->bill_google_link === '';
    }

    public static function missingItems(Bill $bill): bool
    {
        if ($bill->relationLoaded('items')) {
            return $bill->items->isEmpty();
        }

        if (array_key_exists('items_count', $bill->getAttributes())) {
            return (int) $bill->items_count === 0;
        }

        return ! $bill->items()->exists();
    }

    public static function missingProvider(Bill $bill): bool
    {
        return $bill->provider_id === null;
    }

    public static function belongsOnWithoutDocumentsList(Bill $bill): bool
    {
        return self::missingDocument($bill)
            || self::missingItems($bill)
            || self::missingProvider($bill);
    }

    /**
     * @return list<string>
     */
    public static function withoutDocumentsGaps(Bill $bill): array
    {
        $gaps = [];

        if (self::missingDocument($bill)) {
            $gaps[] = self::GAP_MISSING_DOCUMENT;
        }

        if (self::missingItems($bill)) {
            $gaps[] = self::GAP_NO_ITEMS;
        }

        if (self::missingProvider($bill)) {
            $gaps[] = self::GAP_NO_PROVIDER;
        }

        return $gaps;
    }

    public static function withoutDocumentsGapLabel(string $gapKey): string
    {
        return match ($gapKey) {
            self::GAP_MISSING_DOCUMENT => 'Missing document',
            self::GAP_NO_ITEMS => 'No bill items',
            self::GAP_NO_PROVIDER => 'No provider',
            default => ucfirst(str_replace('_', ' ', $gapKey)),
        };
    }

    public static function scopeWithoutTransactionLink(Builder $query): Builder
    {
        return $query
            ->doesntHave('transactions')
            ->whereNull('transaction_id');
    }

    public static function scopeWithFileComment(Builder $query): Builder
    {
        return $query->whereHas('file.comments', function (Builder $comments): void {
            $comments->whereNotNull('content')
                ->where('content', '!=', '');
        });
    }

    /**
     * Bills missing a document, not linked to any bank transaction, but explained via a case comment.
     */
    public static function scopeMissingDocumentWithoutTransactionWithComment(Builder $query): Builder
    {
        return self::scopeWithFileComment(
            self::scopeWithoutTransactionLink(
                self::scopeMissingDocument($query)
            )
        );
    }

    public static function hasTransactionLink(Bill $bill): bool
    {
        if (filled($bill->transaction_id)) {
            return true;
        }

        if ($bill->relationLoaded('transactions')) {
            return $bill->transactions->isNotEmpty();
        }

        return $bill->transactions()->exists();
    }
}

<?php

namespace App\Services;

use App\Models\Bill;
use Illuminate\Database\Eloquent\Builder;

class BillIntegrityService
{
    public static function scopeMissingDocument(Builder $query): Builder
    {
        return $query->where(function (Builder $scoped): void {
            $scoped->whereNull('bill_google_link')
                ->orWhere('bill_google_link', '');
        });
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

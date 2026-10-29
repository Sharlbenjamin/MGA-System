<?php

namespace App\Services;

use App\Models\ExpoPushReceipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ExpoPushReceiptService
{
    /** @var list<int> Seconds to wait before each retry (Expo receipts are not immediate). */
    protected array $backoffSeconds = [30, 120, 300, 900, 1800];

    /**
     * @param  list<string>  $receiptIds
     * @param  list<string|null>  $tokensByIndex
     */
    public function queueReceipts(array $receiptIds, array $tokensByIndex = []): void
    {
        if ($receiptIds === [] || ! Schema::hasTable('expo_push_receipts')) {
            return;
        }

        $checkAt = Carbon::now()->addSeconds($this->backoffSeconds[0]);

        foreach ($receiptIds as $index => $receiptId) {
            ExpoPushReceipt::query()->updateOrCreate(
                ['receipt_id' => $receiptId],
                [
                    'expo_push_token' => $tokensByIndex[$index] ?? null,
                    'attempts' => 0,
                    'next_check_at' => $checkAt,
                    'state' => 'pending',
                    'last_error' => null,
                ],
            );
        }
    }

    public function processDue(int $limit = 100): int
    {
        if (! Schema::hasTable('expo_push_receipts')) {
            return 0;
        }

        $store = app(MobilePushTokenStore::class);
        $rows = ExpoPushReceipt::query()
            ->where('state', 'pending')
            ->where('next_check_at', '<=', Carbon::now())
            ->orderBy('next_check_at')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $ids = $rows->pluck('receipt_id')->all();

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://exp.host/--/api/v2/push/getReceipts', [
                    'ids' => array_slice($ids, 0, 100),
                ]);

            if (! $response->successful()) {
                Log::warning('Expo push receipt HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $this->deferAll($rows);

                return 0;
            }

            $receipts = $response->json('data');
            if (! is_array($receipts)) {
                $this->deferAll($rows);

                return 0;
            }
        } catch (\Throwable $e) {
            Log::warning('Expo push receipt batch failed', ['error' => $e->getMessage()]);
            $this->deferAll($rows);

            return 0;
        }

        $processed = 0;

        foreach ($rows as $row) {
            $receipt = $receipts[$row->receipt_id] ?? null;
            if (! is_array($receipt)) {
                $this->scheduleRetry($row);

                continue;
            }

            if (($receipt['status'] ?? '') === 'ok') {
                $row->update(['state' => 'ok', 'last_error' => null]);
                $processed++;

                continue;
            }

            if (($receipt['status'] ?? '') !== 'error') {
                $this->scheduleRetry($row);

                continue;
            }

            $message = (string) ($receipt['message'] ?? '');
            $details = $receipt['details'] ?? null;
            Log::warning('Expo push receipt error', [
                'receipt_id' => $row->receipt_id,
                'message' => $message,
                'details' => $details,
            ]);

            $errorCode = is_array($details) ? ($details['error'] ?? '') : '';
            $token = is_array($details) ? ($details['expoPushToken'] ?? null) : null;
            if (! is_string($token) || $token === '') {
                $token = $row->expo_push_token;
            }
            if (is_string($token) && in_array($errorCode, ['DeviceNotRegistered', 'InvalidCredentials'], true)) {
                $store->forgetToken($token);
            }

            $row->update([
                'state' => 'error',
                'last_error' => $message !== '' ? $message : $errorCode,
            ]);
            $processed++;
        }

        return $processed;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ExpoPushReceipt>  $rows
     */
    protected function deferAll($rows): void
    {
        foreach ($rows as $row) {
            $this->scheduleRetry($row);
        }
    }

    protected function scheduleRetry(ExpoPushReceipt $row): void
    {
        $attempt = (int) $row->attempts + 1;
        $max = count($this->backoffSeconds);

        if ($attempt >= $max) {
            $row->update([
                'attempts' => $attempt,
                'state' => 'error',
                'last_error' => 'Receipt still unavailable after retries',
            ]);

            return;
        }

        $row->update([
            'attempts' => $attempt,
            'next_check_at' => Carbon::now()->addSeconds($this->backoffSeconds[$attempt]),
        ]);
    }
}

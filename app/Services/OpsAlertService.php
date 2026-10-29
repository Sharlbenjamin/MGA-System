<?php

namespace App\Services;

use App\Models\Client;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Provider;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpsAlertService
{
    public function fileCreated(File $file): void
    {
        $ref = $file->mga_reference ?: ('Case #'.$file->id);

        $this->alert(
            'view File',
            'New case '.$ref,
            $this->fileNotificationBody($file, 'A new case was inserted.'),
            [
                'kind' => 'file_created',
                'subject_type' => 'file',
                'subject_id' => $file->id,
            ],
        );
    }

    public function fileAssisted(File $file): void
    {
        $ref = $file->mga_reference ?: ('Case #'.$file->id);

        $this->alert(
            'view File',
            'Case '.$ref.' is Assisted',
            $this->fileNotificationBody($file, 'Status changed to Assisted.'),
            [
                'kind' => 'file_assisted',
                'subject_type' => 'file',
                'subject_id' => $file->id,
            ],
        );
    }

    public function fileSent(File $file): void
    {
        $ref = $file->mga_reference ?: ('Case #'.$file->id);

        $this->alert(
            'view File',
            'Case '.$ref.' is Sent',
            $this->fileNotificationBody($file, 'Status changed to Sent.'),
            [
                'kind' => 'file_sent',
                'subject_type' => 'file',
                'subject_id' => $file->id,
            ],
        );
    }

    public function invoiceSent(Invoice $invoice): void
    {
        $invoice->loadMissing(['file.patient.client', 'file.country']);
        $file = $invoice->file;
        $ref = $file?->mga_reference ?: ('Case #'.($file?->id ?? $invoice->file_id));
        $invoiceName = $invoice->name ?: ('Invoice #'.$invoice->id);
        $body = $file
            ? $this->fileNotificationBody($file, $invoiceName.' is now Sent.')
            : trim($invoiceName.' is now Sent.');

        $this->alert(
            'view File',
            'Case '.$ref.' invoice sent',
            $body,
            [
                'kind' => 'invoice_sent',
                'subject_type' => 'file',
                'subject_id' => (int) ($file?->id ?? $invoice->file_id),
            ],
        );
    }

    public function clientActive(Client $client, bool $created): void
    {
        $name = $client->company_name ?: ('Client #'.$client->id);

        $this->alert(
            'view Client',
            'Client '.$name.' is Active',
            $created
                ? 'A client was created with status Active.'
                : 'Client status changed to Active.',
            [
                'kind' => 'client_active',
                'subject_type' => 'client',
                'subject_id' => $client->id,
            ],
        );
    }

    public function providerActive(Provider $provider, bool $created): void
    {
        $name = $provider->name ?: ('Provider #'.$provider->id);

        $this->alert(
            'view Provider',
            'Provider '.$name.' is Active',
            $created
                ? 'A provider was created with status Active.'
                : 'Provider status changed to Active.',
            [
                'kind' => 'provider_active',
                'subject_type' => 'provider',
                'subject_id' => $provider->id,
            ],
        );
    }

    /**
     * @param  array{kind: string, subject_type: string, subject_id: int}  $data
     */
    protected function alert(string $permission, string $title, string $body, array $data): void
    {
        $users = $this->recipients($permission);
        $actorId = Auth::id();
        $databaseUsers = $actorId
            ? $users->reject(fn (User $user) => (int) $user->id === (int) $actorId)->values()
            : $users;

        if ($databaseUsers->isNotEmpty()) {
            try {
                Notification::make()
                    ->title($title)
                    ->body($body)
                    ->icon('heroicon-o-bell')
                    ->status('info')
                    ->viewData($data)
                    ->sendToDatabase($databaseUsers);
            } catch (\Throwable $e) {
                Log::warning('Ops database notification failed', [
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $store = app(MobilePushTokenStore::class);
        $targets = [];
        foreach ($users as $user) {
            foreach ($store->pushTargetsForUser($user->id) as $target) {
                $targets[$target['token']] = $target;
            }
        }

        if ($targets === []) {
            Log::info('Ops alert skipped mobile push — no registered device tokens', [
                'kind' => $data['kind'],
                'recipient_count' => $users->count(),
            ]);

            return;
        }

        $messages = array_map(function (array $target) use ($title, $body, $data) {
            $message = [
                'to' => $target['token'],
                'title' => $title,
                'body' => $body,
                'priority' => 'high',
                'badge' => 1,
                'channelId' => 'default',
                'data' => [
                    'kind' => $data['kind'],
                    'subject_type' => $data['subject_type'],
                    'subject_id' => (string) $data['subject_id'],
                ],
            ];
            $sound = $this->expoPushSound($target['notification_sound'] ?? 'default');
            if ($sound !== null) {
                $message['sound'] = $sound;
            }

            return $message;
        }, array_values($targets));

        try {
            // Send during the request so PHP workers that terminate early still deliver push.
            // Failures are logged only — they must not roll back the case update that triggered the alert.
            $this->deliverExpoPush($messages, $store);
        } catch (\Throwable $e) {
            Log::warning('Ops mobile push delivery failed', [
                'kind' => $data['kind'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    protected function deliverExpoPush(array $messages, MobilePushTokenStore $store): void
    {
        if ($messages === []) {
            return;
        }

        foreach (array_chunk($messages, 100) as $chunk) {
            try {
                $response = Http::timeout(12)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Accept-Encoding' => 'gzip, deflate',
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://exp.host/--/api/v2/push/send', $chunk);

                if (! $response->successful()) {
                    Log::warning('Expo push HTTP error', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                $payload = $response->json('data');
                if (! is_array($payload)) {
                    continue;
                }

                $receiptIds = [];
                $receiptTokens = [];
                foreach ($payload as $index => $ticket) {
                    if (($ticket['status'] ?? '') === 'ok' && ! empty($ticket['id'])) {
                        $receiptIds[] = $ticket['id'];
                        $receiptTokens[] = $chunk[$index]['to'] ?? null;

                        continue;
                    }

                    if (($ticket['status'] ?? '') !== 'error') {
                        continue;
                    }

                    Log::warning('Expo push ticket error', [
                        'message' => $ticket['message'] ?? '',
                        'details' => $ticket['details'] ?? null,
                    ]);

                    $invalidToken = $ticket['details']['expoPushToken'] ?? ($chunk[$index]['to'] ?? null);
                    $errorCode = $ticket['details']['error'] ?? '';
                    if (is_string($invalidToken) && in_array($errorCode, ['DeviceNotRegistered', 'InvalidCredentials'], true)) {
                        $store->forgetToken($invalidToken);
                    }
                }

                if ($receiptIds !== []) {
                    app(ExpoPushReceiptService::class)->queueReceipts($receiptIds, $receiptTokens);
                }
            } catch (\Throwable $e) {
                Log::warning('Expo push failed', ['error' => $e->getMessage()]);
            }
        }
    }

    protected function fileNotificationBody(File $file, string $suffix): string
    {
        $file->loadMissing(['patient.client', 'client', 'country']);

        $client = $file->client?->company_name
            ?: $file->patient?->client?->company_name;
        $country = $file->country?->nicename ?: $file->country?->name;

        $parts = array_values(array_filter([
            is_string($client) ? trim($client) : '',
            is_string($country) ? trim($country) : '',
        ], fn (string $value) => $value !== ''));

        if ($parts !== []) {
            return implode(' · ', $parts).' — '.$suffix;
        }

        return $suffix;
    }

    protected function expoPushSound(string $preference): ?string
    {
        $preference = trim($preference);
        if ($preference === '' || $preference === 'default') {
            return 'default';
        }
        if ($preference === 'none') {
            return null;
        }

        $allowed = ['mga_bell.wav', 'mga_chime.wav', 'mga_alert.wav'];
        if (in_array($preference, $allowed, true)) {
            return $preference;
        }

        return 'default';
    }

    /**
     * @return Collection<int, User>
     */
    protected function recipients(string $permission): Collection
    {
        try {
            $users = User::permission($permission)->get();
            if ($users->isNotEmpty()) {
                return $users;
            }
        } catch (\Throwable $e) {
            Log::warning('Ops alert permission lookup failed', [
                'permission' => $permission,
                'error' => $e->getMessage(),
            ]);
        }

        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'Admin']))
            ->get();
    }
}

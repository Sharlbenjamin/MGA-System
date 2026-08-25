<?php

namespace App\Services\Communications;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Support\Communications\CommunicationContext;
use Illuminate\Support\Arr;

class CommunicationLogService
{
    public function createPrepared(
        CommunicationContext $context,
        CommunicationChannel $channel,
        ?int $templateId,
        ?string $subject,
        string $body,
        User $user,
        ?string $recipientPhone = null,
        ?string $recipientLabel = null,
        array $metadata = [],
    ): CommunicationLog {
        $case = $context->case ?? ($context->cases[0] ?? null);

        return CommunicationLog::query()->create([
            'user_id' => $user->id,
            'channel' => $channel->value,
            'recipient_type' => null,
            'recipient_id' => null,
            'provider_id' => $context->provider?->id,
            'provider_branch_id' => $context->providerBranch?->id,
            'file_id' => $case?->id,
            'context_type' => $context->contextType->value,
            'context_id' => $case?->id,
            'template_id' => $templateId,
            'subject' => $subject,
            'body' => $body,
            'status' => CommunicationStatus::Prepared->value,
            'metadata' => array_merge($metadata, array_filter([
                'recipient_phone' => $recipientPhone,
                'recipient_label' => $recipientLabel,
                'selected_file_ids' => Arr::pluck($context->cases, 'id'),
                'selected_bill_ids' => Arr::pluck($context->bills, 'id'),
                'selected_transaction_ids' => Arr::pluck($context->transactions, 'id'),
            ])),
        ]);
    }

    public function markOpened(CommunicationLog $log, array $metadata = []): CommunicationLog
    {
        return $this->updateStatus($log, CommunicationStatus::Opened, $metadata);
    }

    public function updateStatus(CommunicationLog $log, CommunicationStatus $status, array $metadata = []): CommunicationLog
    {
        $log->status = $status->value;
        $log->metadata = array_merge($log->metadata ?? [], $metadata);
        $log->save();

        return $log->fresh();
    }
}

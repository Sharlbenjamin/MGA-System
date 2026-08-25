<?php

namespace App\Services\Communications;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationContextType;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communications\Channels\WhatsAppChannel;
use App\Support\Communications\CommunicationContext;
use App\Support\Communications\PhoneCandidate;
use Illuminate\Support\Collection;

class CommunicationService
{
    public function __construct(
        protected CommunicationTemplateRenderer $renderer,
        protected CommunicationRecipientResolver $recipientResolver,
        protected CommunicationLogService $logService,
        protected WhatsAppChannel $whatsAppChannel,
    ) {}

    public function findTemplate(CommunicationContextType $contextType, CommunicationChannel $channel = CommunicationChannel::WhatsApp): ?CommunicationTemplate
    {
        return CommunicationTemplate::query()
            ->where('key', $contextType->defaultTemplateKey())
            ->where('channel', $channel->value)
            ->where('is_active', true)
            ->first();
    }

    public function renderMessage(CommunicationTemplate $template, CommunicationContext $context): string
    {
        return trim($this->renderer->render($template->body_template, $context));
    }

    public function renderSubject(CommunicationTemplate $template, CommunicationContext $context): ?string
    {
        if (blank($template->subject_template)) {
            return null;
        }

        return trim($this->renderer->render($template->subject_template, $context));
    }

    /**
     * @return Collection<int, PhoneCandidate>
     */
    public function resolveWhatsAppRecipients(CommunicationContext $context): Collection
    {
        return $this->recipientResolver->resolveWhatsAppCandidates(
            $context->provider,
            $context->providerBranch,
        );
    }

    public function prepareLog(
        CommunicationContext $context,
        CommunicationTemplate $template,
        string $body,
        User $user,
        PhoneCandidate $recipient,
        ?string $subject = null,
    ): CommunicationLog {
        return $this->logService->createPrepared(
            context: $context,
            channel: CommunicationChannel::WhatsApp,
            templateId: $template->id,
            subject: $subject,
            body: $body,
            user: $user,
            recipientPhone: $recipient->normalized,
            recipientLabel: $recipient->label,
        );
    }

    public function openWhatsApp(CommunicationLog $log, PhoneCandidate $recipient, string $body): string
    {
        return $this->whatsAppChannel->openConversation($log, $recipient->normalized, $body);
    }

    public function markUserSent(CommunicationLog $log): CommunicationLog
    {
        return $this->whatsAppChannel->markUserSent($log);
    }
}

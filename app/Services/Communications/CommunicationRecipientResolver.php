<?php

namespace App\Services\Communications;

use App\Models\Contact;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Support\Communications\PhoneCandidate;
use App\Support\Communications\PhoneNumberNormalizer;
use Illuminate\Support\Collection;

class CommunicationRecipientResolver
{
    public function __construct(
        protected PhoneNumberNormalizer $normalizer,
    ) {}

    /**
     * @return Collection<int, PhoneCandidate>
     */
    public function resolveWhatsAppCandidates(?Provider $provider, ?ProviderBranch $branch = null): Collection
    {
        $candidates = collect();

        if ($branch) {
            $this->collectBranchCandidates($candidates, $branch);
        }

        if ($provider) {
            $this->collectProviderCandidates($candidates, $provider);
        }

        return $this->deduplicateCandidates($candidates);
    }

    /**
     * @param  Collection<int, PhoneCandidate>  $candidates
     */
    protected function collectBranchCandidates(Collection $candidates, ProviderBranch $branch): void
    {
        $branch->loadMissing(['operationContact', 'gopContact', 'financialContact', 'provider']);

        if ($branch->communication_method === 'WhatsApp' && filled($branch->phone)) {
            $this->pushPhone($candidates, $branch->phone, 'Branch phone (WhatsApp preferred)', 'branch.phone');
        } elseif (filled($branch->phone)) {
            $this->pushPhone($candidates, $branch->phone, 'Branch phone', 'branch.phone');
        }

        foreach ([
            ['contact' => $branch->operationContact, 'label' => 'Operation contact'],
            ['contact' => $branch->gopContact, 'label' => 'GOP contact'],
            ['contact' => $branch->financialContact, 'label' => 'Financial contact'],
        ] as $entry) {
            $this->collectContactPhones($candidates, $entry['contact'], $entry['label'].' (branch)');
        }
    }

    /**
     * @param  Collection<int, PhoneCandidate>  $candidates
     */
    protected function collectProviderCandidates(Collection $candidates, Provider $provider): void
    {
        $provider->loadMissing(['operationContact', 'gopContact', 'financialContact']);

        if (filled($provider->phone)) {
            $this->pushPhone($candidates, $provider->phone, 'Provider phone', 'provider.phone');
        }

        foreach ([
            ['contact' => $provider->operationContact, 'label' => 'Operation contact'],
            ['contact' => $provider->gopContact, 'label' => 'GOP contact'],
            ['contact' => $provider->financialContact, 'label' => 'Financial contact'],
        ] as $entry) {
            $this->collectContactPhones($candidates, $entry['contact'], $entry['label'].' (provider)');
        }
    }

    /**
     * @param  Collection<int, PhoneCandidate>  $candidates
     */
    protected function collectContactPhones(Collection $candidates, ?Contact $contact, string $prefix): void
    {
        if (! $contact) {
            return;
        }

        if (in_array($contact->preferred_contact, ['first_whatsapp', 'Phone'], true) && filled($contact->phone_number)) {
            $this->pushPhone($candidates, $contact->phone_number, $prefix.' — primary', 'contact.primary');
        }

        if (in_array($contact->preferred_contact, ['second_whatsapp', 'Second Phone'], true) && filled($contact->second_phone)) {
            $this->pushPhone($candidates, $contact->second_phone, $prefix.' — secondary', 'contact.secondary');
        }

        if (filled($contact->phone_number)) {
            $this->pushPhone($candidates, $contact->phone_number, $prefix.' — phone', 'contact.phone');
        }

        if (filled($contact->second_phone)) {
            $this->pushPhone($candidates, $contact->second_phone, $prefix.' — second phone', 'contact.second_phone');
        }
    }

    /**
     * @param  Collection<int, PhoneCandidate>  $candidates
     */
    protected function pushPhone(Collection $candidates, string $raw, string $label, string $source): void
    {
        $normalized = $this->normalizer->normalize($raw);

        if (! $normalized) {
            return;
        }

        $candidates->push(new PhoneCandidate($label, $raw, $normalized, $source));
    }

    /**
     * @param  Collection<int, PhoneCandidate>  $candidates
     * @return Collection<int, PhoneCandidate>
     */
    protected function deduplicateCandidates(Collection $candidates): Collection
    {
        $seen = [];

        return $candidates->filter(function (PhoneCandidate $candidate) use (&$seen): bool {
            if (isset($seen[$candidate->normalized])) {
                return false;
            }

            $seen[$candidate->normalized] = true;

            return true;
        })->values();
    }
}

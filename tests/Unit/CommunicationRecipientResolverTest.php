<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Services\Communications\CommunicationRecipientResolver;
use App\Support\Communications\PhoneNumberNormalizer;
use Tests\TestCase;

class CommunicationRecipientResolverTest extends TestCase
{
    public function test_collects_branch_and_provider_phone_candidates(): void
    {
        $provider = new Provider([
            'name' => 'Provider A',
            'phone' => '+353111111111',
        ]);

        $branch = new ProviderBranch([
            'branch_name' => 'Branch A',
            'phone' => '0872222222',
            'communication_method' => 'WhatsApp',
            'provider_id' => 1,
        ]);
        $branch->setRelation('provider', $provider);

        $operationContact = new Contact([
            'phone_number' => '0873333333',
            'preferred_contact' => 'Phone',
        ]);
        $branch->setRelation('operationContact', $operationContact);
        $branch->setRelation('gopContact', null);
        $branch->setRelation('financialContact', null);

        $resolver = new CommunicationRecipientResolver(new PhoneNumberNormalizer('353'));
        $candidates = $resolver->resolveWhatsAppCandidates($provider, $branch);

        $this->assertNotEmpty($candidates);
        $normalized = $candidates->pluck('normalized')->all();
        $this->assertContains('353872222222', $normalized);
        $this->assertContains('353111111111', $normalized);
    }

    public function test_deduplicates_identical_numbers(): void
    {
        $provider = new Provider([
            'name' => 'Provider B',
            'phone' => '+353871234567',
        ]);

        $branch = new ProviderBranch([
            'branch_name' => 'Branch B',
            'phone' => '+353871234567',
        ]);
        $branch->setRelation('provider', $provider);
        $branch->setRelation('operationContact', null);
        $branch->setRelation('gopContact', null);
        $branch->setRelation('financialContact', null);

        $resolver = new CommunicationRecipientResolver(new PhoneNumberNormalizer('353'));
        $candidates = $resolver->resolveWhatsAppCandidates($provider, $branch);

        $this->assertCount(1, $candidates);
    }
}

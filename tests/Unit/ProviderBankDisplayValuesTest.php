<?php

namespace Tests\Unit;

use App\Filament\Resources\TransactionResource;
use App\Models\BankAccount;
use App\Models\Country;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProviderBankDisplayValuesTest extends TestCase
{
    #[Test]
    public function it_maps_bank_account_fields_for_the_transaction_form(): void
    {
        $account = new BankAccount([
            'iban' => 'DE89370400440532013000',
            'beneficiary_name' => 'Klinik GmbH',
            'swift' => 'COBADEFFXXX',
        ]);
        $account->setRelation('country', new Country(['name' => 'Germany']));

        $this->assertSame([
            'iban' => 'DE89370400440532013000',
            'beneficiary_name' => 'Klinik GmbH',
            'swift' => 'COBADEFFXXX',
            'country' => 'Germany',
        ], TransactionResource::providerBankDisplayValues($account));
    }

    #[Test]
    public function it_returns_empty_strings_when_no_bank_account_is_selected(): void
    {
        $this->assertSame([
            'iban' => '',
            'beneficiary_name' => '',
            'swift' => '',
            'country' => '',
        ], TransactionResource::providerBankDisplayValues(null));
    }
}

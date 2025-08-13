<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\BankAccount;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_account_filtering_shows_only_internal_accounts()
    {
        // Create test data
        $country = Country::factory()->create();
        
        // Create internal bank account
        $internalAccount = BankAccount::factory()->create([
            'type' => 'Internal',
            'beneficiary_name' => 'Internal Bank Account',
            'country_id' => $country->id,
        ]);
        
        // Create provider bank account
        $providerAccount = BankAccount::factory()->create([
            'type' => 'Provider',
            'beneficiary_name' => 'Provider Bank Account',
            'country_id' => $country->id,
        ]);
        
        // Test that only internal accounts are returned
        $internalAccounts = BankAccount::where('type', 'Internal')->get();
        
        $this->assertCount(1, $internalAccounts);
        $this->assertEquals('Internal Bank Account', $internalAccounts->first()->beneficiary_name);
    }

    public function test_provider_bank_account_relationship_exists()
    {
        // Create test data
        $country = Country::factory()->create();
        $provider = Provider::factory()->create();
        
        $bankAccount = BankAccount::factory()->create([
            'type' => 'Provider',
            'provider_id' => $provider->id,
            'beneficiary_name' => 'Test Provider Bank',
            'iban' => 'TEST123456789',
            'swift' => 'TESTSWIFT',
            'country_id' => $country->id,
        ]);
        
        // Test the relationship
        $this->assertNotNull($provider->bankAccounts()->first());
        $this->assertEquals('Test Provider Bank', $provider->bankAccounts()->first()->beneficiary_name);
    }

    public function test_branch_bank_account_relationship_exists()
    {
        // Create test data
        $country = Country::factory()->create();
        $provider = Provider::factory()->create();
        $branch = ProviderBranch::factory()->create([
            'provider_id' => $provider->id,
        ]);
        
        $bankAccount = BankAccount::factory()->create([
            'type' => 'Branch',
            'branch_id' => $branch->id,
            'beneficiary_name' => 'Test Branch Bank',
            'iban' => 'TEST987654321',
            'swift' => 'TESTSWIFT2',
            'country_id' => $country->id,
        ]);
        
        // Test the relationship
        $this->assertNotNull($branch->bankAccounts()->first());
        $this->assertEquals('Test Branch Bank', $branch->bankAccounts()->first()->beneficiary_name);
    }
}

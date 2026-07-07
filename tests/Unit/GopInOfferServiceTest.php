<?php

namespace Tests\Unit;

use App\Models\Gop;
use App\Services\GopInOfferService;
use Tests\TestCase;

class GopInOfferServiceTest extends TestCase
{
    public function test_resolve_gop_in_cost_and_total_sums_offered_cost_and_file_fee(): void
    {
        $service = new GopInOfferService();

        $gop = new Gop([
            'offered_cost' => 100,
            'file_fee' => 50,
            'amount' => 100,
        ]);

        [$cost, $total] = $service->resolveGopInCostAndTotal($gop);

        $this->assertSame(100.0, $cost);
        $this->assertSame(150.0, $total);
    }

    public function test_resolve_gop_in_cost_and_total_uses_amount_minus_fee_when_only_amount_is_set(): void
    {
        $service = new GopInOfferService();

        $gop = new Gop([
            'offered_cost' => null,
            'file_fee' => 50,
            'amount' => 150,
        ]);

        [$cost, $total] = $service->resolveGopInCostAndTotal($gop);

        $this->assertSame(100.0, $cost);
        $this->assertSame(150.0, $total);
    }

    public function test_resolve_cost_and_total_for_branch_prefers_gop_in_over_service_pricing(): void
    {
        $service = new GopInOfferService();

        $file = new \App\Models\File(['service_type_id' => 5]);
        $branch = new \App\Models\ProviderBranch(['id' => 1]);
        $gop = new Gop([
            'offered_cost' => 100,
            'file_fee' => 50,
            'amount' => 100,
        ]);

        [$cost, $total] = $service->resolveCostAndTotalForBranch($file, $branch, $gop);

        $this->assertSame(100.0, $cost);
        $this->assertSame(150.0, $total);
    }
}

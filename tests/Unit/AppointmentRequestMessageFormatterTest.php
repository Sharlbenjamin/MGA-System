<?php

namespace Tests\Unit;

use App\Models\File;
use App\Models\Gop;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use App\Services\AppointmentRequestMessageFormatter;
use App\Services\GopInOfferService;
use Mockery;
use Tests\TestCase;

class AppointmentRequestMessageFormatterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_uses_accepted_gop_in_cost_and_total_without_patient_address(): void
    {
        $serviceType = new ServiceType(['id' => 5, 'name' => 'Clinic Visit']);

        $file = new File([
            'service_type_id' => 5,
            'service_date' => now()->parse('2026-07-10'),
            'service_time' => '14:30:00',
            'address' => '123 Patient Street',
        ]);
        $file->setRelation('serviceType', $serviceType);

        $branch = new ProviderBranch([
            'id' => 42,
            'branch_name' => 'Rome Medical Center',
            'address' => 'Via Roma 1, Rome',
        ]);

        $acceptedGop = new Gop([
            'type' => 'In',
            'status' => Gop::IN_STATUS_ACCEPTED,
            'provider_branch_id' => 42,
            'offered_cost' => 450,
            'file_fee' => 55,
            'amount' => 505,
            'service_type_id' => 5,
        ]);
        $acceptedGop->setRelation('serviceType', $serviceType);

        $offerService = Mockery::mock(GopInOfferService::class);
        $offerService->shouldReceive('resolveGopInForAppointmentMessage')->once()->with($file, $branch)->andReturn($acceptedGop);
        $offerService->shouldReceive('resolveCostAndTotalForBranch')->once()->with($file, $branch, $acceptedGop)->andReturn([450.0, 505.0]);

        $message = (new AppointmentRequestMessageFormatter($offerService))->format($file, $branch, 20);

        $this->assertStringContainsString('Here are the details of the nearest available clinic:', $message);
        $this->assertStringContainsString('Provider: Rome Medical Center', $message);
        $this->assertStringContainsString('Address: Via Roma 1, Rome', $message);
        $this->assertStringContainsString('Distance: 20 mins by car', $message);
        $this->assertStringContainsString('Date & Time: 10/07/2026 at 14:30', $message);
        $this->assertStringContainsString('Cost: 450€', $message);
        $this->assertStringContainsString('Requested GOP: 505€', $message);
        $this->assertStringContainsString('Please let us know if these details suits the patient in order to proceed with the booking or check for another appointment', $message);
        $this->assertStringNotContainsString('Patient Address', $message);
        $this->assertStringNotContainsString('123 Patient Street', $message);
    }

    public function test_it_uses_custom_service_type_from_accepted_gop_in(): void
    {
        $file = new File([
            'service_type_id' => 5,
            'service_date' => now()->parse('2026-07-10'),
        ]);
        $file->setRelation('serviceType', new ServiceType(['name' => 'Clinic Visit']));

        $branch = new ProviderBranch([
            'id' => 7,
            'branch_name' => 'Specialist Clinic',
            'address' => 'Main Street',
        ]);

        $acceptedGop = new Gop([
            'type' => 'In',
            'status' => Gop::IN_STATUS_ACCEPTED,
            'provider_branch_id' => 7,
            'offered_cost' => 300,
            'file_fee' => 40,
            'amount' => 340,
            'service_type_other' => 'Cardiology specialist',
        ]);

        $offerService = Mockery::mock(GopInOfferService::class);
        $offerService->shouldReceive('resolveGopInForAppointmentMessage')->once()->with($file, $branch)->andReturn($acceptedGop);
        $offerService->shouldReceive('resolveCostAndTotalForBranch')->once()->with($file, $branch, $acceptedGop)->andReturn([300.0, 340.0]);

        $message = (new AppointmentRequestMessageFormatter($offerService))->format($file, $branch);

        $this->assertStringContainsString('Here are the details of the nearest available Cardiology specialist:', $message);
        $this->assertStringContainsString('Cost: 300€', $message);
        $this->assertStringContainsString('Requested GOP: 340€', $message);
    }

    public function test_it_builds_house_visit_intro(): void
    {
        $formatter = new AppointmentRequestMessageFormatter(Mockery::mock(GopInOfferService::class));

        $this->assertSame(
            'Here are the details of the nearest available house visit provider:',
            $formatter->buildIntro('House Call'),
        );
    }

    public function test_it_uses_branch_gop_in_even_when_amount_field_is_stale(): void
    {
        $file = new File([
            'service_type_id' => 5,
            'service_date' => now()->parse('2026-07-07'),
        ]);
        $file->setRelation('serviceType', new ServiceType(['name' => 'Clinic Visit']));

        $branch = new ProviderBranch([
            'id' => 99,
            'branch_name' => 'Dooctor.ie Abbeyfeale',
            'address' => 'The Old Bank, Main Street, Abbeyfeale, Co. Limerick',
        ]);

        $gopIn = new Gop([
            'type' => 'In',
            'status' => Gop::IN_STATUS_DRAFT,
            'provider_branch_id' => 99,
            'offered_cost' => 100,
            'file_fee' => 50,
            'amount' => 100,
        ]);

        $offerService = Mockery::mock(GopInOfferService::class);
        $offerService->shouldReceive('resolveGopInForAppointmentMessage')->once()->with($file, $branch)->andReturn($gopIn);
        $offerService->shouldReceive('resolveCostAndTotalForBranch')->once()->with($file, $branch, $gopIn)->andReturn([100.0, 150.0]);

        $message = (new AppointmentRequestMessageFormatter($offerService))->format($file, $branch);

        $this->assertStringContainsString('Cost: 100€', $message);
        $this->assertStringContainsString('Requested GOP: 150€', $message);
    }

    public function test_it_includes_branch_request_comment_when_set(): void
    {
        $file = new File([
            'service_type_id' => 5,
            'service_date' => now()->parse('2026-07-10'),
        ]);
        $file->setRelation('serviceType', new ServiceType(['name' => 'Clinic Visit']));

        $branch = new ProviderBranch([
            'id' => 11,
            'branch_name' => 'City Clinic',
            'address' => 'Main St',
            'request_comment' => 'Please note: bring passport.',
        ]);
        $branch->setRelation('provider', new Provider([
            'request_comment' => 'Provider-level note should be ignored.',
        ]));

        $offerService = Mockery::mock(GopInOfferService::class);
        $offerService->shouldReceive('resolveGopInForAppointmentMessage')->once()->andReturn(null);
        $offerService->shouldReceive('resolveCostAndTotalForBranch')->once()->andReturn([null, null]);

        $message = (new AppointmentRequestMessageFormatter($offerService))->format($file, $branch);

        $this->assertStringContainsString("Requested GOP: N/A\n\nPlease note: bring passport.\n\nPlease let us know", $message);
        $this->assertStringNotContainsString('Provider-level note should be ignored.', $message);
    }

    public function test_it_falls_back_to_provider_request_comment(): void
    {
        $file = new File([
            'service_type_id' => 5,
            'service_date' => now()->parse('2026-07-10'),
        ]);
        $file->setRelation('serviceType', new ServiceType(['name' => 'Clinic Visit']));

        $branch = new ProviderBranch([
            'id' => 12,
            'branch_name' => 'City Clinic',
            'address' => 'Main St',
        ]);
        $branch->setRelation('provider', new Provider([
            'request_comment' => 'Ask for reception desk B.',
        ]));

        $offerService = Mockery::mock(GopInOfferService::class);
        $offerService->shouldReceive('resolveGopInForAppointmentMessage')->once()->andReturn(null);
        $offerService->shouldReceive('resolveCostAndTotalForBranch')->once()->andReturn([null, null]);

        $message = (new AppointmentRequestMessageFormatter($offerService))->format($file, $branch);

        $this->assertStringContainsString('Ask for reception desk B.', $message);
    }
}

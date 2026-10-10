<?php

namespace Tests\Unit;

use App\Models\Lead;
use PHPUnit\Framework\TestCase;

class LeadPipelineProgressTest extends TestCase
{
    public function test_pipeline_step_index_follows_outreach_order(): void
    {
        $this->assertSame(0, Lead::pipelineStepIndex('Introduction'));
        $this->assertSame(1, Lead::pipelineStepIndex('Introduction Sent'));
        $this->assertGreaterThan(
            Lead::pipelineStepIndex('Presentation'),
            Lead::pipelineStepIndex('Contract Sent'),
        );
        $this->assertSame(count(Lead::PIPELINE_STEPS) - 1, Lead::pipelineStepIndex('Partner'));
        $this->assertSame(-1, Lead::pipelineStepIndex('Error'));
    }

    public function test_pipeline_progress_percent_scales_to_partner(): void
    {
        $intro = new Lead(['status' => 'Introduction']);
        $partner = new Lead(['status' => 'Partner']);
        $unknown = new Lead(['status' => 'Rejected']);

        $this->assertSame(0, $intro->pipelineProgressPercent());
        $this->assertSame(100, $partner->pipelineProgressPercent());
        $this->assertSame(0, $unknown->pipelineProgressPercent());
    }

    public function test_linkedin_url_adds_https_when_missing(): void
    {
        $lead = new Lead(['linked_in' => 'linkedin.com/in/example']);

        $this->assertSame('https://linkedin.com/in/example', $lead->linkedInUrl());

        $withScheme = new Lead(['linked_in' => 'https://www.linkedin.com/in/example']);
        $this->assertSame('https://www.linkedin.com/in/example', $withScheme->linkedInUrl());

        $empty = new Lead(['linked_in' => '']);
        $this->assertNull($empty->linkedInUrl());
    }
}

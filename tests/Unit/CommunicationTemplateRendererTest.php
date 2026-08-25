<?php

namespace Tests\Unit;

use App\Enums\CommunicationContextType;
use App\Models\File;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Services\Communications\CommunicationTemplateRenderer;
use App\Support\Communications\CommunicationContext;
use Tests\TestCase;

class CommunicationTemplateRendererTest extends TestCase
{
    public function test_renders_dot_notation_placeholders(): void
    {
        $provider = new Provider(['name' => 'Dr. Ahmed Clinic']);
        $branch = new ProviderBranch(['branch_name' => 'Dublin Branch']);
        $patient = new Patient(['name' => 'John Doe']);
        $file = new File(['mga_reference' => 'MGA-1001', 'client_reference' => 'AXA-55']);

        $context = new CommunicationContext(
            contextType: CommunicationContextType::MissingBills,
            provider: $provider,
            providerBranch: $branch,
            case: $file,
            patient: $patient,
            cases: [$file],
            extra: ['cases_table' => '- MGA-1001'],
        );

        $renderer = new CommunicationTemplateRenderer;
        $output = $renderer->render(
            'Dear {{ provider.name }}, case {{ case.reference }} / {{ case.client_reference }} for {{ patient.name }}:\n{{ cases_table }}',
            $context,
        );

        $this->assertStringContainsString('Dr. Ahmed Clinic', $output);
        $this->assertStringContainsString('MGA-1001', $output);
        $this->assertStringContainsString('AXA-55', $output);
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('- MGA-1001', $output);
    }

    public function test_does_not_execute_arbitrary_template_logic(): void
    {
        $context = new CommunicationContext(
            contextType: CommunicationContextType::MissingBills,
            provider: new Provider(['name' => 'Safe Name']),
        );

        $renderer = new CommunicationTemplateRenderer;
        $output = $renderer->render('Name: {{ provider.name }} {{ phpinfo() }}', $context);

        $this->assertSame('Name: Safe Name {{ phpinfo() }}', $output);
    }
}

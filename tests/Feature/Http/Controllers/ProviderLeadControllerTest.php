<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\ProviderLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\ProviderLeadController
 */
final class ProviderLeadControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_displays_view(): void
    {
        $providerLeads = ProviderLead::factory()->count(3)->create();

        $response = $this->get(route('provider-leads.index'));

        $response->assertOk();
        $response->assertViewIs('providerLead.index');
        $response->assertViewHas('providerLeads');
    }


    #[Test]
    public function create_displays_view(): void
    {
        $response = $this->get(route('provider-leads.create'));

        $response->assertOk();
        $response->assertViewIs('providerLead.create');
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ProviderLeadController::class,
            'store',
            \App\Http\Requests\ProviderLeadStoreRequest::class
        );
    }

    #[Test]
    public function store_saves_and_redirects(): void
    {
        $city = fake()->city();
        $service_types = fake()->word();
        $type = fake()->randomElement(/** enum_attributes **/);
        $name = fake()->name();
        $communication_method = fake()->word();
        $status = fake()->randomElement(/** enum_attributes **/);

        $response = $this->post(route('provider-leads.store'), [
            'city' => $city,
            'service_types' => $service_types,
            'type' => $type,
            'name' => $name,
            'communication_method' => $communication_method,
            'status' => $status,
        ]);

        $providerLeads = ProviderLead::query()
            ->where('city', $city)
            ->where('service_types', $service_types)
            ->where('type', $type)
            ->where('name', $name)
            ->where('communication_method', $communication_method)
            ->where('status', $status)
            ->get();
        $this->assertCount(1, $providerLeads);
        $providerLead = $providerLeads->first();

        $response->assertRedirect(route('providerLeads.index'));
        $response->assertSessionHas('providerLead.id', $providerLead->id);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $providerLead = ProviderLead::factory()->create();

        $response = $this->get(route('provider-leads.show', $providerLead));

        $response->assertOk();
        $response->assertViewIs('providerLead.show');
        $response->assertViewHas('providerLead');
    }


    #[Test]
    public function edit_displays_view(): void
    {
        $providerLead = ProviderLead::factory()->create();

        $response = $this->get(route('provider-leads.edit', $providerLead));

        $response->assertOk();
        $response->assertViewIs('providerLead.edit');
        $response->assertViewHas('providerLead');
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ProviderLeadController::class,
            'update',
            \App\Http\Requests\ProviderLeadUpdateRequest::class
        );
    }

    #[Test]
    public function update_redirects(): void
    {
        $providerLead = ProviderLead::factory()->create();
        $city = fake()->city();
        $service_types = fake()->word();
        $type = fake()->randomElement(/** enum_attributes **/);
        $name = fake()->name();
        $communication_method = fake()->word();
        $status = fake()->randomElement(/** enum_attributes **/);

        $response = $this->put(route('provider-leads.update', $providerLead), [
            'city' => $city,
            'service_types' => $service_types,
            'type' => $type,
            'name' => $name,
            'communication_method' => $communication_method,
            'status' => $status,
        ]);

        $providerLead->refresh();

        $response->assertRedirect(route('providerLeads.index'));
        $response->assertSessionHas('providerLead.id', $providerLead->id);

        $this->assertEquals($city, $providerLead->city);
        $this->assertEquals($service_types, $providerLead->service_types);
        $this->assertEquals($type, $providerLead->type);
        $this->assertEquals($name, $providerLead->name);
        $this->assertEquals($communication_method, $providerLead->communication_method);
        $this->assertEquals($status, $providerLead->status);
    }


    #[Test]
    public function destroy_deletes_and_redirects(): void
    {
        $providerLead = ProviderLead::factory()->create();

        $response = $this->delete(route('provider-leads.destroy', $providerLead));

        $response->assertRedirect(route('providerLeads.index'));

        $this->assertModelMissing($providerLead);
    }
}

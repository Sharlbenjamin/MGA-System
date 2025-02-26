<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\ProviderBranchController
 */
final class ProviderBranchControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_displays_view(): void
    {
        $providerBranches = ProviderBranch::factory()->count(3)->create();

        $response = $this->get(route('provider-branches.index'));

        $response->assertOk();
        $response->assertViewIs('providerBranch.index');
        $response->assertViewHas('providerBranches');
    }


    #[Test]
    public function create_displays_view(): void
    {
        $response = $this->get(route('provider-branches.create'));

        $response->assertOk();
        $response->assertViewIs('providerBranch.create');
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ProviderBranchController::class,
            'store',
            \App\Http\Requests\ProviderBranchStoreRequest::class
        );
    }

    #[Test]
    public function store_saves_and_redirects(): void
    {
        $provider = Provider::factory()->create();
        $city = fake()->city();
        $status = fake()->randomElement(/** enum_attributes **/);
        $priority = fake()->numberBetween(-10000, 10000);
        $service_type = ServiceType::factory()->create();
        $communication_method = fake()->word();
        $day_cost = fake()->randomFloat(/** decimal_attributes **/);
        $night_cost = fake()->randomFloat(/** decimal_attributes **/);
        $weekend_cost = fake()->randomFloat(/** decimal_attributes **/);
        $weekend_night_cost = fake()->randomFloat(/** decimal_attributes **/);

        $response = $this->post(route('provider-branches.store'), [
            'provider_id' => $provider->id,
            'city' => $city,
            'status' => $status,
            'priority' => $priority,
            'service_type_id' => $service_type->id,
            'communication_method' => $communication_method,
            'day_cost' => $day_cost,
            'night_cost' => $night_cost,
            'weekend_cost' => $weekend_cost,
            'weekend_night_cost' => $weekend_night_cost,
        ]);

        $providerBranches = ProviderBranch::query()
            ->where('provider_id', $provider->id)
            ->where('city', $city)
            ->where('status', $status)
            ->where('priority', $priority)
            ->where('service_type_id', $service_type->id)
            ->where('communication_method', $communication_method)
            ->where('day_cost', $day_cost)
            ->where('night_cost', $night_cost)
            ->where('weekend_cost', $weekend_cost)
            ->where('weekend_night_cost', $weekend_night_cost)
            ->get();
        $this->assertCount(1, $providerBranches);
        $providerBranch = $providerBranches->first();

        $response->assertRedirect(route('providerBranches.index'));
        $response->assertSessionHas('providerBranch.id', $providerBranch->id);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $providerBranch = ProviderBranch::factory()->create();

        $response = $this->get(route('provider-branches.show', $providerBranch));

        $response->assertOk();
        $response->assertViewIs('providerBranch.show');
        $response->assertViewHas('providerBranch');
    }


    #[Test]
    public function edit_displays_view(): void
    {
        $providerBranch = ProviderBranch::factory()->create();

        $response = $this->get(route('provider-branches.edit', $providerBranch));

        $response->assertOk();
        $response->assertViewIs('providerBranch.edit');
        $response->assertViewHas('providerBranch');
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ProviderBranchController::class,
            'update',
            \App\Http\Requests\ProviderBranchUpdateRequest::class
        );
    }

    #[Test]
    public function update_redirects(): void
    {
        $providerBranch = ProviderBranch::factory()->create();
        $provider = Provider::factory()->create();
        $city = fake()->city();
        $status = fake()->randomElement(/** enum_attributes **/);
        $priority = fake()->numberBetween(-10000, 10000);
        $service_type = ServiceType::factory()->create();
        $communication_method = fake()->word();
        $day_cost = fake()->randomFloat(/** decimal_attributes **/);
        $night_cost = fake()->randomFloat(/** decimal_attributes **/);
        $weekend_cost = fake()->randomFloat(/** decimal_attributes **/);
        $weekend_night_cost = fake()->randomFloat(/** decimal_attributes **/);

        $response = $this->put(route('provider-branches.update', $providerBranch), [
            'provider_id' => $provider->id,
            'city' => $city,
            'status' => $status,
            'priority' => $priority,
            'service_type_id' => $service_type->id,
            'communication_method' => $communication_method,
            'day_cost' => $day_cost,
            'night_cost' => $night_cost,
            'weekend_cost' => $weekend_cost,
            'weekend_night_cost' => $weekend_night_cost,
        ]);

        $providerBranch->refresh();

        $response->assertRedirect(route('providerBranches.index'));
        $response->assertSessionHas('providerBranch.id', $providerBranch->id);

        $this->assertEquals($provider->id, $providerBranch->provider_id);
        $this->assertEquals($city, $providerBranch->city);
        $this->assertEquals($status, $providerBranch->status);
        $this->assertEquals($priority, $providerBranch->priority);
        $this->assertEquals($service_type->id, $providerBranch->service_type_id);
        $this->assertEquals($communication_method, $providerBranch->communication_method);
        $this->assertEquals($day_cost, $providerBranch->day_cost);
        $this->assertEquals($night_cost, $providerBranch->night_cost);
        $this->assertEquals($weekend_cost, $providerBranch->weekend_cost);
        $this->assertEquals($weekend_night_cost, $providerBranch->weekend_night_cost);
    }


    #[Test]
    public function destroy_deletes_and_redirects(): void
    {
        $providerBranch = ProviderBranch::factory()->create();

        $response = $this->delete(route('provider-branches.destroy', $providerBranch));

        $response->assertRedirect(route('providerBranches.index'));

        $this->assertModelMissing($providerBranch);
    }
}

<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\ProviderController
 */
final class ProviderControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_displays_view(): void
    {
        $providers = Provider::factory()->count(3)->create();

        $response = $this->get(route('providers.index'));

        $response->assertOk();
        $response->assertViewIs('provider.index');
        $response->assertViewHas('providers');
    }


    #[Test]
    public function create_displays_view(): void
    {
        $response = $this->get(route('providers.create'));

        $response->assertOk();
        $response->assertViewIs('provider.create');
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ProviderController::class,
            'store',
            \App\Http\Requests\ProviderStoreRequest::class
        );
    }

    #[Test]
    public function store_saves_and_redirects(): void
    {
        $country = fake()->country();
        $status = fake()->randomElement(/** enum_attributes **/);
        $type = fake()->randomElement(/** enum_attributes **/);
        $name = fake()->name();

        $response = $this->post(route('providers.store'), [
            'country' => $country,
            'status' => $status,
            'type' => $type,
            'name' => $name,
        ]);

        $providers = Provider::query()
            ->where('country', $country)
            ->where('status', $status)
            ->where('type', $type)
            ->where('name', $name)
            ->get();
        $this->assertCount(1, $providers);
        $provider = $providers->first();

        $response->assertRedirect(route('providers.index'));
        $response->assertSessionHas('provider.id', $provider->id);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $provider = Provider::factory()->create();

        $response = $this->get(route('providers.show', $provider));

        $response->assertOk();
        $response->assertViewIs('provider.show');
        $response->assertViewHas('provider');
    }


    #[Test]
    public function edit_displays_view(): void
    {
        $provider = Provider::factory()->create();

        $response = $this->get(route('providers.edit', $provider));

        $response->assertOk();
        $response->assertViewIs('provider.edit');
        $response->assertViewHas('provider');
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ProviderController::class,
            'update',
            \App\Http\Requests\ProviderUpdateRequest::class
        );
    }

    #[Test]
    public function update_redirects(): void
    {
        $provider = Provider::factory()->create();
        $country = fake()->country();
        $status = fake()->randomElement(/** enum_attributes **/);
        $type = fake()->randomElement(/** enum_attributes **/);
        $name = fake()->name();

        $response = $this->put(route('providers.update', $provider), [
            'country' => $country,
            'status' => $status,
            'type' => $type,
            'name' => $name,
        ]);

        $provider->refresh();

        $response->assertRedirect(route('providers.index'));
        $response->assertSessionHas('provider.id', $provider->id);

        $this->assertEquals($country, $provider->country);
        $this->assertEquals($status, $provider->status);
        $this->assertEquals($type, $provider->type);
        $this->assertEquals($name, $provider->name);
    }


    #[Test]
    public function destroy_deletes_and_redirects(): void
    {
        $provider = Provider::factory()->create();

        $response = $this->delete(route('providers.destroy', $provider));

        $response->assertRedirect(route('providers.index'));

        $this->assertModelMissing($provider);
    }
}

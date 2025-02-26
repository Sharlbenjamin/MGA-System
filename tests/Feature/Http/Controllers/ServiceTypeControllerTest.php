<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\ServiceTypeController
 */
final class ServiceTypeControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_displays_view(): void
    {
        $serviceTypes = ServiceType::factory()->count(3)->create();

        $response = $this->get(route('service-types.index'));

        $response->assertOk();
        $response->assertViewIs('serviceType.index');
        $response->assertViewHas('serviceTypes');
    }


    #[Test]
    public function create_displays_view(): void
    {
        $response = $this->get(route('service-types.create'));

        $response->assertOk();
        $response->assertViewIs('serviceType.create');
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ServiceTypeController::class,
            'store',
            \App\Http\Requests\ServiceTypeStoreRequest::class
        );
    }

    #[Test]
    public function store_saves_and_redirects(): void
    {
        $name = fake()->name();

        $response = $this->post(route('service-types.store'), [
            'name' => $name,
        ]);

        $serviceTypes = ServiceType::query()
            ->where('name', $name)
            ->get();
        $this->assertCount(1, $serviceTypes);
        $serviceType = $serviceTypes->first();

        $response->assertRedirect(route('serviceTypes.index'));
        $response->assertSessionHas('serviceType.id', $serviceType->id);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $serviceType = ServiceType::factory()->create();

        $response = $this->get(route('service-types.show', $serviceType));

        $response->assertOk();
        $response->assertViewIs('serviceType.show');
        $response->assertViewHas('serviceType');
    }


    #[Test]
    public function edit_displays_view(): void
    {
        $serviceType = ServiceType::factory()->create();

        $response = $this->get(route('service-types.edit', $serviceType));

        $response->assertOk();
        $response->assertViewIs('serviceType.edit');
        $response->assertViewHas('serviceType');
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ServiceTypeController::class,
            'update',
            \App\Http\Requests\ServiceTypeUpdateRequest::class
        );
    }

    #[Test]
    public function update_redirects(): void
    {
        $serviceType = ServiceType::factory()->create();
        $name = fake()->name();

        $response = $this->put(route('service-types.update', $serviceType), [
            'name' => $name,
        ]);

        $serviceType->refresh();

        $response->assertRedirect(route('serviceTypes.index'));
        $response->assertSessionHas('serviceType.id', $serviceType->id);

        $this->assertEquals($name, $serviceType->name);
    }


    #[Test]
    public function destroy_deletes_and_redirects(): void
    {
        $serviceType = ServiceType::factory()->create();

        $response = $this->delete(route('service-types.destroy', $serviceType));

        $response->assertRedirect(route('serviceTypes.index'));

        $this->assertModelMissing($serviceType);
    }
}

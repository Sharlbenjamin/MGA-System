<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\City;
use App\Models\Contact;
use App\Models\Contactable;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\ContactController
 */
final class ContactControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_displays_view(): void
    {
        $contacts = Contact::factory()->count(3)->create();

        $response = $this->get(route('contacts.index'));

        $response->assertOk();
        $response->assertViewIs('contact.index');
        $response->assertViewHas('contacts');
    }


    #[Test]
    public function create_displays_view(): void
    {
        $response = $this->get(route('contacts.create'));

        $response->assertOk();
        $response->assertViewIs('contact.create');
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ContactController::class,
            'store',
            \App\Http\Requests\ContactStoreRequest::class
        );
    }

    #[Test]
    public function store_saves_and_redirects(): void
    {
        $contactable = Contactable::factory()->create();
        $contactable_type = fake()->word();
        $type = fake()->randomElement(/** enum_attributes **/);
        $name = fake()->name();
        $country = Country::factory()->create();
        $city = City::factory()->create();
        $preferred_contact = fake()->randomElement(/** enum_attributes **/);
        $status = fake()->randomElement(/** enum_attributes **/);

        $response = $this->post(route('contacts.store'), [
            'contactable_id' => $contactable->id,
            'contactable_type' => $contactable_type,
            'type' => $type,
            'name' => $name,
            'country_id' => $country->id,
            'city_id' => $city->id,
            'preferred_contact' => $preferred_contact,
            'status' => $status,
        ]);

        $contacts = Contact::query()
            ->where('contactable_id', $contactable->id)
            ->where('contactable_type', $contactable_type)
            ->where('type', $type)
            ->where('name', $name)
            ->where('country_id', $country->id)
            ->where('city_id', $city->id)
            ->where('preferred_contact', $preferred_contact)
            ->where('status', $status)
            ->get();
        $this->assertCount(1, $contacts);
        $contact = $contacts->first();

        $response->assertRedirect(route('contacts.index'));
        $response->assertSessionHas('contact.id', $contact->id);
    }


    #[Test]
    public function show_displays_view(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->get(route('contacts.show', $contact));

        $response->assertOk();
        $response->assertViewIs('contact.show');
        $response->assertViewHas('contact');
    }


    #[Test]
    public function edit_displays_view(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->get(route('contacts.edit', $contact));

        $response->assertOk();
        $response->assertViewIs('contact.edit');
        $response->assertViewHas('contact');
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ContactController::class,
            'update',
            \App\Http\Requests\ContactUpdateRequest::class
        );
    }

    #[Test]
    public function update_redirects(): void
    {
        $contact = Contact::factory()->create();
        $contactable = Contactable::factory()->create();
        $contactable_type = fake()->word();
        $type = fake()->randomElement(/** enum_attributes **/);
        $name = fake()->name();
        $country = Country::factory()->create();
        $city = City::factory()->create();
        $preferred_contact = fake()->randomElement(/** enum_attributes **/);
        $status = fake()->randomElement(/** enum_attributes **/);

        $response = $this->put(route('contacts.update', $contact), [
            'contactable_id' => $contactable->id,
            'contactable_type' => $contactable_type,
            'type' => $type,
            'name' => $name,
            'country_id' => $country->id,
            'city_id' => $city->id,
            'preferred_contact' => $preferred_contact,
            'status' => $status,
        ]);

        $contact->refresh();

        $response->assertRedirect(route('contacts.index'));
        $response->assertSessionHas('contact.id', $contact->id);

        $this->assertEquals($contactable->id, $contact->contactable_id);
        $this->assertEquals($contactable_type, $contact->contactable_type);
        $this->assertEquals($type, $contact->type);
        $this->assertEquals($name, $contact->name);
        $this->assertEquals($country->id, $contact->country_id);
        $this->assertEquals($city->id, $contact->city_id);
        $this->assertEquals($preferred_contact, $contact->preferred_contact);
        $this->assertEquals($status, $contact->status);
    }


    #[Test]
    public function destroy_deletes_and_redirects(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->delete(route('contacts.destroy', $contact));

        $response->assertRedirect(route('contacts.index'));

        $this->assertModelMissing($contact);
    }
}

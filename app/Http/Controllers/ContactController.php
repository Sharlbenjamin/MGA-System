<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactStoreRequest;
use App\Http\Requests\ContactUpdateRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $contacts = Contact::all();

        return view('contact.index', [
            'contacts' => $contacts,
        ]);
    }

    public function create(Request $request): View
    {
        return view('contact.create');
    }

    public function store(ContactStoreRequest $request): RedirectResponse
    {
        $contact = Contact::create($request->validated());

        $request->session()->flash('contact.id', $contact->id);

        return redirect()->route('contacts.index');
    }

    public function show(Request $request, Contact $contact): View
    {
        return view('contact.show', [
            'contact' => $contact,
        ]);
    }

    public function edit(Request $request, Contact $contact): View
    {
        return view('contact.edit', [
            'contact' => $contact,
        ]);
    }

    public function update(ContactUpdateRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        $request->session()->flash('contact.id', $contact->id);

        return redirect()->route('contacts.index');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()->route('contacts.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactStoreRequest;
use App\Http\Requests\ContactUpdateRequest;
use App\Models\Contact;
use App\Services\NotificationService;
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

        // Send notification to the current user
        NotificationService::success(
            auth()->user(),
            'Contact Created',
            "Contact '{$contact->title}' has been created successfully.",
            route('contacts.show', $contact),
            'View Contact'
        );

        // Notify admins about new contact
        NotificationService::notifyAdmins(
            'New Contact Added',
            "A new contact '{$contact->title}' has been added by " . auth()->user()->name,
            'info',
            route('contacts.show', $contact),
            'View Contact'
        );

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

        // Send notification about contact update
        NotificationService::info(
            auth()->user(),
            'Contact Updated',
            "Contact '{$contact->title}' has been updated successfully.",
            route('contacts.show', $contact),
            'View Contact'
        );

        $request->session()->flash('contact.id', $contact->id);

        return redirect()->route('contacts.index');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        $contactName = $contact->title;
        $contact->delete();

        // Send notification about contact deletion
        NotificationService::warning(
            auth()->user(),
            'Contact Deleted',
            "Contact '{$contactName}' has been deleted.",
            route('contacts.index'),
            'View All Contacts'
        );

        // Notify admins about contact deletion
        NotificationService::notifyAdmins(
            'Contact Deleted',
            "Contact '{$contactName}' has been deleted by " . auth()->user()->name,
            'warning'
        );

        return redirect()->route('contacts.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadStoreRequest;
use App\Http\Requests\LeadUpdateRequest;
use App\Models\Lead;
use App\Models\Client;
use App\Models\Country;
use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $leads = Lead::with(['client'])->get();

        return view('lead.index', [
            'leads' => $leads,
        ]);
    }

    public function create(Request $request): View
    {
        $clients = Client::all();
        $countries = Country::all();

        return view('lead.create', [
            'clients' => $clients,
            'countries' => $countries,
        ]);
    }

    public function store(LeadStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        
        // Handle client creation if needed
        if ($request->input('client_selection') === 'new') {
            $client = Client::create([
                'company_name' => $request->input('new_client_company_name'),
                'type' => $request->input('new_client_type'),
                'status' => $request->input('new_client_status'),
                'initials' => $request->input('new_client_initials'),
                'number_requests' => $request->input('new_client_number_requests', 0),
                'email' => $request->input('new_client_email'),
                'phone' => $request->input('new_client_phone'),
            ]);
            
            $data['client_id'] = $client->id;
        } else {
            $data['client_id'] = $request->input('client_id');
        }

        $lead = Lead::create($data);

        $request->session()->flash('lead.id', $lead->id);
        $request->session()->flash('success', 'Client lead created successfully!');

        return redirect()->route('leads.index');
    }

    public function show(Request $request, Lead $lead): View
    {
        return view('lead.show', [
            'lead' => $lead,
        ]);
    }

    public function edit(Request $request, Lead $lead): View
    {
        $clients = Client::all();
        $countries = Country::all();

        return view('lead.edit', [
            'lead' => $lead,
            'clients' => $clients,
            'countries' => $countries,
        ]);
    }

    public function update(LeadUpdateRequest $request, Lead $lead): RedirectResponse
    {
        $lead->update($request->validated());

        $request->session()->flash('lead.id', $lead->id);
        $request->session()->flash('success', 'Client lead updated successfully!');

        return redirect()->route('leads.index');
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        $lead->delete();

        $request->session()->flash('success', 'Client lead deleted successfully!');

        return redirect()->route('leads.index');
    }

    // API endpoints for AJAX requests
    public function checkEmail(Request $request): JsonResponse
    {
        $email = $request->input('email');
        $type = $request->input('type', 'lead');
        
        if ($type === 'client') {
            $exists = Client::where('email', $email)->exists();
        } else {
            $exists = Lead::where('email', $email)->exists();
        }
        
        return response()->json(['exists' => $exists]);
    }
} 
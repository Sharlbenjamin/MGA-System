<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderLeadStoreRequest;
use App\Http\Requests\ProviderLeadUpdateRequest;
use App\Models\ProviderLead;
use App\Models\Provider;
use App\Models\Country;
use App\Models\City;
use App\Models\ServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class ProviderLeadController extends Controller
{
    public function index(Request $request): View
    {
        $providerLeads = ProviderLead::with(['provider', 'city'])->get();

        return view('providerLead.index', [
            'providerLeads' => $providerLeads,
        ]);
    }

    public function create(Request $request): View
    {
        $providers = Provider::all();
        $countries = Country::all();
        $serviceTypes = ServiceType::all();

        return view('providerLead.create', [
            'providers' => $providers,
            'countries' => $countries,
            'serviceTypes' => $serviceTypes,
        ]);
    }

    public function store(ProviderLeadStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        
        // Handle provider creation if needed
        if ($request->input('provider_selection') === 'new') {
            $provider = Provider::create([
                'name' => $request->input('new_provider_name'),
                'type' => $request->input('new_provider_type'),
                'country_id' => $request->input('new_provider_country'),
                'status' => $request->input('new_provider_status'),
                'email' => $request->input('new_provider_email'),
                'phone' => $request->input('new_provider_phone'),
                'payment_due' => $request->input('new_provider_payment_due'),
                'payment_method' => $request->input('new_provider_payment_method'),
                'comment' => $request->input('new_provider_comment'),
            ]);
            
            $data['provider_id'] = $provider->id;
        } else {
            $data['provider_id'] = $request->input('provider_id');
        }

        // Handle service types
        if (is_array($data['service_types'])) {
            $data['service_types'] = implode(',', $data['service_types']);
        }

        $providerLead = ProviderLead::create($data);

        $request->session()->flash('providerLead.id', $providerLead->id);
        $request->session()->flash('success', 'Provider lead created successfully!');

        return redirect()->route('provider-leads.index');
    }

    public function show(Request $request, ProviderLead $providerLead): View
    {
        return view('providerLead.show', [
            'providerLead' => $providerLead,
        ]);
    }

    public function edit(Request $request, ProviderLead $providerLead): View
    {
        $providers = Provider::all();
        $countries = Country::all();
        $serviceTypes = ServiceType::all();

        return view('providerLead.edit', [
            'providerLead' => $providerLead,
            'providers' => $providers,
            'countries' => $countries,
            'serviceTypes' => $serviceTypes,
        ]);
    }

    public function update(ProviderLeadUpdateRequest $request, ProviderLead $providerLead): RedirectResponse
    {
        $data = $request->validated();
        
        // Handle service types
        if (is_array($data['service_types'])) {
            $data['service_types'] = implode(',', $data['service_types']);
        }

        $providerLead->update($data);

        $request->session()->flash('providerLead.id', $providerLead->id);
        $request->session()->flash('success', 'Provider lead updated successfully!');

        return redirect()->route('provider-leads.index');
    }

    public function destroy(Request $request, ProviderLead $providerLead): RedirectResponse
    {
        $providerLead->delete();

        $request->session()->flash('success', 'Provider lead deleted successfully!');

        return redirect()->route('provider-leads.index');
    }

    // API endpoints for AJAX requests
    public function getCities(Request $request, $countryId): JsonResponse
    {
        $cities = City::where('country_id', $countryId)->get(['id', 'name']);
        return response()->json($cities);
    }

    public function checkEmail(Request $request): JsonResponse
    {
        $email = $request->input('email');
        $type = $request->input('type', 'provider');
        
        if ($type === 'provider') {
            $exists = Provider::where('email', $email)->exists();
        } else {
            $exists = ProviderLead::where('email', $email)->exists();
        }
        
        return response()->json(['exists' => $exists]);
    }
}

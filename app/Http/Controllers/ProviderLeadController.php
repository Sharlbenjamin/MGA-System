<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderLeadStoreRequest;
use App\Http\Requests\ProviderLeadUpdateRequest;
use App\Models\ProviderLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderLeadController extends Controller
{
    public function index(Request $request): View
    {
        $providerLeads = ProviderLead::all();

        return view('providerLead.index', [
            'providerLeads' => $providerLeads,
        ]);
    }

    public function create(Request $request): View
    {
        return view('providerLead.create');
    }

    public function store(ProviderLeadStoreRequest $request): RedirectResponse
    {
        $providerLead = ProviderLead::create($request->validated());

        $request->session()->flash('providerLead.id', $providerLead->id);

        return redirect()->route('providerLeads.index');
    }

    public function show(Request $request, ProviderLead $providerLead): View
    {
        return view('providerLead.show', [
            'providerLead' => $providerLead,
        ]);
    }

    public function edit(Request $request, ProviderLead $providerLead): View
    {
        return view('providerLead.edit', [
            'providerLead' => $providerLead,
        ]);
    }

    public function update(ProviderLeadUpdateRequest $request, ProviderLead $providerLead): RedirectResponse
    {
        $providerLead->update($request->validated());

        $request->session()->flash('providerLead.id', $providerLead->id);

        return redirect()->route('providerLeads.index');
    }

    public function destroy(Request $request, ProviderLead $providerLead): RedirectResponse
    {
        $providerLead->delete();

        return redirect()->route('providerLeads.index');
    }
}

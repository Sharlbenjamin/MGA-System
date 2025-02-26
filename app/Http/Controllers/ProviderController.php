<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderStoreRequest;
use App\Http\Requests\ProviderUpdateRequest;
use App\Models\Provider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function index(Request $request): View
    {
        $providers = Provider::all();

        return view('provider.index', [
            'providers' => $providers,
        ]);
    }

    public function create(Request $request): View
    {
        return view('provider.create');
    }

    public function store(ProviderStoreRequest $request): RedirectResponse
    {
        $provider = Provider::create($request->validated());

        $request->session()->flash('provider.id', $provider->id);

        return redirect()->route('providers.index');
    }

    public function show(Request $request, Provider $provider): View
    {
        return view('provider.show', [
            'provider' => $provider,
        ]);
    }

    public function edit(Request $request, Provider $provider): View
    {
        return view('provider.edit', [
            'provider' => $provider,
        ]);
    }

    public function update(ProviderUpdateRequest $request, Provider $provider): RedirectResponse
    {
        $provider->update($request->validated());

        $request->session()->flash('provider.id', $provider->id);

        return redirect()->route('providers.index');
    }

    public function destroy(Request $request, Provider $provider): RedirectResponse
    {
        $provider->delete();

        return redirect()->route('providers.index');
    }
}

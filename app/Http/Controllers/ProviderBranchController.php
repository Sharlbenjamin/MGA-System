<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderBranchStoreRequest;
use App\Http\Requests\ProviderBranchUpdateRequest;
use App\Models\ProviderBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderBranchController extends Controller
{
    public function index(Request $request): View
    {
        $providerBranches = ProviderBranch::all();

        return view('providerBranch.index', [
            'providerBranches' => $providerBranches,
        ]);
    }

    public function create(Request $request): View
    {
        return view('providerBranch.create');
    }

    public function store(ProviderBranchStoreRequest $request): RedirectResponse
    {
        $providerBranch = ProviderBranch::create($request->validated());

        $request->session()->flash('providerBranch.id', $providerBranch->id);

        return redirect()->route('providerBranches.index');
    }

    public function show(Request $request, ProviderBranch $providerBranch): View
    {
        return view('providerBranch.show', [
            'providerBranch' => $providerBranch,
        ]);
    }

    public function edit(Request $request, ProviderBranch $providerBranch): View
    {
        return view('providerBranch.edit', [
            'providerBranch' => $providerBranch,
        ]);
    }

    public function update(ProviderBranchUpdateRequest $request, ProviderBranch $providerBranch): RedirectResponse
    {
        $providerBranch->update($request->validated());

        $request->session()->flash('providerBranch.id', $providerBranch->id);

        return redirect()->route('providerBranches.index');
    }

    public function destroy(Request $request, ProviderBranch $providerBranch): RedirectResponse
    {
        $providerBranch->delete();

        return redirect()->route('providerBranches.index');
    }
}

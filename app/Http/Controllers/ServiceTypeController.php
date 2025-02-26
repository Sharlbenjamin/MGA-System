<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceTypeStoreRequest;
use App\Http\Requests\ServiceTypeUpdateRequest;
use App\Models\ServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceTypeController extends Controller
{
    public function index(Request $request): View
    {
        $serviceTypes = ServiceType::all();

        return view('serviceType.index', [
            'serviceTypes' => $serviceTypes,
        ]);
    }

    public function create(Request $request): View
    {
        return view('serviceType.create');
    }

    public function store(ServiceTypeStoreRequest $request): RedirectResponse
    {
        $serviceType = ServiceType::create($request->validated());

        $request->session()->flash('serviceType.id', $serviceType->id);

        return redirect()->route('serviceTypes.index');
    }

    public function show(Request $request, ServiceType $serviceType): View
    {
        return view('serviceType.show', [
            'serviceType' => $serviceType,
        ]);
    }

    public function edit(Request $request, ServiceType $serviceType): View
    {
        return view('serviceType.edit', [
            'serviceType' => $serviceType,
        ]);
    }

    public function update(ServiceTypeUpdateRequest $request, ServiceType $serviceType): RedirectResponse
    {
        $serviceType->update($request->validated());

        $request->session()->flash('serviceType.id', $serviceType->id);

        return redirect()->route('serviceTypes.index');
    }

    public function destroy(Request $request, ServiceType $serviceType): RedirectResponse
    {
        $serviceType->delete();

        return redirect()->route('serviceTypes.index');
    }
}

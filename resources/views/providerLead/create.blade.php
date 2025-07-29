@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Create Provider Lead</h2>

            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('provider-leads.store') }}" method="POST" id="providerLeadForm">
                @csrf
                
                <!-- Parent Provider Selection -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700">Provider Information</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Provider Selection
                        </label>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="radio" name="provider_selection" value="existing" class="mr-2" checked>
                                <span>Select Existing Provider</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="provider_selection" value="new" class="mr-2">
                                <span>Create New Provider</span>
                            </label>
                        </div>
                    </div>

                    <!-- Existing Provider Selection -->
                    <div id="existingProviderSection" class="space-y-4">
                        <div>
                            <label for="provider_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Select Provider *
                            </label>
                            <select name="provider_id" id="provider_id" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select a provider...</option>
                                @foreach($providers as $provider)
                                    <option value="{{ $provider->id }}" data-country="{{ $provider->country_id }}">
                                        {{ $provider->name }} ({{ $provider->type }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- New Provider Creation -->
                    <div id="newProviderSection" class="space-y-4 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="new_provider_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Provider Name *
                                </label>
                                <input type="text" name="new_provider_name" id="new_provider_name" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Enter provider name">
                            </div>
                            <div>
                                <label for="new_provider_type" class="block text-sm font-medium text-gray-700 mb-2">
                                    Provider Type *
                                </label>
                                <select name="new_provider_type" id="new_provider_type" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select type...</option>
                                    <option value="Doctor">Doctor</option>
                                    <option value="Hospital">Hospital</option>
                                    <option value="Clinic">Clinic</option>
                                    <option value="Dental">Dental</option>
                                    <option value="Agency">Agency</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="new_provider_country" class="block text-sm font-medium text-gray-700 mb-2">
                                    Country *
                                </label>
                                <select name="new_provider_country" id="new_provider_country" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select country...</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="new_provider_status" class="block text-sm font-medium text-gray-700 mb-2">
                                    Status *
                                </label>
                                <select name="new_provider_status" id="new_provider_status" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select status...</option>
                                    <option value="Active">Active</option>
                                    <option value="Hold">Hold</option>
                                    <option value="Potential">Potential</option>
                                    <option value="Black list">Black List</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="new_provider_email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Provider Email
                                </label>
                                <input type="email" name="new_provider_email" id="new_provider_email" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="provider@example.com">
                            </div>
                            <div>
                                <label for="new_provider_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Provider Phone
                                </label>
                                <input type="tel" name="new_provider_phone" id="new_provider_phone" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="+1234567890">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="new_provider_payment_due" class="block text-sm font-medium text-gray-700 mb-2">
                                    Payment Due (Days)
                                </label>
                                <input type="number" name="new_provider_payment_due" id="new_provider_payment_due" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="30">
                            </div>
                            <div>
                                <label for="new_provider_payment_method" class="block text-sm font-medium text-gray-700 mb-2">
                                    Payment Method
                                </label>
                                <select name="new_provider_payment_method" id="new_provider_payment_method" 
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select method...</option>
                                    <option value="Online Link">Online Link</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="AEAT">AEAT</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="new_provider_comment" class="block text-sm font-medium text-gray-700 mb-2">
                                Provider Comment
                            </label>
                            <textarea name="new_provider_comment" id="new_provider_comment" rows="3"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Additional notes about the provider..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Provider Lead Information -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700">Provider Lead Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Lead Name *
                            </label>
                            <input type="text" name="name" id="name" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter lead name">
                        </div>
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                                Lead Type *
                            </label>
                            <select name="type" id="type" required
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select type...</option>
                                <option value="Doctor">Doctor</option>
                                <option value="Clinic">Clinic</option>
                                <option value="Hospital">Hospital</option>
                                <option value="Dental">Dental</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                Email
                            </label>
                            <input type="email" name="email" id="email" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="lead@example.com">
                            <div id="emailValidation" class="text-sm mt-1 hidden"></div>
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Phone
                            </label>
                            <input type="tel" name="phone" id="phone" 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="+1234567890">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label for="city_id" class="block text-sm font-medium text-gray-700 mb-2">
                                City *
                            </label>
                            <select name="city_id" id="city_id" required
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select city...</option>
                            </select>
                        </div>
                        <div>
                            <label for="communication_method" class="block text-sm font-medium text-gray-700 mb-2">
                                Communication Method *
                            </label>
                            <select name="communication_method" id="communication_method" required
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select method...</option>
                                <option value="Email">Email</option>
                                <option value="WhatsApp">WhatsApp</option>
                                <option value="Phone">Phone</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="service_types" class="block text-sm font-medium text-gray-700 mb-2">
                            Service Types *
                        </label>
                        <select name="service_types[]" id="service_types" multiple required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @foreach($serviceTypes as $serviceType)
                                <option value="{{ $serviceType->name }}">{{ $serviceType->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple service types</p>
                    </div>

                    <div class="mt-4">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Status *
                        </label>
                        <select name="status" id="status" required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select status...</option>
                            <option value="Pending information">Pending Information</option>
                            <option value="Step one">Step One</option>
                            <option value="Step one sent">Step One Sent</option>
                            <option value="Reminder">Reminder</option>
                            <option value="Reminder sent">Reminder Sent</option>
                            <option value="Discount">Discount</option>
                            <option value="Discount sent">Discount Sent</option>
                            <option value="Step two">Step Two</option>
                            <option value="Step two sent">Step Two Sent</option>
                            <option value="Presentation">Presentation</option>
                            <option value="Presentation sent">Presentation Sent</option>
                            <option value="Contract">Contract</option>
                            <option value="Contract sent">Contract Sent</option>
                        </select>
                    </div>

                    <div class="mt-4">
                        <label for="last_contact_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Last Contact Date
                        </label>
                        <input type="date" name="last_contact_date" id="last_contact_date" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="mt-4">
                        <label for="comment" class="block text-sm font-medium text-gray-700 mb-2">
                            Comment
                        </label>
                        <textarea name="comment" id="comment" rows="3"
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Additional notes about this lead..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="{{ route('provider-leads.index') }}" 
                       class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors">
                        Create Provider Lead
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const providerSelection = document.querySelectorAll('input[name="provider_selection"]');
    const existingProviderSection = document.getElementById('existingProviderSection');
    const newProviderSection = document.getElementById('newProviderSection');
    const providerSelect = document.getElementById('provider_id');
    const citySelect = document.getElementById('city_id');
    const emailInput = document.getElementById('email');
    const emailValidation = document.getElementById('emailValidation');
    const newProviderCountry = document.getElementById('new_provider_country');
    const newProviderCity = document.getElementById('city_id');

    // Handle provider selection toggle
    providerSelection.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'existing') {
                existingProviderSection.classList.remove('hidden');
                newProviderSection.classList.add('hidden');
                // Make provider_id required when existing is selected
                providerSelect.required = true;
                // Remove required from new provider fields
                document.querySelectorAll('#newProviderSection input, #newProviderSection select').forEach(field => {
                    field.required = false;
                });
            } else {
                existingProviderSection.classList.add('hidden');
                newProviderSection.classList.remove('hidden');
                // Remove required from provider_id when new is selected
                providerSelect.required = false;
                // Make new provider fields required
                document.querySelectorAll('#newProviderSection input[type="text"], #newProviderSection input[type="email"], #newProviderSection input[type="tel"], #newProviderSection select').forEach(field => {
                    if (field.id !== 'new_provider_comment' && field.id !== 'new_provider_payment_due' && field.id !== 'new_provider_payment_method') {
                        field.required = true;
                    }
                });
            }
        });
    });

    // Handle provider selection change to update cities
    providerSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const countryId = selectedOption.dataset.country;
        
        if (countryId) {
            // Fetch cities for the selected country
            fetch(`/api/cities/${countryId}`)
                .then(response => response.json())
                .then(cities => {
                    citySelect.innerHTML = '<option value="">Select city...</option>';
                    cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.id;
                        option.textContent = city.name;
                        citySelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error fetching cities:', error);
                });
        } else {
            citySelect.innerHTML = '<option value="">Select city...</option>';
        }
    });

    // Handle new provider country change
    newProviderCountry.addEventListener('change', function() {
        const countryId = this.value;
        
        if (countryId) {
            // Fetch cities for the selected country
            fetch(`/api/cities/${countryId}`)
                .then(response => response.json())
                .then(cities => {
                    citySelect.innerHTML = '<option value="">Select city...</option>';
                    cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.id;
                        option.textContent = city.name;
                        citySelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error fetching cities:', error);
                });
        } else {
            citySelect.innerHTML = '<option value="">Select city...</option>';
        }
    });

    // Email validation
    emailInput.addEventListener('blur', function() {
        const email = this.value;
        if (email) {
            // Check if email exists in providers
            fetch(`/api/check-email?email=${encodeURIComponent(email)}&type=provider`)
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        emailValidation.textContent = 'This email is already registered with a provider.';
                        emailValidation.className = 'text-sm mt-1 text-red-600';
                        emailValidation.classList.remove('hidden');
                    } else {
                        emailValidation.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error checking email:', error);
                });
        } else {
            emailValidation.classList.add('hidden');
        }
    });

    // Form validation
    document.getElementById('providerLeadForm').addEventListener('submit', function(e) {
        const selectedProviderType = document.querySelector('input[name="provider_selection"]:checked').value;
        
        if (selectedProviderType === 'existing' && !providerSelect.value) {
            e.preventDefault();
            alert('Please select an existing provider or choose to create a new one.');
            return;
        }
        
        if (selectedProviderType === 'new') {
            const requiredFields = ['new_provider_name', 'new_provider_type', 'new_provider_country', 'new_provider_status'];
            const missingFields = requiredFields.filter(field => !document.getElementById(field).value);
            
            if (missingFields.length > 0) {
                e.preventDefault();
                alert('Please fill in all required fields for the new provider.');
                return;
            }
        }
    });
});
</script>
@endsection

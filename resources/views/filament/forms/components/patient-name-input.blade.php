<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{
        similarPatients: [],
        duplicatePatient: null,
        showSuggestions: false,
        selectedIndex: -1,
        
        async searchSimilar() {
            const name = $wire.get('patient_name');
            const clientId = $wire.get('client_id');
            
            if (name.length < 2) {
                this.similarPatients = [];
                this.showSuggestions = false;
                return;
            }
            
            try {
                const response = await fetch(`/api/patients/search-similar?name=${encodeURIComponent(name)}&client_id=${clientId || ''}`);
                const data = await response.json();
                
                if (data.success) {
                    this.similarPatients = data.data;
                    this.showSuggestions = data.data.length > 0;
                }
            } catch (error) {
                console.error('Error searching similar patients:', error);
                this.similarPatients = [];
                this.showSuggestions = false;
            }
        },
        
        async checkDuplicate() {
            const name = $wire.get('patient_name');
            const clientId = $wire.get('client_id');
            
            if (!name || !clientId) {
                this.duplicatePatient = null;
                return;
            }
            
            try {
                const response = await fetch('/api/patients/check-duplicate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content')
                    },
                    body: JSON.stringify({ name, client_id: clientId })
                });
                
                const data = await response.json();
                if (data.success && data.is_duplicate) {
                    this.duplicatePatient = data.duplicate_patient;
                } else {
                    this.duplicatePatient = null;
                }
            } catch (error) {
                console.error('Error checking duplicate:', error);
                this.duplicatePatient = null;
            }
        },
        
        selectPatient(patient) {
            $wire.set('patient_name', patient.name);
            this.showSuggestions = false;
            this.selectedIndex = -1;
        },
        
        handleKeydown(event) {
            if (!this.showSuggestions) return;
            
            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this.selectedIndex = Math.min(this.selectedIndex + 1, this.similarPatients.length - 1);
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                    break;
                case 'Enter':
                    event.preventDefault();
                    if (this.selectedIndex >= 0) {
                        this.selectPatient(this.similarPatients[this.selectedIndex]);
                    }
                    break;
                case 'Escape':
                    this.showSuggestions = false;
                    this.selectedIndex = -1;
                    break;
            }
        }
    }" 
    x-init="
        $watch('$wire.patient_name', () => {
            searchSimilar();
            checkDuplicate();
        });
        $watch('$wire.client_id', () => {
            searchSimilar();
            checkDuplicate();
        });
    ">
        
        <div class="relative">
            <input
                type="text"
                id="{{ $getId() }}"
                name="{{ $getName() }}"
                value="{{ $getState() }}"
                x-model="$wire.patient_name"
                @keydown="handleKeydown($event)"
                @blur="setTimeout(() => showSuggestions = false, 200)"
                @focus="if (similarPatients.length > 0) showSuggestions = true"
                {!! $isAutofocused() ? 'autofocus' : null !!}
                {!! $isDisabled() ? 'disabled' : null !!}
                {!! $isRequired() ? 'required' : null !!}
                {{ $applyStateBindingModifiers('wire:model') }}
                {{ $getExtraInputAttributeBag()->class(['block w-full transition duration-75 rounded-lg shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 disabled:opacity-70 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 border-gray-300 dark:border-gray-600', 'border-danger-600 ring-danger-600 dark:border-danger-400 dark:ring-danger-400' => $errors->has($getStatePath())]) }}
                {{ $getExtraAlpineAttributeBag() }}
            />
            
            <!-- Similar Patients Suggestions -->
            <div x-show="showSuggestions" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-95"
                 class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                
                <div class="p-2 text-sm text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                    Similar patients found:
                </div>
                
                <template x-for="(patient, index) in similarPatients" :key="patient.id">
                    <div @click="selectPatient(patient)"
                         @mouseenter="selectedIndex = index"
                         :class="{
                             'bg-primary-50 dark:bg-primary-900/20': selectedIndex === index,
                             'hover:bg-gray-50 dark:hover:bg-gray-700': selectedIndex !== index
                         }"
                         class="p-3 cursor-pointer border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                        
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white" x-text="patient.name"></div>
                                <div class="text-sm text-gray-500 dark:text-gray-400" x-text="patient.client_name"></div>
                            </div>
                            <div class="text-right text-xs text-gray-400 dark:text-gray-500">
                                <div x-text="patient.dob || 'No DOB'"></div>
                                <div x-text="patient.gender || 'No Gender'"></div>
                                <div x-text="`${patient.files_count} files`"></div>
                            </div>
                        </div>
                    </div>
                </template>
                
                <div x-show="similarPatients.length === 0" class="p-3 text-sm text-gray-500 dark:text-gray-400">
                    No similar patients found
                </div>
            </div>
        </div>
        
        <!-- Duplicate Warning -->
        <div x-show="duplicatePatient" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="mt-2 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
            
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                        Duplicate Patient Found
                    </h3>
                    <div class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                        <p>A patient with this name already exists for the selected client:</p>
                        <div class="mt-2 p-2 bg-white dark:bg-gray-800 rounded border">
                            <div class="font-medium" x-text="duplicatePatient.name"></div>
                            <div class="text-sm text-gray-500" x-text="duplicatePatient.client_name"></div>
                            <div class="text-xs text-gray-400">
                                <span x-text="duplicatePatient.dob || 'No DOB'"></span> • 
                                <span x-text="duplicatePatient.gender || 'No Gender'"></span> • 
                                <span x-text="`${duplicatePatient.files_count} files`"></span>
                            </div>
                        </div>
                        <p class="mt-2">The system will use the existing patient instead of creating a duplicate.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component> 
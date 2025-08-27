<?php

namespace App\Livewire;

use App\Models\File;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use App\Models\Country;
use Livewire\Component;
use Livewire\WithPagination;

class RequestAppointmentModal extends Component
{
    use WithPagination;

    public File $file;
    public $selectedBranches = [];
    public $customEmails = [];
    public $selectedBranchForPhone = null;
    
    // Filter properties
    public $search = '';
    public $serviceTypeFilter = '';
    public $countryFilter = '';
    public $statusFilter = '';
    public $showProvinceBranches = false;
    public $showOnlyWithEmail = false;
    public $showOnlyWithPhone = false;
    
    // Sorting
    public $sortField = 'priority';
    public $sortDirection = 'asc';

    protected $queryString = [
        'search' => ['except' => ''],
        'serviceTypeFilter' => ['except' => ''],
        'countryFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'showProvinceBranches' => ['except' => false],
        'showOnlyWithEmail' => ['except' => false],
        'showOnlyWithPhone' => ['except' => false],
        'sortField' => ['except' => 'priority'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function mount(File $file)
    {
        $this->file = $file;
        $this->customEmails = [''];
        
        // Set default filters
        $this->statusFilter = 'Active'; // Only show active providers by default
        
        // Set country filter based on file country, except for Telemedicine
        if ($file->service_type_id != 2 && $file->country_id) {
            $this->countryFilter = $file->country_id;
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedServiceTypeFilter()
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedCountryFilter()
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedShowProvinceBranches()
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedShowOnlyWithEmail()
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedShowOnlyWithPhone()
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function selectAll()
    {
        // Only select branches that are currently visible (after filtering)
        $branchIds = $this->getBranches()->pluck('id')->toArray();
        $this->selectedBranches = array_unique(array_merge($this->selectedBranches, $branchIds));
    }

    public function clearSelection()
    {
        $this->selectedBranches = [];
    }

    public function toggleBranch($branchId)
    {
        if (in_array($branchId, $this->selectedBranches)) {
            $this->selectedBranches = array_diff($this->selectedBranches, [$branchId]);
        } else {
            $this->selectedBranches[] = $branchId;
        }
    }

    public function addCustomEmail()
    {
        $this->customEmails[] = '';
    }

    public function removeCustomEmail($index)
    {
        unset($this->customEmails[$index]);
        $this->customEmails = array_values($this->customEmails);
    }

    public function getBranches()
    {
        $query = ProviderBranch::query()
            ->with(['provider.country', 'cities', 'branchServices.serviceType', 'operationContact', 'gopContact', 'financialContact'])
            ->where('provider_branches.status', 'Active')
            ->whereHas('branchServices', function ($q) {
                $q->where('service_type_id', $this->file->service_type_id)
                  ->where('is_active', true);
            })
            ->whereHas('provider', function ($q) {
                $q->where('status', 'Active'); // Only show active providers
            });

        // Apply filters
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('provider_branches.branch_name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('provider', function ($providerQuery) {
                      $providerQuery->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->serviceTypeFilter) {
            $query->whereHas('branchServices', function ($q) {
                $q->where('service_type_id', $this->serviceTypeFilter);
            });
        }

        if ($this->countryFilter) {
            $query->whereHas('provider', function ($q) {
                $q->where('country_id', $this->countryFilter);
            });
        }

        if ($this->statusFilter) {
            $query->whereHas('provider', function ($q) {
                $q->where('providers.status', $this->statusFilter);
            });
        }

        if ($this->showOnlyWithEmail) {
            $query->where(function ($q) {
                $q->whereNotNull('provider_branches.email')
                  ->orWhereHas('operationContact', function ($contactQuery) {
                      $contactQuery->whereNotNull('email');
                  })
                  ->orWhereHas('gopContact', function ($contactQuery) {
                      $contactQuery->whereNotNull('email');
                  })
                  ->orWhereHas('financialContact', function ($contactQuery) {
                      $contactQuery->whereNotNull('email');
                  });
            });
        }

        if ($this->showOnlyWithPhone) {
            $query->where(function ($q) {
                $q->whereNotNull('provider_branches.phone')
                  ->orWhereHas('operationContact', function ($contactQuery) {
                      $contactQuery->whereNotNull('phone_number');
                  })
                  ->orWhereHas('gopContact', function ($contactQuery) {
                      $contactQuery->whereNotNull('phone_number');
                  })
                  ->orWhereHas('financialContact', function ($contactQuery) {
                      $contactQuery->whereNotNull('phone_number');
                  });
            });
        }

        // Apply sorting
        switch ($this->sortField) {
            case 'branch_name':
                $query->orderBy('provider_branches.branch_name', $this->sortDirection);
                break;
            case 'provider_name':
                $query->join('providers', 'provider_branches.provider_id', '=', 'providers.id')
                      ->orderBy('providers.name', $this->sortDirection)
                      ->select('provider_branches.*');
                break;
            case 'priority':
                $query->orderBy('provider_branches.priority', $this->sortDirection);
                break;
            case 'country':
                $query->join('providers', 'provider_branches.provider_id', '=', 'providers.id')
                      ->join('countries', 'providers.country_id', '=', 'countries.id')
                      ->orderBy('countries.name', $this->sortDirection)
                      ->select('provider_branches.*');
                break;
            default:
                $query->orderBy('provider_branches.priority', 'asc');
        }

        return $query->paginate(20);
    }

    public function getDistanceToBranch($branch)
    {
        if (!$this->file->address) {
            return 'N/A';
        }

        // First try to use the direct address field on the branch
        if ($branch->address) {
            $distanceService = app(\App\Services\DistanceCalculationService::class);
            $distanceData = $distanceService->calculateDistance($this->file->address, $branch->address);
            return $distanceService->getFormattedDistance($distanceData);
        }

        // Fallback to operation contact address
        $operationContact = $branch->operationContact;
        if (!$operationContact || !$operationContact->address) {
            return 'N/A';
        }

        $distanceService = app(\App\Services\DistanceCalculationService::class);
        $distanceData = $distanceService->calculateDistance($this->file->address, $operationContact->address);
        return $distanceService->getFormattedDistance($distanceData);
    }

    public function getCostForService($branch)
    {
        return $branch->getCostForService($this->file->service_type_id);
    }

    public function hasEmail($branch)
    {
        return $branch->email || 
               $branch->operationContact?->email || 
               $branch->gopContact?->email || 
               $branch->financialContact?->email;
    }

    public function hasPhone($branch)
    {
        return $branch->phone || 
               $branch->operationContact?->phone_number || 
               $branch->gopContact?->phone_number || 
               $branch->financialContact?->phone_number;
    }

    public function getPhoneInfo($branchId)
    {
        $branch = ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->find($branchId);
        
        if (!$branch) {
            return null;
        }

        return [
            'branch_name' => $branch->branch_name,
            'direct_phone' => $branch->phone,
            'operation_contact' => [
                'name' => $branch->operationContact?->name,
                'phone' => $branch->operationContact?->phone_number,
                'email' => $branch->operationContact?->email,
            ],
            'gop_contact' => [
                'name' => $branch->gopContact?->name,
                'phone' => $branch->gopContact?->phone_number,
                'email' => $branch->gopContact?->email,
            ],
            'financial_contact' => [
                'name' => $branch->financialContact?->name,
                'phone' => $branch->financialContact?->phone_number,
                'email' => $branch->financialContact?->email,
            ],
        ];
    }

    public function render()
    {
        $branches = $this->getBranches();
        $serviceTypes = ServiceType::all();
        $countries = Country::all();

        return view('livewire.request-appointment-modal', [
            'branches' => $branches,
            'serviceTypes' => $serviceTypes,
            'countries' => $countries,
        ]);
    }
}

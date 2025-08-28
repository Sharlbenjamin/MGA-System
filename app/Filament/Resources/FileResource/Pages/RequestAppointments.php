<?php

namespace App\Filament\Resources\FileResource\Pages;

use App\Filament\Resources\FileResource;
use App\Models\File;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class RequestAppointments extends Page
{
    protected static string $resource = FileResource::class;

    protected static string $view = 'filament.resources.file-resource.pages.request-appointments';

    public File $file;
    public $search = '';
    public $serviceTypeFilter = '';
    public $countryFilter = '';
    public $statusFilter = '';
    public $showProvinceBranches = false;
    public $showOnlyWithEmail = false;
    public $showOnlyWithPhone = false;
    public $sortField = 'priority';
    public $sortDirection = 'asc';
    public $selectedBranches = [];
    public $customEmails = [];
    public $selectedBranchForPhone = null;

    public function mount($record): void
    {
        $this->file = File::findOrFail($record);
        
        // Set default filters
        $this->statusFilter = 'Active';
        $this->countryFilter = $this->file->service_type_id === 2 ? '' : $this->file->country_id;
    }

    public function getBranches()
    {
        return $this->getBranchesQuery()->paginate(20);
    }

    public function updatedSearch()
    {
        $this->selectedBranches = [];
    }

    public function updatedServiceTypeFilter()
    {
        $this->selectedBranches = [];
    }

    public function updatedCountryFilter()
    {
        $this->selectedBranches = [];
    }

    public function updatedStatusFilter()
    {
        $this->selectedBranches = [];
    }

    public function updatedShowOnlyWithEmail()
    {
        $this->selectedBranches = [];
    }

    public function updatedShowOnlyWithPhone()
    {
        $this->selectedBranches = [];
    }

    public function selectAll()
    {
        $branches = $this->getBranchesQuery()->get();
        $this->selectedBranches = $branches->pluck('id')->toArray();
    }

    public function clearSelection()
    {
        $this->selectedBranches = [];
    }

    public function addCustomEmail()
    {
        $this->customEmails[] = ['email' => ''];
    }

    public function removeCustomEmail($index)
    {
        unset($this->customEmails[$index]);
        $this->customEmails = array_values($this->customEmails);
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

    protected function getBranchesQuery(): Builder
    {
        $query = \App\Models\ProviderBranch::with([
            'provider.country',
            'operationContact',
            'gopContact', 
            'financialContact',
            'cities',
            'branchServices.serviceType'
        ])
        ->whereHas('provider', function ($q) {
            $q->where('status', 'Active');
        })
        ->whereHas('branchServices', function ($q) {
            $q->where('service_type_id', $this->file->service_type_id)
              ->where('is_active', 1);
        });

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('branch_name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('provider', function ($providerQuery) {
                      $providerQuery->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Apply service type filter
        if ($this->serviceTypeFilter) {
            $query->whereHas('branchServices', function ($q) {
                $q->where('service_type_id', $this->serviceTypeFilter)
                  ->where('is_active', 1);
            });
        }

        // Apply country filter
        if ($this->countryFilter) {
            $query->whereHas('provider', function ($q) {
                $q->where('country_id', $this->countryFilter);
            });
        }

        // Apply status filter
        if ($this->statusFilter) {
            $query->whereHas('provider', function ($q) {
                $q->where('status', $this->statusFilter);
            });
        }

        // Apply email filter
        if ($this->showOnlyWithEmail) {
            $query->where(function ($q) {
                $q->whereNotNull('email')
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

        // Apply phone filter
        if ($this->showOnlyWithPhone) {
            $query->where(function ($q) {
                $q->whereNotNull('phone')
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
        if ($this->sortField === 'provider') {
            $query->join('providers', 'provider_branches.provider_id', '=', 'providers.id')
                  ->orderBy('providers.name', $this->sortDirection)
                  ->select('provider_branches.*');
        } elseif ($this->sortField === 'status') {
            $query->join('providers', 'provider_branches.provider_id', '=', 'providers.id')
                  ->orderBy('providers.status', $this->sortDirection)
                  ->select('provider_branches.*');
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        return $query;
    }

    public function toggleBranch($branchId)
    {
        if (in_array($branchId, $this->selectedBranches)) {
            $this->selectedBranches = array_diff($this->selectedBranches, [$branchId]);
        } else {
            $this->selectedBranches[] = $branchId;
        }
    }

    public function getDistanceToBranch($branch)
    {
        $service = app(\App\Services\DistanceCalculationService::class);
        return $service->calculateFileToBranchDistance($this->file, $branch);
    }

    public function getCostForService($branch, $serviceTypeId)
    {
        return $branch->getCostForService($serviceTypeId);
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
        $branch = \App\Models\ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->find($branchId);
        
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

    public function formatPhoneInfo($phoneInfo)
    {
        $output = "**{$phoneInfo['branch_name']}**\n\n";
        
        if ($phoneInfo['direct_phone']) {
            $output .= "**Direct Phone:** {$phoneInfo['direct_phone']}\n";
        }
        
        if ($phoneInfo['operation_contact']['name']) {
            $output .= "\n**Operation Contact:** {$phoneInfo['operation_contact']['name']}\n";
            if ($phoneInfo['operation_contact']['phone']) {
                $output .= "Phone: {$phoneInfo['operation_contact']['phone']}\n";
            }
            if ($phoneInfo['operation_contact']['email']) {
                $output .= "Email: {$phoneInfo['operation_contact']['email']}\n";
            }
        }
        
        if ($phoneInfo['gop_contact']['name']) {
            $output .= "\n**GOP Contact:** {$phoneInfo['gop_contact']['name']}\n";
            if ($phoneInfo['gop_contact']['phone']) {
                $output .= "Phone: {$phoneInfo['gop_contact']['phone']}\n";
            }
            if ($phoneInfo['gop_contact']['email']) {
                $output .= "Email: {$phoneInfo['gop_contact']['email']}\n";
            }
        }
        
        if ($phoneInfo['financial_contact']['name']) {
            $output .= "\n**Financial Contact:** {$phoneInfo['financial_contact']['name']}\n";
            if ($phoneInfo['financial_contact']['phone']) {
                $output .= "Phone: {$phoneInfo['financial_contact']['phone']}\n";
            }
            if ($phoneInfo['financial_contact']['email']) {
                $output .= "Email: {$phoneInfo['financial_contact']['email']}\n";
            }
        }
        
        return $output;
    }

    public function sendRequests()
    {
        $customEmails = collect($this->customEmails)
            ->pluck('email')
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->toArray();

        // If no branches or custom emails selected, show warning
        if (empty($this->selectedBranches) && empty($customEmails)) {
            Notification::make()
                ->title('No Recipients Selected')
                ->body('Please select at least one provider branch or add a custom email address.')
                ->warning()
                ->send();
            return;
        }

        // Send notifications and emails
        $successfulBranches = [];
        $skippedBranches = [];
        $updatedAppointments = [];
        $newAppointments = [];

        // Create/update appointments in a transaction
        DB::transaction(function () use (&$successfulBranches, &$skippedBranches, &$updatedAppointments, &$newAppointments, $customEmails) {
            foreach ($this->selectedBranches as $branchId) {
                $providerBranch = \App\Models\ProviderBranch::find($branchId);
                
                if (!$providerBranch) {
                    continue;
                }

                // Check if an appointment already exists
                $existingAppointment = $this->file->appointments()
                    ->where('provider_branch_id', $branchId)
                    ->first();

                if ($existingAppointment) {
                    $newDate = now()->toDateString();

                    if ($existingAppointment->service_date !== $newDate) {
                        $existingAppointment->update([
                            'service_date' => $newDate,
                        ]);
                        $updatedAppointments[] = $providerBranch->branch_name;
                    }
                } else {
                    // Create new appointment
                    $appointment = $this->file->appointments()->create([
                        'provider_branch_id' => $branchId,
                        'service_date' => now()->toDateString(),
                        'status' => 'Requested',
                        'created_by' => auth()->id(),
                    ]);
                    $newAppointments[] = $providerBranch->branch_name;
                }

                // Send email notification
                try {
                    $email = $providerBranch->email ?: 
                             $providerBranch->operationContact?->email ?: 
                             $providerBranch->gopContact?->email ?: 
                             $providerBranch->financialContact?->email;

                    if ($email) {
                        \Mail::to($email)->send(new \App\Mail\AppointmentRequestMail($this->file, $providerBranch));
                        $successfulBranches[] = $providerBranch->branch_name;
                    } else {
                        $skippedBranches[] = $providerBranch->branch_name . ' (No email)';
                    }
                } catch (\Exception $e) {
                    $skippedBranches[] = $providerBranch->branch_name . ' (Email failed)';
                }
            }

            // Handle custom emails
            foreach ($customEmails as $email) {
                try {
                    \Mail::to($email)->send(new \App\Mail\AppointmentRequestMail($this->file, null));
                    $successfulBranches[] = 'Custom: ' . $email;
                } catch (\Exception $e) {
                    $skippedBranches[] = 'Custom: ' . $email . ' (Email failed)';
                }
            }
        });

        // Show success notification
        $message = "Successfully sent " . count($successfulBranches) . " appointment requests.";
        if (!empty($updatedAppointments)) {
            $message .= " Updated " . count($updatedAppointments) . " existing appointments.";
        }
        if (!empty($newAppointments)) {
            $message .= " Created " . count($newAppointments) . " new appointments.";
        }
        if (!empty($skippedBranches)) {
            $message .= " Skipped " . count($skippedBranches) . " branches.";
        }

        Notification::make()
            ->title('Appointment Requests Sent')
            ->body($message)
            ->success()
            ->send();

        // Redirect back to the file view
        return redirect()->route('filament.admin.resources.files.view', $this->file);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToFile')
                ->label('Back to File')
                ->icon('heroicon-o-arrow-left')
                ->url(route('filament.admin.resources.files.view', $this->file))
                ->color('gray'),
            
            Action::make('sendRequests')
                ->label('Send Appointment Requests')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->action('sendRequests')
                ->requiresConfirmation()
                ->modalHeading('Send Appointment Requests')
                ->modalDescription('Are you sure you want to send appointment requests to the selected providers?')
                ->modalSubmitActionLabel('Send Requests'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}

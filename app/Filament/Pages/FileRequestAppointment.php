<?php

namespace App\Filament\Pages;

use App\Models\File;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use App\Models\Country;
use App\Models\City;
use App\Services\GoogleDistanceService;
use App\Mail\NotifyBranchMailable;
use App\Models\Appointment;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\View\View;

class FileRequestAppointment extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Request Appointment';
    protected static ?string $slug = 'file-request-appointment/{record}';
    protected static string $view = 'filament.panels::page';

    public File $file;
    public array $customEmails = [];

    public function mount($record): void
    {
        $this->file = File::with(['patient', 'city', 'country', 'serviceType'])->findOrFail($record);
    }

    public function getTitle(): string
    {
        return "Request Appointment - {$this->file->mga_reference}";
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back_to_file')
                ->label('Back to File')
                ->icon('heroicon-o-arrow-left')
                ->url(route('filament.admin.resources.files.view', $this->file))
                ->color('gray'),
        ];
    }

    public function getTableQuery(): Builder
    {
        return ProviderBranch::query()
            ->with(['city', 'provider', 'branchServices.serviceType', 'operationContact', 'gopContact', 'financialContact'])
            ->where('status', 'Active')
            ->whereHas('branchServices', function ($q) {
                $q->where('service_type_id', $this->file->service_type_id)
                  ->where('is_active', true);
            });
    }

    protected function getDistanceToBranch(ProviderBranch $branch): string
    {
        $distanceService = app(GoogleDistanceService::class);
        $distanceData = $distanceService->calculateFileToBranchDistance($this->file, $branch);
        return $distanceService->getFormattedDistance($distanceData);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('branch_name')
                    ->label('Branch Name')
                    ->url(fn (ProviderBranch $record) => route('filament.admin.resources.provider-branches.edit', $record))
                    ->openUrlInNewTab()
                    ->color('primary'),

                TextColumn::make('priority')->sortable(),
                TextColumn::make('city.name')->sortable(),
                TextColumn::make('status')->badge(),

                TextColumn::make('services')->formatStateUsing(fn (ProviderBranch $record) =>
                    $record->branchServices()
                        ->where('service_type_id', $this->file->service_type_id)
                        ->where('is_active', true)
                        ->get()
                        ->pluck('serviceType.name')
                        ->implode(', ')
                ),

                TextColumn::make('distance')->formatStateUsing(fn (ProviderBranch $record) =>
                    $this->getDistanceToBranch($record)
                ),
            ])
            ->bulkActions([
                BulkAction::make('sendAppointmentRequests')
                    ->action(fn ($records) => $this->sendAppointmentRequests($records))
            ]);
    }

    protected function sendAppointmentRequests(array $branches): void
    {
        foreach ($branches as $branchId) {
            $branch = ProviderBranch::find($branchId);
            if (!$branch) continue;

            $appointment = Appointment::updateOrCreate(
                [
                    'file_id' => $this->file->id,
                    'provider_branch_id' => $branchId,
                ],
                [
                    'status' => 'Requested',
                    'requested_at' => now(),
                ]
            );

            if ($email = $branch->getPrimaryEmailAttribute()) {
                try {
                    Mail::to($email)->send(new NotifyBranchMailable('appointment_created', $appointment));
                } catch (\Exception $e) {
                    Log::error("Mail failed to branch {$branchId}: {$e->getMessage()}");
                }
            }
        }

        Notification::make()
            ->title('Requests sent')
            ->body('Appointment requests sent to selected branches.')
            ->success()
            ->send();
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        $panel = $panel ?? 'admin';
        $slug = static::$slug;
        
        // Replace {record} placeholder with actual record ID
        if (isset($parameters['record'])) {
            $slug = str_replace('{record}', $parameters['record'], $slug);
        }
        
        $url = "/{$panel}/{$slug}";
        
        return $isAbsolute ? url($url) : $url;
    }
}

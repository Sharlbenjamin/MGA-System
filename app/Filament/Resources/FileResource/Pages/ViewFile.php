<?php

namespace App\Filament\Resources\FileResource\Pages;

use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Infolist;
use App\Filament\Resources\FileResource;
use Filament\Infolists\Components\Card;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\RepeatableEntry;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\View;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppointmentRequestMail;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Filament\Widgets\CommentsWidget;

class ViewFile extends ViewRecord
{
    protected static string $resource = FileResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()
                    ->columns(2) // Two columns layout
                    ->schema([
                        // 🏛 Column 1: 3 Stacked Cards (MGA Reference, Patient, Client)
                        Section::make()->schema([
                            // 🔸 MGA Reference - Top Card (Gray Background, Orange Text)
                            Card::make()
                                ->schema([
                                    TextEntry::make('mga_reference')
                                        ->label('MGA Reference')
                                        ->color('warning') // Orange text
                                        ->weight('bold')
                                        ->size('lg'),
                                ])
                                ->columnSpan(1),

                            // 🔹 Patient Details - Middle Card (Blue)
                            Card::make()
                                ->schema([
                                    TextEntry::make('patient.name')->label('Patient Name')->weight('bold')->color('info'),
                                    TextEntry::make('patient.dob')->color('info')
                                        ->label('Age')
                                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->age . ' years')->color('info'),
                                    TextEntry::make('patient.gender')->label('Gender')->color('info'),
                                ])// Blue background
                                ->columnSpan(1),

                            // 🟢 Client Details - Bottom Card (Green)
                            Card::make()
                                ->schema([
                                    TextEntry::make('patient.client.company_name')->label('Client Name')->weight('bold')->color('info'),
                                    TextEntry::make('client_reference')->label('Client Reference')->color('info'),
                                    TextEntry::make('country.name')->label('Country')->color('info'),
                                    TextEntry::make('city.name')->label('City')->color('info'),
                                ])
                                ->columnSpan(1),
                        ])
                        ->columnSpan(1), // Column 1 (Left)

                        // 📌 Column 2: Single Card for File Details
                        Card::make()
                            ->schema([
                                TextEntry::make('serviceType.name')->label('Service Type')->weight('bold')->color('info')->color('info'),
                                TextEntry::make('status')->label('Status')->badge(),
                                TextEntry::make('providerBranch.branch_name')->label('Branch Name')->color('info'),
                                TextEntry::make('service_date')->label('Service Date')->date()->color('info'),
                                TextEntry::make('service_time')->label('Service Time')->time()->color('info'),
                                TextEntry::make('address')->label('Address')->color('info'),
                                TextEntry::make('symptoms')->label('Symptoms')->color('info'),
                                TextEntry::make('diagnosis')->label('Diagnosis')->color('info'),
                            ])
                            ->columnSpan(1), // Column 2 (Right)
                    ]),
                ]);
    }

    protected function getFooterWidgets(): array
    {
        return [
            CommentsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestAppointments')
                ->label('Request Appointments')
                ->modalHeading('Select Branches for Appointment Request')
                ->modalWidth('4xl') // Make modal wider
                ->form([
                    Repeater::make('selected_branches')
                        ->label('Available Branches')
                        ->schema([
                            Checkbox::make('selected')->label('Select')->default(false),
                            TextInput::make('name')->label('Branch Name')->disabled(),
                            TextInput::make('provider')->label('Provider Name')->disabled(),
                            TextInput::make('preferred_contact')->label('Preferred Contact')->default(fn ($get) => optional($get('contact'))->preferred_contact ?? 'N/A')->disabled(),
                        ])
                        ->columns(4)
                        ->default(fn ($record) => $record->fileBranches()->map(fn ($branch) => [
                            'id' => $branch->id,
                            'selected' => false,
                            'name' => $branch->branch_name,
                            'provider' => $branch->provider->name ?? 'N/A',
                            'preferred_contact' => optional($branch->primaryContact('Appointment'))->preferred_contact ?? 'N/A',
                        ])->toArray()),
                ])
                ->modalButton('Send Requests')
                ->action(fn (array $data, $record) => $this->bulkSendRequests($data, $record)),

            Action::make('Update Request')
                ->label('Update Request')
                ->icon('heroicon-o-pencil')
                ->url(fn ($record) => route('filament.admin.resources.files.edit', $record))
                ->openUrlInNewTab(false)
        ];
    }

    public function bulkSendRequests(array $data, $record)
    {
        $selectedBranches = collect($data['selected_branches'] ?? [])
            ->filter(fn ($branch) => $branch['selected'])
            ->pluck('id');

        $skippedBranches = [];
        $updatedAppointments = [];
        $newAppointments = [];

        DB::transaction(function () use ($selectedBranches, $record, &$skippedBranches, &$updatedAppointments, &$newAppointments) {
            foreach ($selectedBranches as $branchId) {
                $providerBranch = \App\Models\ProviderBranch::find($branchId);

                if (!$providerBranch) {
                    continue;
                }

                $contact = $providerBranch->primaryContact('Appointment');
                if (!$contact) {
                    $skippedBranches[] = $providerBranch->branch_name;
                    continue;
                }

                // ✅ Check if an appointment already exists
                $existingAppointment = $record->appointments()
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
                    continue;
                }

                // ✅ Ensure a new appointment is created
                $appointment = new \App\Models\Appointment([
                    'file_id' => $record->id,
                    'provider_branch_id' => $branchId,
                    'service_date' => now()->toDateString(),
                    'service_time' => now()->toTimeString(),
                    'status' => 'Requested',
                ]);

                if ($appointment->save()) {
                    // ✅ Track newly created appointments
                    $newAppointments[] = $providerBranch->branch_name;

                    // ✅ Create a task for the new appointment
                    \App\Models\Task::create([
                        'taskable_id' => $appointment->id,
                        'taskable_type' => \App\Models\Appointment::class,
                        'department' => 'Operation',
                        'title' => 'New Appointment Request',
                        'description' => "Confirm appointment with {$providerBranch->branch_name} for {$appointment->service_date}.",
                        'due_date' => now()->addHours(2),
                        'user_id' => Auth::id(),
                        'file_id' => $record->id,
                    ]);
                }
            }
        });

        // ✅ Notify user of results
        if (!empty($skippedBranches)) {
            Notification::make()->title('Some Requests Skipped')->body('The following branches were skipped due to missing contact: ' . implode(', ', $skippedBranches))->warning()->send();
        }

        if (!empty($updatedAppointments)) {
            Notification::make()->title('Appointments Updated')->body('Appointments updated for: ' . implode(', ', $updatedAppointments))->info()->send();
        }

        if (!empty($newAppointments)) {
            Notification::make()->title('Appointments Created')->body('Appointments created for: ' . implode(', ', $newAppointments))->success()->send();
        }
    }

    public function mount($record): void
    {
        parent::mount($record);

        $this->alertMessage = Session::get('contact_alert');
    }

    public $alertMessage;

    public function clearAlert(): void
    {
        $this->alertMessage = null;
        Session::forget('contact_alert');
    }
}

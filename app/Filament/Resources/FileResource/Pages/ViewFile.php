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
use Illuminate\Support\Facades\Session;

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
                                    TextEntry::make('client.name')->label('Client Name')->weight('bold')->color('info'),
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
                    Card::make()
    ->schema([
        RepeatableEntry::make('comments')
            ->label('Comments')
            ->schema([
                TextEntry::make('user.name')
                    ->label('Posted By')
                    ->weight('bold')
                    ->color('primary'),

                TextEntry::make('content')
                    ->label('Comment')
                    ->markdown(),

                TextEntry::make('created_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->columnSpanFull() // Makes it span the full width
    ])
    ->columnSpanFull(),
            ]);
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
                Checkbox::make('selected')
                    ->label('Select')
                    ->default(false),

                TextInput::make('name')
                    ->label('Branch Name')
                    ->disabled(),

                TextInput::make('provider')
                    ->label('Provider Name')
                    ->disabled(),

                TextInput::make('preferred_contact')
                    ->label('Preferred Contact')
                    ->default(fn ($get) => optional($get('contact'))->preferred_contact ?? 'N/A')
                    ->disabled(),
                ])
                ->columns(4)
                ->default(fn ($record) => $record->fileBranches()->map(fn ($branch) => [
                    'id' => $branch->id,
                    'selected' => false,
                    'name' => $branch->branch_name,
                    'provider' => $branch->provider->name ?? 'N/A',
                    'preferred_contact' => optional($branch->firstContact())->preferred_contact ?? 'N/A',
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
    // ✅ Filter and get only the selected branch IDs
    $selectedBranches = collect($data['selected_branches'] ?? [])
        ->filter(fn ($branch) => $branch['selected']) // ✅ Keep only selected branches
        ->pluck('id'); // ✅ Extract only the branch IDs

    foreach ($selectedBranches as $branchId) {
        $providerBranch = \App\Models\ProviderBranch::find($branchId);

        if (!$providerBranch) {
            continue; // ✅ Skip if branch not found
        }

       
        // ✅ Create the appointment
        $appointment = $record->appointments()->create([
            'provider_branch_id' => $branchId,
            'service_date' => now()->addDays(1)->toDateString(), // ✅ Ensure correct date format
            'status' => 'Requested',
        ]);
       
    }

    // ✅ Final success message after processing all requests
    Notification::make()
        ->title('Requests Sent Successfully!')
        ->success()
        ->send();
}
public function mount($record): void
    {
        parent::mount($record);

        // Check if a session flash message exists and store it in a property
        if (Session::has('contact_alert')) {
            $this->alertMessage = Session::get('contact_alert');
        }
    }
    public $alertMessage;

    public function clearAlert()
    {
        $this->alertMessage = null; // Remove message after confirmation
        Session::forget('contact_alert'); // Ensure it's removed from session
    }
}
<?php

namespace App\Filament\Pages;

use App\Models\{Provider, ProviderBranch, ServiceType, City};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BulkAddBranches extends Page
{
    protected static ?string $navigationGroup = 'PRM';
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $title = 'Bulk Add Provider Branches';
    protected static string $view = 'filament.pages.blank';
    
    public static function shouldRegisterNavigation(): bool
    {
        // Only show if the branch_service table exists
        return Schema::hasTable('branch_service');
    }

    use Forms\Concerns\InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'data' => [
                'provider_id' => null,
                'branches' => [
                    [
                        'branch_name' => '',
                        'city_id' => null,
                        'additional_cities' => [],
                        'address' => '',
                        'email' => '',
                        'phone' => '',
                        'services' => [
                            [
                                'service_type_id' => null,
                                'min_cost' => null,
                                'max_cost' => null,
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Select::make('provider_id')
                ->label('Provider')->options(Provider::query()->pluck('name','id'))
                ->searchable()->required()->reactive(),
            Forms\Components\Repeater::make('branches')
                ->minItems(1)
                ->columns(2)
                ->addActionLabel('Add Branch')
                ->reorderable()
                ->schema([
                    Forms\Components\TextInput::make('branch_name')
                        ->label('Branch Name')
                        ->required(),
                    Forms\Components\Select::make('city_id')
                        ->label('Primary City')
                        ->options(function (callable $get) {
                            $providerId = $get('../../provider_id');
                            if (!$providerId) {
                                return [];
                            }
                            $provider = Provider::with('country.cities')->find($providerId);
                            return $provider ? $provider->country->cities->pluck('name', 'id') : [];
                        })
                        ->searchable()
                        ->required()
                        ->reactive(),
                    Forms\Components\Select::make('additional_cities')
                        ->label('Additional Cities')
                        ->options(function (callable $get) {
                            $providerId = $get('../../provider_id');
                            if (!$providerId) {
                                return [];
                            }
                            $provider = Provider::with('country.cities')->find($providerId);
                            return $provider ? $provider->country->cities->pluck('name', 'id') : [];
                        })
                        ->searchable()
                        ->multiple()
                        ->preload()
                        ->reactive(),
                    Forms\Components\TextInput::make('address')->columnSpanFull(),
                    Forms\Components\TextInput::make('email')->email(),
                    Forms\Components\TextInput::make('phone')->tel(),
                    Forms\Components\Repeater::make('services')
                        ->label('Branch Services')
                        ->minItems(1)
                        ->columns(3)
                        ->addActionLabel('Add Service')
                        ->reorderable()
                        ->schema([
                            Forms\Components\Select::make('service_type_id')
                                ->label('Service')->options(ServiceType::pluck('name','id'))
                                ->searchable()->required()->columnSpan(2),
                    Forms\Components\TextInput::make('min_cost')
                        ->label('Minimum Cost')
                        ->numeric()
                        ->suffix('€')
                        ->minValue(0)
                        ->step(0.01),
                    Forms\Components\TextInput::make('max_cost')
                        ->label('Maximum Cost')
                        ->numeric()
                        ->suffix('€')
                        ->minValue(0)
                        ->step(0.01),
                        ])->columnSpanFull(),
                ]),
            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('save')
                    ->label('Insert All')
                    ->action('saveAll')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Branch Creation')
                    ->modalDescription('Are you sure you want to create all these branches? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, Create All Branches'),
            ])->alignEnd(),
        ]);
    }

    public function saveAll(): void
    {
        try {
            $state = $this->form->getState();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Form Validation Error')
                ->body('Please check all required fields: ' . $e->getMessage())
                ->danger()
                ->send();
            return;
        }
        
        // Check if provider_id is present
        if (empty($state['provider_id'])) {
            Notification::make()
                ->title('Provider Required')
                ->body('Provider is required. Please select a provider.')
                ->danger()
                ->send();
            return;
        }
        
        try {
            DB::transaction(function () use ($state) {
                foreach ($state['branches'] as $b) {
                    $branch = ProviderBranch::create([
                        'provider_id' => $state['provider_id'],
                        'branch_name' => $b['branch_name'],
                        'city_id'     => $b['city_id'],
                        'address'     => $b['address'],
                        'email'       => $b['email'] ?? null,
                        'phone'       => $b['phone'],
                        'priority'    => 1, // Default priority value
                    ]);

                    // Attach additional cities if provided
                    if (!empty($b['additional_cities'])) {
                        $branch->cities()->attach($b['additional_cities']);
                    }

                    // Attach services if provided
                    if (!empty($b['services'])) {
                        $attach = [];
                        foreach ($b['services'] as $s) {
                            $minCost = $s['min_cost'] ?? null;
                            $maxCost = $s['max_cost'] ?? null;
                            
                            // Validate min/max cost logic
                            if ($minCost && $maxCost && $maxCost < $minCost) {
                                // Swap them if max is less than min
                                $temp = $minCost;
                                $minCost = $maxCost;
                                $maxCost = $temp;
                            }
                            
                            $attach[$s['service_type_id']] = [
                                'min_cost' => $minCost,
                                'max_cost' => $maxCost,
                            ];
                        }
                        $branch->services()->attach($attach);
                    }
                }
            });

            Notification::make()
                ->title('Success')
                ->body('Branches created successfully!')
                ->success()
                ->send();
            $this->form->fill(['provider_id' => $state['provider_id'], 'branches' => []]);
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Error creating branches: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}

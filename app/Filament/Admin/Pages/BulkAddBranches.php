<?php

namespace App\Filament\Admin\Pages;

use App\Models\{Provider, ProviderBranch, ServiceType};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class BulkAddBranches extends Page
{
    protected static ?string $navigationGroup = 'PRM';
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $title = 'Bulk Add Provider Branches';
    protected static string $view = 'filament.pages.blank'; // no blade, form-only

    use Forms\Concerns\InteractsWithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Select::make('provider_id')
                ->label('Provider')->options(Provider::query()->pluck('name','id'))
                ->searchable()->required(),
            Forms\Components\Repeater::make('branches')
                ->minItems(1)->columns(2)
                ->schema([
                    Forms\Components\Select::make('city_id')
                        ->relationship('city','name') // or ->options(City::pluck('name','id'))
                        ->searchable()->required(),
                    Forms\Components\TextInput::make('address')->required()->columnSpanFull(),
                    Forms\Components\TextInput::make('email')->email(),
                    Forms\Components\TextInput::make('phone')->tel()->required(),
                    Forms\Components\Repeater::make('services')
                        ->label('Branch Services')->minItems(1)->columns(3)
                        ->schema([
                            Forms\Components\Select::make('service_type_id')
                                ->label('Service')->options(ServiceType::pluck('name','id'))
                                ->searchable()->required()->columnSpan(2),
                    Forms\Components\TextInput::make('min_cost')
                        ->numeric()
                        ->suffix('€')
                        ->minValue(0)
                        ->step(0.01),
                    Forms\Components\TextInput::make('max_cost')
                        ->numeric()
                        ->suffix('€')
                        ->minValue(0)
                        ->step(0.01)
                        ->rules(['gte:min_cost']),
                        ])->columnSpanFull(),
                ]),
            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('save')
                    ->label('Insert All')->submit('saveAll')->color('primary'),
            ])->alignEnd(),
        ]);
    }

    public function saveAll(): void
    {
        $state = $this->form->getState();
        DB::transaction(function () use ($state) {
            foreach ($state['branches'] as $b) {
                $branch = ProviderBranch::create([
                    'provider_id' => $state['provider_id'],
                    'city_id'     => $b['city_id'],
                    'address'     => $b['address'],
                    'email'       => $b['email'] ?? null,
                    'phone'       => $b['phone'],
                ]);

                if (!empty($b['services'])) {
                    $attach = [];
                    foreach ($b['services'] as $s) {
                        $attach[$s['service_type_id']] = [
                            'min_cost' => $s['min_cost'] ?? null,
                            'max_cost' => $s['max_cost'] ?? null,
                        ];
                    }
                    $branch->services()->attach($attach);
                }
            }
        });

        $this->notify('success','Branches inserted successfully.');
        $this->form->fill(['provider_id'=> $state['provider_id'],'branches'=>[]]);
    }
}

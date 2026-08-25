<?php

namespace App\Filament\Support;

use App\Models\File;
use App\Models\Gop;
use App\Models\GopItem;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use App\Services\ClientOfferMessageFormatter;
use App\Services\GopInOfferService;
use App\Services\OfferPricingCalculator;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;

class GopInOfferForm
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function schema(File $file, bool $isEdit = false, bool $inOnly = false): array
    {
        $canEditSelling = OfferPricingCalculator::userCanEditSellingCost();

        $typeField = $inOnly
            ? Forms\Components\Hidden::make('type')->default('In')->dehydrated()
            : Forms\Components\Select::make('type')
                ->options(['In' => 'In', 'Out' => 'Out'])
                ->required()
                ->live()
                ->disabled($isEdit)
                ->dehydrated();

        return [
            Forms\Components\Hidden::make('file_id')->default($file->getKey()),
            $typeField,
            Forms\Components\Select::make('provider_branch_id')
                ->label('Provider branch')
                ->options(fn () => ProviderBranch::query()->orderBy('branch_name')->pluck('branch_name', 'id'))
                ->searchable()
                ->default($file->provider_branch_id)
                ->live()
                ->afterStateUpdated(fn (Set $set, Get $get) => static::applySuggestedCosts($file, $set, $get))
                ->visible(fn (Get $get): bool => $get('type') === 'In'),
            Forms\Components\Select::make('service_type_id')
                ->label('Service type')
                ->options(fn () => ServiceType::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->placeholder('Select from list')
                ->default($file->service_type_id)
                ->live()
                ->visible(fn (Get $get): bool => $get('type') === 'In')
                ->afterStateUpdated(function (Set $set, Get $get) use ($file): void {
                    $set('service_type_other', null);
                    static::applySuggestedCosts($file, $set, $get);
                }),
            Forms\Components\TextInput::make('service_type_other')
                ->label('Other service type')
                ->placeholder('e.g. Cardiology specialist')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('type') === 'In')
                ->helperText('Use when the service is not in the list above.'),
            Forms\Components\Repeater::make('items')
                ->label('Items')
                ->helperText('Draft bill lines: name, cost, and selling cost. Selling cost is calculated by the system.')
                ->schema([
                    Forms\Components\Hidden::make('item_type')->default(GopItem::TYPE_SERVICE),
                    Forms\Components\TextInput::make('description')
                        ->label('Name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('cost')
                        ->label('Cost')
                        ->numeric()
                        ->prefix('€')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, Get $get) use ($file): void {
                            static::recalculateItemSelling($file, $set, $get);
                            static::syncTotalsFromItems($file, $set, $get);
                        }),
                    Forms\Components\TextInput::make('selling_cost')
                        ->label('Selling cost')
                        ->numeric()
                        ->prefix('€')
                        ->disabled(! $canEditSelling)
                        ->dehydrated()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, Get $get) => static::syncTotalsFromItems($file, $set, $get)),
                ])
                ->columns(4)
                ->defaultItems(1)
                ->addActionLabel('Add item')
                ->reorderable()
                ->collapsible()
                ->live()
                ->afterStateUpdated(fn (Set $set, Get $get) => static::syncTotalsFromItems($file, $set, $get))
                ->visible(fn (Get $get): bool => $get('type') === 'In'),
            Forms\Components\TextInput::make('offered_cost')
                ->label('Offered cost')
                ->numeric()
                ->prefix('€')
                ->disabled()
                ->dehydrated()
                ->visible(fn (Get $get): bool => $get('type') === 'In'),
            Forms\Components\TextInput::make('file_fee')
                ->label('File fee')
                ->numeric()
                ->prefix('€')
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => static::syncAmount($set, $get))
                ->visible(fn (Get $get): bool => $get('type') === 'In')
                ->helperText('Calculated from item cost total, country, city, and service. House visits and telemedicine have no separate file fee.'),
            Forms\Components\TextInput::make('amount')
                ->label(fn (Get $get): string => $get('type') === 'In' ? 'Offer total' : 'Amount')
                ->numeric()
                ->prefix('€')
                ->required(fn (Get $get): bool => $get('type') === 'Out')
                ->disabled(fn (Get $get): bool => $get('type') === 'In')
                ->dehydrated(),
            Forms\Components\CheckboxList::make('offer_sections')
                ->label('Offer parts')
                ->options(ClientOfferMessageFormatter::sectionOptions())
                ->default(ClientOfferMessageFormatter::defaultSections())
                ->columns(3)
                ->helperText('Choose which parts appear when you copy the offer or GOP request.')
                ->visible(fn (Get $get): bool => $get('type') === 'In'),
            Forms\Components\Textarea::make('notes')
                ->rows(2)
                ->visible(fn (Get $get): bool => $get('type') === 'In'),
            Forms\Components\DatePicker::make('date')->required()->default(now()),
            Forms\Components\Select::make('status')
                ->options(fn (Get $get) => $get('type') === 'In'
                    ? Gop::inStatusOptions()
                    : Gop::outStatusOptions())
                ->default(fn (Get $get) => $get('type') === 'In'
                    ? Gop::IN_STATUS_DRAFT
                    : Gop::OUT_STATUS_NOT_SENT)
                ->required(),
            Forms\Components\TextInput::make('gop_google_drive_link')->label('Google Drive Link')->nullable(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultInState(File $file): array
    {
        $branchId = $file->provider_branch_id;
        $serviceTypeId = $file->service_type_id;
        $suggested = app(GopInOfferService::class)->suggestCostsForGopForm(
            $file,
            filled($branchId) ? (int) $branchId : null,
            filled($serviceTypeId) ? (int) $serviceTypeId : null,
        );

        return [
            'type' => 'In',
            'provider_branch_id' => $branchId,
            'service_type_id' => $serviceTypeId,
            'items' => static::serviceItemsOnly($suggested['items'] ?? []),
            'offered_cost' => $suggested['offered_cost'],
            'file_fee' => $suggested['file_fee'] ?? 0,
            'amount' => $suggested['total'],
            'offer_sections' => ClientOfferMessageFormatter::defaultSections(),
            'date' => now()->toDateString(),
            'status' => Gop::IN_STATUS_DRAFT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fillFromGop(Gop $gop): array
    {
        $data = $gop->toArray();
        $gop->loadMissing('items');
        $data['items'] = $gop->items
            ->filter(fn (GopItem $item): bool => $item->isServiceItem())
            ->map(fn (GopItem $item): array => [
                'description' => $item->description,
                'cost' => $item->cost,
                'selling_cost' => $item->selling_cost,
                'item_type' => GopItem::TYPE_SERVICE,
                'sort_order' => $item->sort_order,
            ])
            ->values()
            ->all();

        if ($data['items'] === [] && $gop->type === 'In' && (float) ($gop->offered_cost ?? 0) > 0) {
            $data['items'] = [[
                'description' => $gop->effective_service_type_name ?: 'Service',
                'cost' => round((float) $gop->offered_cost / max(1, (float) config('offer.selling_cost_multiplier', 2)), 2),
                'selling_cost' => (float) $gop->offered_cost,
                'item_type' => GopItem::TYPE_SERVICE,
                'sort_order' => 0,
            ]];
        }

        $data['offer_sections'] = app(ClientOfferMessageFormatter::class)
            ->normalizeSections($gop->offer_sections);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function persist(File $file, array $data, ?Gop $existing = null): Gop
    {
        if (($data['type'] ?? 'In') !== 'In') {
            unset($data['items'], $data['offer_sections']);

            if ($existing) {
                $existing->update($data);

                return $existing->fresh();
            }

            return $file->gops()->create($data);
        }

        $data = static::normalizeInData($data);

        return app(GopInOfferService::class)->saveInOffer($file, $data, $existing);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeInData(array $data): array
    {
        $data['amount'] = round(
            (float) ($data['offered_cost'] ?? 0) + (float) ($data['file_fee'] ?? 0),
            2,
        );

        if (filled($data['service_type_id'] ?? null)) {
            $data['service_type_other'] = null;
        } elseif (filled($data['service_type_other'] ?? null)) {
            $data['service_type_id'] = null;
            $data['service_type_other'] = trim((string) $data['service_type_other']);
        } else {
            $data['service_type_id'] = null;
            $data['service_type_other'] = null;
        }

        $data['offer_sections'] = app(ClientOfferMessageFormatter::class)
            ->normalizeSections($data['offer_sections'] ?? null);

        return $data;
    }

    public static function applySuggestedCosts(File $file, Set $set, Get $get): void
    {
        if ($get('type') !== 'In') {
            return;
        }

        $suggested = app(GopInOfferService::class)->suggestCostsForGopForm(
            $file,
            filled($get('provider_branch_id')) ? (int) $get('provider_branch_id') : null,
            filled($get('service_type_id')) ? (int) $get('service_type_id') : null,
        );

        if (($suggested['items'] ?? []) === [] && $suggested['offered_cost'] === null) {
            return;
        }

        $set('items', static::serviceItemsOnly($suggested['items'] ?? []));
        $set('offered_cost', $suggested['offered_cost']);
        $set('file_fee', $suggested['file_fee'] ?? 0);
        $set('amount', $suggested['total']);
    }

    public static function recalculateItemSelling(File $file, Set $set, Get $get): void
    {
        $cost = (float) ($get('cost') ?? 0);
        $serviceTypeId = filled($get('../../service_type_id'))
            ? (int) $get('../../service_type_id')
            : ($file->service_type_id ? (int) $file->service_type_id : null);
        $serviceTypeName = $get('description') ?: $file->serviceType?->name;

        $set('selling_cost', app(OfferPricingCalculator::class)->calculateSellingCost(
            $cost,
            $file,
            null,
            $serviceTypeId,
            $serviceTypeName,
        ));
    }

    public static function syncTotalsFromItems(File $file, Set $set, Get $get): void
    {
        $items = $get('items');
        $nested = ! is_array($items);

        if ($nested) {
            $items = $get('../../items') ?? [];
        }

        $type = $nested ? $get('../../type') : $get('type');

        if ($type !== 'In') {
            return;
        }

        if (! is_array($items)) {
            $items = [];
        }

        $rawServiceTypeId = $nested ? $get('../../service_type_id') : $get('service_type_id');
        $serviceTypeId = filled($rawServiceTypeId)
            ? (int) $rawServiceTypeId
            : ($file->service_type_id ? (int) $file->service_type_id : null);
        $calculator = app(OfferPricingCalculator::class);
        $withFee = $calculator->withFileFeeItem(
            static::serviceItemsOnly($items),
            $file,
            $serviceTypeId,
            $file->serviceType?->name,
        );
        $totals = $calculator->totals($withFee);
        $prefix = $nested ? '../../' : '';

        $set($prefix.'offered_cost', $totals['offered_cost']);
        $set($prefix.'file_fee', $totals['file_fee']);
        $set($prefix.'amount', $totals['total']);
    }

    public static function syncAmount(Set $set, Get $get): void
    {
        if (($get('type') ?? 'In') !== 'In') {
            return;
        }

        $set('amount', round(
            (float) ($get('offered_cost') ?? 0) + (float) ($get('file_fee') ?? 0),
            2,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function serviceItemsOnly(array $items): array
    {
        return array_values(array_filter(
            $items,
            fn (array $item): bool => ($item['item_type'] ?? GopItem::TYPE_SERVICE) !== GopItem::TYPE_FILE_FEE,
        ));
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function copyFormSchema(File $file, Gop $gop, string $mode): array
    {
        $formatter = app(ClientOfferMessageFormatter::class);
        $sections = $formatter->normalizeSections($gop->offer_sections);
        $preview = $formatter->format($file, $gop, $mode, $sections);

        return [
            Forms\Components\CheckboxList::make('offer_sections')
                ->label('Include')
                ->options(ClientOfferMessageFormatter::sectionOptions())
                ->default($sections)
                ->columns(3)
                ->live()
                ->afterStateUpdated(function (Set $set, Get $get) use ($file, $gop, $mode): void {
                    $set('message', app(ClientOfferMessageFormatter::class)->format(
                        $file,
                        $gop,
                        $mode,
                        Arr::wrap($get('offer_sections')),
                    ));
                }),
            Forms\Components\Textarea::make('message')
                ->label($mode === ClientOfferMessageFormatter::MODE_OFFER ? 'Client offer' : 'GOP / request')
                ->default($preview)
                ->rows(14)
                ->required(),
        ];
    }

    public static function copyToClipboard(string $text, string $label, $livewire): void
    {
        $escaped = json_encode($text, JSON_HEX_APOS | JSON_HEX_QUOT);

        Notification::make()
            ->title('Copied to clipboard')
            ->body("'{$label}' has been copied to your clipboard")
            ->success()
            ->send();

        $livewire->js("
            (function() {
                var textToCopy = {$escaped};
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(textToCopy);
                    return;
                }
                var area = document.createElement('textarea');
                area.value = textToCopy;
                area.style.position = 'fixed';
                area.style.left = '-9999px';
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                document.body.removeChild(area);
            })();
        ");
    }
}

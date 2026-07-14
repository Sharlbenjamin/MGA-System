<?php

namespace App\Filament\Resources\FileFeeResource\Pages\Concerns;

trait NormalizesFileFeeFormData
{
    protected function normalizeFileFeeData(array $data): array
    {
        $feeMode = $this->form->getState()['fee_mode'] ?? 'tier_package';

        $data['tier'] = null;

        if ($feeMode === 'tier_package') {
            $data['service_type_id'] = null;
            $data['amount'] = null;

            return $data;
        }

        $data['simple_amount'] = null;
        $data['middle_amount'] = null;
        $data['complex_amount'] = null;
        $data['simple_max_total'] = null;
        $data['middle_max_total'] = null;

        return $data;
    }

    protected function getFileFeeValidationRules(): array
    {
        $feeMode = $this->form->getState()['fee_mode'] ?? 'tier_package';

        if ($feeMode === 'tier_package') {
            return [
                'simple_amount' => ['nullable', 'numeric', 'min:0'],
                'middle_amount' => ['nullable', 'numeric', 'min:0'],
                'complex_amount' => ['nullable', 'numeric', 'min:0'],
                'simple_max_total' => ['nullable', 'numeric', 'min:0'],
                'middle_max_total' => ['nullable', 'numeric', 'min:0'],
            ];
        }

        return [
            'service_type_id' => ['required', 'exists:service_types,id'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function validateTierPackageHasAmount(): void
    {
        $feeMode = $this->form->getState()['fee_mode'] ?? 'tier_package';

        if ($feeMode !== 'tier_package') {
            return;
        }

        $state = $this->form->getState();
        $hasAmount = filled($state['simple_amount'])
            || filled($state['middle_amount'])
            || filled($state['complex_amount']);

        if (! $hasAmount) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'simple_amount' => 'Enter at least one tier amount (Standard, Middle, or Complex).',
            ]);
        }
    }
}

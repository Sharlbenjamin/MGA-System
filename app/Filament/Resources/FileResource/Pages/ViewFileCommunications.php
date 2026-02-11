<?php

namespace App\Filament\Resources\FileResource\Pages;

use App\Filament\Resources\FileResource;
use App\Models\CommunicationThread;
use Filament\Resources\Pages\ViewRecord;

class ViewFileCommunications extends ViewRecord
{
    protected static string $resource = FileResource::class;

    protected static string $view = 'filament.pages.files.communications-wireframe';

    protected static ?string $title = 'Communications';

    protected function getViewData(): array
    {
        $allCaseThreads = CommunicationThread::query()
            ->where('linked_file_id', $this->record->id)
            ->orderByDesc('last_message_at')
            ->get();

        $clientThreads = $allCaseThreads->where('category', 'client')->values();
        $providerThreads = $allCaseThreads->where('category', 'provider')->values();

        $activeView = request()->query('view', 'case');
        if (!in_array($activeView, ['case', 'ops'], true)) {
            $activeView = 'case';
        }

        $caseTab = request()->query('case_tab', 'client');
        $casePool = $caseTab === 'provider' ? $providerThreads : $clientThreads;
        if ($casePool->isEmpty()) {
            $casePool = $allCaseThreads;
        }

        $selectedCaseThread = null;
        if ($casePool->isNotEmpty()) {
            $selectedCaseThread = $casePool->firstWhere('id', (int) request()->query('thread_id')) ?? $casePool->first();
            $selectedCaseThread?->load(['messages' => fn ($q) => $q->with('attachments')->orderBy('sent_at')]);
        }

        $opsThreads = CommunicationThread::query()
            ->with('file:id,mga_reference,status')
            ->orderByDesc('last_message_at')
            ->limit(300)
            ->get();

        $selectedOpsThread = null;
        if ($opsThreads->isNotEmpty()) {
            $selectedOpsThread = $opsThreads->firstWhere('id', (int) request()->query('inbox_thread_id')) ?? $opsThreads->first();
            $selectedOpsThread?->load(['messages' => fn ($q) => $q->with('attachments')->orderBy('sent_at')]);
        }

        return [
            'file' => $this->record,
            'activeView' => $activeView,
            'caseTab' => $caseTab,
            'allCaseThreads' => $allCaseThreads,
            'clientThreads' => $clientThreads,
            'providerThreads' => $providerThreads,
            'selectedCaseThread' => $selectedCaseThread,
            'opsThreads' => $opsThreads,
            'selectedOpsThread' => $selectedOpsThread,
        ];
    }

    public function getBreadcrumb(): string
    {
        return 'Communications';
    }

    public function getTitle(): string
    {
        return 'Communications - ' . ($this->record->mga_reference ?? ('Case #' . $this->record->id));
    }
}

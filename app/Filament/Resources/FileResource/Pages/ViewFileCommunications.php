<?php

namespace App\Filament\Resources\FileResource\Pages;

use App\Filament\Resources\FileResource;
use App\Models\CommunicationAttachment;
use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\File;
use App\Models\ProviderBranch;
use App\Services\GmailImapPollingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Url;

class ViewFileCommunications extends ViewRecord
{
    protected static string $resource = FileResource::class;

    protected static string $view = 'filament.pages.files.communications-wireframe';

    protected static ?string $title = 'Communications';

    #[Url(as: 'view')]
    public string $activeView = 'case';

    #[Url(as: 'case_tab')]
    public string $caseTab = 'client';

    #[Url(as: 'thread_id')]
    public ?int $caseThreadId = null;

    #[Url(as: 'opsThread')]
    public ?int $opsThreadId = null;

    #[Url(as: 'ops_filter')]
    public string $opsFilter = 'general';

    #[Url(as: 'ops_unread')]
    public bool $opsUnreadOnly = false;

    #[Url(as: 'ops_linked')]
    public bool $opsLinkedOnly = false;

    #[Url(as: 'ops_category')]
    public string $opsCategory = 'all';

    public int $opsThreadsLimit = 60;
    public int $caseMessagesLimit = 25;
    public int $opsMessagesLimit = 25;

    public bool $showOpsReplyModal = false;
    public string $opsReplySubject = '';
    public string $opsReplyBody = '';

    public bool $showOpsForwardModal = false;
    public string $opsForwardTo = '';
    public string $opsForwardBody = '';

    public bool $showLinkModal = false;
    public string $linkSearch = '';
    public ?int $linkFileId = null;
    public string $linkType = 'client';
    public ?int $linkProviderBranchId = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $legacyOpsThread = (int) request()->query('inbox_thread_id', 0);
        if (!$this->opsThreadId && $legacyOpsThread > 0) {
            $this->opsThreadId = $legacyOpsThread;
        }

        $this->normalizeState();
    }

    public function selectOpsThread(int $threadId): void
    {
        $this->activeView = 'ops';
        $this->opsThreadId = $threadId;
        $this->opsMessagesLimit = 25;
    }

    public function setOpsFilter(string $filter): void
    {
        if (!in_array($filter, ['general', 'open_cases', 'unlinked', 'providers'], true)) {
            return;
        }

        $this->opsFilter = $filter;
        $this->opsThreadId = null;
    }

    public function toggleOpsUnreadOnly(): void
    {
        $this->opsUnreadOnly = !$this->opsUnreadOnly;
        $this->opsThreadId = null;
    }

    public function toggleOpsLinkedOnly(): void
    {
        $this->opsLinkedOnly = !$this->opsLinkedOnly;
        $this->opsThreadId = null;
    }

    public function setOpsCategory(string $category): void
    {
        if (!in_array($category, ['all', 'client', 'provider'], true)) {
            return;
        }

        $this->opsCategory = $category;
        $this->opsThreadId = null;
    }

    public function loadMoreOpsThreads(): void
    {
        $this->opsThreadsLimit = min(300, $this->opsThreadsLimit + 60);
    }

    public function loadOlderCaseMessages(): void
    {
        $this->caseMessagesLimit = min(100, $this->caseMessagesLimit + 25);
    }

    public function loadOlderOpsMessages(): void
    {
        $this->opsMessagesLimit = min(100, $this->opsMessagesLimit + 25);
    }

    public function openOpsReplyModal(): void
    {
        if (!$this->opsThreadId) {
            return;
        }

        $this->opsReplySubject = '';
        $this->opsReplyBody = '';
        $this->showOpsReplyModal = true;
    }

    public function closeOpsReplyModal(): void
    {
        $this->showOpsReplyModal = false;
    }

    public function sendOpsReply(): void
    {
        $validated = $this->validate([
            'opsReplyBody' => ['required', 'string'],
            'opsReplySubject' => ['nullable', 'string', 'max:255'],
        ]);

        $thread = CommunicationThread::query()->find($this->opsThreadId);
        if (!$thread) {
            Notification::make()->title('Thread not found')->danger()->send();
            return;
        }

        $latestIncoming = $thread->messages()
            ->where('direction', 'incoming')
            ->latest('sent_at')
            ->first();

        $toEmail = strtolower((string) ($latestIncoming->from_email ?? ''));
        if ($toEmail === '') {
            Notification::make()->title('No recipient found on this thread')->warning()->send();
            return;
        }

        $fromAddress = (string) config('mail.from.address', 'mga.operation@medguarda.com');
        $fromName = (string) config('mail.from.name', 'MGA Operation');
        $subject = $validated['opsReplySubject'] ?: ('Re: ' . ($thread->subject ?: 'Case Communication'));
        $body = (string) $validated['opsReplyBody'];

        Mail::raw($body, function ($message) use ($toEmail, $subject, $fromAddress, $fromName) {
            $message->from($fromAddress, $fromName)
                ->to($toEmail)
                ->subject($subject);
        });

        CommunicationMessage::create([
            'communication_thread_id' => $thread->id,
            'mailbox' => strtolower($fromAddress),
            'direction' => 'outgoing',
            'from_email' => strtolower($fromAddress),
            'from_name' => $fromName,
            'to_emails' => [$toEmail],
            'subject' => $subject,
            'body_text' => $body,
            'sent_at' => now(),
            'is_unread' => false,
            'has_attachments' => false,
            'metadata' => ['sent_via' => 'ops_view_reply'],
        ]);

        $participants = array_values(array_unique(array_map('strtolower', array_filter(array_merge(
            $thread->participants ?? [],
            [$fromAddress, $toEmail]
        )))));

        $thread->update([
            'participants' => $participants,
            'is_read' => true,
            'awaiting_reply' => false,
            'last_message_at' => now(),
        ]);

        $thread->messages()
            ->where('direction', 'incoming')
            ->where('is_unread', true)
            ->update(['is_unread' => false]);

        $this->showOpsReplyModal = false;
        Notification::make()->title('Reply sent')->success()->send();
    }

    public function openOpsForwardModal(): void
    {
        if (!$this->opsThreadId) {
            return;
        }

        $this->opsForwardTo = '';
        $this->opsForwardBody = '';
        $this->showOpsForwardModal = true;
    }

    public function closeOpsForwardModal(): void
    {
        $this->showOpsForwardModal = false;
    }

    public function sendOpsForward(): void
    {
        $validated = $this->validate([
            'opsForwardTo' => ['required', 'email'],
            'opsForwardBody' => ['nullable', 'string'],
        ]);

        $thread = CommunicationThread::query()->find($this->opsThreadId);
        if (!$thread) {
            Notification::make()->title('Thread not found')->danger()->send();
            return;
        }

        $latestMessage = $thread->messages()->latest('sent_at')->first();
        $summary = $latestMessage ? $latestMessage->display_body : '(No message body)';
        $summary = mb_substr($summary, 0, 4000);
        $body = trim($validated['opsForwardBody'] ?: ("Forwarded from thread: " . ($thread->subject ?: '(No subject)') . "\n\n" . $summary));

        $fromAddress = (string) config('mail.from.address', 'mga.operation@medguarda.com');
        $fromName = (string) config('mail.from.name', 'MGA Operation');
        $subject = 'Fwd: ' . ($thread->subject ?: ($latestMessage?->subject ?: 'Case Communication'));

        Mail::raw($body, function ($message) use ($validated, $subject, $fromAddress, $fromName) {
            $message->from($fromAddress, $fromName)
                ->to((string) $validated['opsForwardTo'])
                ->subject($subject);
        });

        CommunicationMessage::create([
            'communication_thread_id' => $thread->id,
            'mailbox' => strtolower($fromAddress),
            'direction' => 'outgoing',
            'from_email' => strtolower($fromAddress),
            'from_name' => $fromName,
            'to_emails' => [strtolower((string) $validated['opsForwardTo'])],
            'subject' => $subject,
            'body_text' => $body,
            'sent_at' => now(),
            'is_unread' => false,
            'has_attachments' => false,
            'metadata' => ['sent_via' => 'ops_view_forward'],
        ]);

        $thread->update([
            'is_read' => true,
            'awaiting_reply' => false,
            'last_message_at' => now(),
        ]);

        $this->showOpsForwardModal = false;
        Notification::make()->title('Forward sent')->success()->send();
    }

    public function openLinkToFileModal(): void
    {
        if (!$this->opsThreadId) {
            return;
        }

        $this->linkSearch = '';
        $this->linkFileId = null;
        $this->linkType = 'client';
        $this->linkProviderBranchId = null;
        $this->showLinkModal = true;
    }

    public function closeLinkToFileModal(): void
    {
        $this->showLinkModal = false;
    }

    public function linkOpsThreadToFile(): void
    {
        $validated = $this->validate([
            'linkFileId' => ['required', 'integer', 'exists:files,id'],
            'linkType' => ['required', 'in:client,provider'],
            'linkProviderBranchId' => ['nullable', 'integer', 'exists:provider_branches,id'],
        ]);

        $thread = CommunicationThread::query()->find($this->opsThreadId);
        if (!$thread) {
            Notification::make()->title('Thread not found')->danger()->send();
            return;
        }

        $metadata = $thread->metadata ?? [];
        $metadata['link_type'] = $validated['linkType'];
        $metadata['provider_branch_id'] = $validated['linkProviderBranchId'] ?? null;

        $thread->update([
            'linked_file_id' => (int) $validated['linkFileId'],
            'category' => $validated['linkType'],
            'metadata' => $metadata,
        ]);

        $this->showLinkModal = false;
        Notification::make()->title('Thread linked to file')->success()->send();
    }

    public function handleAttachmentAction(int $attachmentId, string $mode = 'open'): void
    {
        $attachment = CommunicationAttachment::query()->find($attachmentId);
        if (!$attachment) {
            Notification::make()
                ->title('Attachment download will be enabled after sync')
                ->body('This attachment is indexed as metadata only right now.')
                ->warning()
                ->send();
            return;
        }

        if ($attachment->url) {
            $this->redirect($attachment->url, navigate: false);
            return;
        }

        $this->redirect(route('files.communications.attachment', [
            'attachment' => $attachment->id,
            'mode' => $mode === 'download' ? 'download' : 'open',
        ]), navigate: false);
    }

    public function selectNextOpsThread(): void
    {
        if ($this->activeView !== 'ops') {
            return;
        }

        $ids = $this->getCurrentOpsThreadIds();
        if (empty($ids)) {
            return;
        }

        $currentIndex = array_search((int) $this->opsThreadId, $ids, true);
        if ($currentIndex === false) {
            $this->opsThreadId = (int) $ids[0];
            return;
        }

        $nextIndex = min(count($ids) - 1, $currentIndex + 1);
        $this->opsThreadId = (int) $ids[$nextIndex];
        $this->opsMessagesLimit = 25;
    }

    public function selectPrevOpsThread(): void
    {
        if ($this->activeView !== 'ops') {
            return;
        }

        $ids = $this->getCurrentOpsThreadIds();
        if (empty($ids)) {
            return;
        }

        $currentIndex = array_search((int) $this->opsThreadId, $ids, true);
        if ($currentIndex === false) {
            $this->opsThreadId = (int) $ids[0];
            return;
        }

        $prevIndex = max(0, $currentIndex - 1);
        $this->opsThreadId = (int) $ids[$prevIndex];
        $this->opsMessagesLimit = 25;
    }

    public function quickReplyShortcut(): void
    {
        if ($this->activeView === 'ops' && $this->opsThreadId) {
            $this->openOpsReplyModal();
        }
    }

    protected function getViewData(): array
    {
        $this->normalizeState();

        $allCaseThreads = collect();
        $clientThreads = collect();
        $providerThreads = collect();
        $selectedCaseThread = null;

        if ($this->activeView === 'case') {
            $allCaseThreads = CommunicationThread::query()
                ->with([
                    'latestMessage' => fn ($q) => $q->select([
                        'id',
                        'communication_thread_id',
                        'sent_at',
                        'body_text',
                        'direction',
                        'is_unread',
                    ]),
                ])
                ->where('linked_file_id', $this->record->id)
                ->orderByDesc('last_message_at')
                ->get();

            $clientThreads = $allCaseThreads->where('category', 'client')->values();
            $providerThreads = $allCaseThreads->where('category', 'provider')->values();

            $casePool = $this->caseTab === 'provider' ? $providerThreads : $clientThreads;
            if ($casePool->isEmpty()) {
                $casePool = $allCaseThreads;
            }

            if ($casePool->isNotEmpty()) {
                if (!$this->caseThreadId || !$casePool->firstWhere('id', $this->caseThreadId)) {
                    $this->caseThreadId = (int) $casePool->first()->id;
                }

                $selectedCaseThread = $casePool->firstWhere('id', $this->caseThreadId);
                if ($selectedCaseThread) {
                    $this->hydrateThreadMessages($selectedCaseThread, $this->caseMessagesLimit);
                }
            }
        }

        $opsThreads = collect();
        $selectedOpsThread = null;

        if ($this->activeView === 'ops') {
            $opsThreads = $this->getOpsThreadsQuery()
                ->limit($this->opsThreadsLimit)
                ->get();

            if ($opsThreads->isNotEmpty()) {
                if (!$this->opsThreadId || !$opsThreads->firstWhere('id', $this->opsThreadId)) {
                    $this->opsThreadId = (int) $opsThreads->first()->id;
                }

                $selectedOpsThread = $opsThreads->firstWhere('id', $this->opsThreadId);
                if ($selectedOpsThread) {
                    $this->hydrateThreadMessages($selectedOpsThread, $this->opsMessagesLimit);
                }
            }
        }

        $opsFilterCounts = $this->activeView === 'ops'
            ? $this->getOpsFilterCounts()
            : ['general' => 0, 'open_cases' => 0, 'unlinked' => 0, 'providers' => 0];

        return [
            'file' => $this->record,
            'activeView' => $this->activeView,
            'caseTab' => $this->caseTab,
            'allCaseThreads' => $allCaseThreads,
            'clientThreads' => $clientThreads,
            'providerThreads' => $providerThreads,
            'selectedCaseThread' => $selectedCaseThread,
            'opsThreads' => $opsThreads,
            'selectedOpsThread' => $selectedOpsThread,
            'opsFilter' => $this->opsFilter,
            'opsFilterCounts' => $opsFilterCounts,
            'opsUnreadOnly' => $this->opsUnreadOnly,
            'opsLinkedOnly' => $this->opsLinkedOnly,
            'opsCategory' => $this->opsCategory,
            'opsThreadsLimit' => $this->opsThreadsLimit,
            'opsMessagesLimit' => $this->opsMessagesLimit,
            'caseMessagesLimit' => $this->caseMessagesLimit,
            'linkFileOptions' => $this->getLinkFileOptions(),
            'providerBranchOptions' => $this->getProviderBranchOptions(),
        ];
    }

    protected function getOpsThreadsQuery(): Builder
    {
        $query = CommunicationThread::query()
            ->with([
                'file:id,mga_reference,status',
                'latestMessage' => fn ($q) => $q->select([
                    'id',
                    'communication_thread_id',
                    'sent_at',
                    'body_text',
                    'direction',
                    'is_unread',
                ]),
            ])
            ->orderByDesc('last_message_at');

        if ($this->opsFilter === 'general') {
            $query->where('category', 'general');
        } elseif ($this->opsFilter === 'open_cases') {
            $query->whereNotNull('linked_file_id');
        } elseif ($this->opsFilter === 'unlinked') {
            $query->whereNull('linked_file_id');
        } elseif ($this->opsFilter === 'providers') {
            $query->where('category', 'provider');
        }

        if ($this->opsUnreadOnly) {
            $query->where(function (Builder $q): void {
                $q->where('is_read', false)
                    ->orWhereHas('messages', fn (Builder $m) => $m->where('is_unread', true));
            });
        }

        if ($this->opsLinkedOnly) {
            $query->whereNotNull('linked_file_id');
        }

        if (in_array($this->opsCategory, ['client', 'provider'], true)) {
            $query->where('category', $this->opsCategory);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    protected function getOpsFilterCounts(): array
    {
        $row = CommunicationThread::query()
            ->selectRaw("SUM(CASE WHEN category = 'general' THEN 1 ELSE 0 END) as general_count")
            ->selectRaw("SUM(CASE WHEN linked_file_id IS NOT NULL THEN 1 ELSE 0 END) as open_cases_count")
            ->selectRaw("SUM(CASE WHEN linked_file_id IS NULL THEN 1 ELSE 0 END) as unlinked_count")
            ->selectRaw("SUM(CASE WHEN category = 'provider' THEN 1 ELSE 0 END) as providers_count")
            ->first();

        return [
            'general' => (int) ($row->general_count ?? 0),
            'open_cases' => (int) ($row->open_cases_count ?? 0),
            'unlinked' => (int) ($row->unlinked_count ?? 0),
            'providers' => (int) ($row->providers_count ?? 0),
        ];
    }

    protected function hydrateThreadMessages(CommunicationThread $thread, int $limit): void
    {
        $thread->load([
            'messages' => fn ($q) => $q
                ->with('attachments')
                ->orderByDesc('sent_at')
                ->limit($limit),
        ]);

        $thread->setRelation(
            'messages',
            $thread->messages->sortBy('sent_at')->values()
        );
    }

    /**
     * @return array<int, string>
     */
    protected function getLinkFileOptions(): array
    {
        if (!$this->showLinkModal) {
            return [];
        }

        $search = trim($this->linkSearch);
        $query = File::query()
            ->with('patient:id,name')
            ->select(['id', 'mga_reference', 'client_reference', 'patient_id'])
            ->latest('id');

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('mga_reference', 'like', '%' . $search . '%')
                    ->orWhere('client_reference', 'like', '%' . $search . '%')
                    ->orWhereHas('patient', fn (Builder $p) => $p->where('name', 'like', '%' . $search . '%'));
            });
        }

        return $query
            ->limit(15)
            ->get()
            ->mapWithKeys(fn (File $file) => [
                $file->id => ($file->mga_reference ?: ('Case #' . $file->id)) . ' - ' . ($file->patient?->name ?: 'Unknown patient'),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function getProviderBranchOptions(): array
    {
        if (!$this->showLinkModal || $this->linkType !== 'provider') {
            return [];
        }

        return ProviderBranch::query()
            ->orderBy('branch_name')
            ->limit(200)
            ->pluck('branch_name', 'id')
            ->toArray();
    }

    protected function normalizeState(): void
    {
        if (!in_array($this->activeView, ['case', 'ops'], true)) {
            $this->activeView = 'case';
        }

        if (!in_array($this->caseTab, ['client', 'provider'], true)) {
            $this->caseTab = 'client';
        }

        if (!in_array($this->opsFilter, ['general', 'open_cases', 'unlinked', 'providers'], true)) {
            $this->opsFilter = 'general';
        }

        if (!in_array($this->opsCategory, ['all', 'client', 'provider'], true)) {
            $this->opsCategory = 'all';
        }

        $this->opsThreadsLimit = max(60, min(300, $this->opsThreadsLimit));
        $this->caseMessagesLimit = max(25, min(100, $this->caseMessagesLimit));
        $this->opsMessagesLimit = max(25, min(100, $this->opsMessagesLimit));
    }

    /**
     * @return array<int, int>
     */
    protected function getCurrentOpsThreadIds(): array
    {
        return $this->getOpsThreadsQuery()
            ->limit($this->opsThreadsLimit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

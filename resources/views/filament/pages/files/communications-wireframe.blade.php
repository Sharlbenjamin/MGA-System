<x-filament-panels::page>
    <style>
        :root { --bg:#f1f4f9; --surface:#fff; --soft:#f8fafc; --line:#dfe3ea; --text:#1f2937; --muted:#6b7280; --client:#22c55e; --provider:#8b5cf6; --neutral:#9ca3af; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter,"Segoe UI",Roboto,Arial,sans-serif; background:var(--bg); color:var(--text); min-width:1240px; }
        .btn { border:1px solid var(--line); border-radius:10px; background:#fff; color:#1f2937; padding:7px 10px; font-size:11px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all .15s ease; }
        .btn:hover { transform:translateY(-1px); box-shadow:0 3px 10px rgba(15,23,42,.08); }
        .btn.primary { background:#e8f0fe; border-color:#bfdbfe; color:#1d4ed8; }
        .btn.success { background:#ecfdf3; border-color:#86efac; color:#166534; }
        .btn.warning { background:#fff7ed; border-color:#fdba74; color:#b45309; }
        .btn.danger { background:#fef2f2; border-color:#fca5a5; color:#b91c1c; }
        .btn.info { background:#eff6ff; border-color:#93c5fd; color:#1d4ed8; }
        .wrapper { padding:10px 12px 12px; }
        .screen-toggle { display:flex; gap:8px; margin-bottom:8px; }
        .pill { border:1px solid var(--line); border-radius:999px; padding:6px 10px; font-size:11px; font-weight:700; background:#fff; color:#64748b; text-decoration:none; }
        .pill.active { background:#e8f0fe; color:#1d4ed8; border-color:#bfdbfe; }
        .gmail-shell { border:1px solid var(--line); border-radius:14px; overflow:hidden; background:#fff; box-shadow:0 8px 24px rgba(15,23,42,.07); }
        .layout { display:grid; grid-template-columns:230px 430px 1fr; min-height:calc(100vh - 140px); }
        .sidebar { border-right:1px solid var(--line); background:#f7f9fc; padding:10px; }
        .compose { width:100%; border:0; border-radius:999px; background:#dbeafe; color:#1e3a8a; font-size:12px; font-weight:700; padding:8px 12px; margin-bottom:10px; }
        .nav { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:4px; }
        .nav li { border-radius:999px; padding:7px 10px; font-size:12px; font-weight:600; display:flex; justify-content:space-between; color:#334155; }
        .nav li.active { background:#dbeafe; color:#1e40af; }
        .count { border:1px solid var(--line); border-radius:999px; padding:2px 7px; background:#fff; font-size:10px; font-weight:700; color:#64748b; }
        .column { border-right:1px solid var(--line); display:flex; flex-direction:column; min-height:0; }
        .column-h { border-bottom:1px solid var(--line); padding:9px 12px; display:flex; justify-content:space-between; align-items:center; gap:8px; font-size:12px; font-weight:700; }
        .tabs { display:flex; gap:6px; flex-wrap:wrap; }
        .tab { border:1px solid var(--line); border-radius:999px; padding:5px 9px; font-size:10px; font-weight:700; color:#64748b; background:#fff; text-decoration:none; }
        .tab.active { background:#e8f0fe; color:#1d4ed8; border-color:#bfdbfe; }
        .list { margin:0; padding:0; list-style:none; overflow:auto; }
        .item { border-bottom:1px solid #edf0f4; padding:8px 10px; background:#f3f4f6; text-decoration:none; color:inherit; display:block; }
        .item.unread { background:#fff; border-left:3px solid #93c5fd; padding-left:9px; }
        .item.active { outline:2px solid #bfdbfe; outline-offset:-2px; }
        .item-btn { width:100%; text-align:left; border:0; font:inherit; cursor:pointer; }
        .item-top { display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:5px; }
        .time { font-size:11px; color:#64748b; }
        .badge { border-radius:999px; font-size:10px; font-weight:700; padding:3px 8px; border:1px solid transparent; }
        .badge.client { background:#ecfdf3; color:#15803d; border-color:#bbf7d0; }
        .badge.provider { background:#f3eeff; color:#6d28d9; border-color:#ddd6fe; }
        .badge.neutral { background:#f3f4f6; color:#4b5563; border-color:#e5e7eb; }
        .subject { font-size:12px; font-weight:700; color:#1e293b; margin-bottom:3px; }
        .preview { font-size:12px; color:#64748b; }
        .reader { display:flex; flex-direction:column; min-height:0; }
        .reader-h { border-bottom:1px solid var(--line); padding:9px 12px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .title { font-size:16px; font-weight:700; }
        .toolbar { border-bottom:1px solid var(--line); padding:8px 12px; display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap; }
        .body { padding:10px; overflow:auto; display:grid; gap:10px; }
        .msg { border:1px solid var(--line); border-left:4px solid var(--neutral); border-radius:10px; background:#fff; padding:8px 10px; font-size:12px; line-height:1.35; }
        .msg-body { white-space:pre-wrap; }
        .msg.unread { background:#fff; box-shadow:inset 0 0 0 1px #bfdbfe; }
        .msg.client { border-left-color:var(--client); }
        .msg.provider { border-left-color:var(--provider); }
        .msg.neutral { border-left-color:var(--neutral); background:#fafafa; }
        .meta { display:flex; gap:8px; align-items:center; margin-bottom:6px; font-size:11px; color:#64748b; font-weight:600; flex-wrap:wrap; }
        .attach { margin-top:8px; display:flex; gap:7px; flex-wrap:wrap; }
        .file { border:1px solid var(--line); border-radius:999px; padding:5px 10px; font-size:11px; font-weight:600; color:#334155; background:#fff; }
        .empty { padding:16px; color:var(--muted); font-size:13px; }
        .suggestions { border-top:1px solid var(--line); padding:10px 12px; display:grid; grid-template-columns:1fr 1fr; gap:10px; background:#fafbff; }
        .panel { border:1px solid var(--line); border-radius:12px; background:#fff; }
        .panel-h { padding:10px 12px; border-bottom:1px solid var(--line); font-size:13px; font-weight:700; }
        .panel-b { padding:10px 12px; }
        .row { border:1px dashed var(--line); border-radius:8px; padding:7px 8px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; gap:8px; font-size:13px; }
        .alert { margin:0 0 8px; padding:10px 12px; border:1px solid #86efac; background:#ecfdf3; color:#166534; border-radius:10px; font-size:13px; font-weight:600; }
        .chip-actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
        .chip-btn { border:1px solid var(--line); background:#fff; color:#1e3a8a; border-radius:999px; font-size:10px; font-weight:700; padding:4px 8px; cursor:pointer; }
        .toolbar-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(15,23,42,.35); z-index:70; }
        .modal { position:fixed; z-index:71; left:50%; top:50%; transform:translate(-50%,-50%); width:min(640px,95vw); background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow:0 24px 50px rgba(15,23,42,.2); }
        .modal-h { padding:12px 14px; border-bottom:1px solid var(--line); font-size:14px; font-weight:700; display:flex; justify-content:space-between; align-items:center; }
        .modal-b { padding:12px 14px; display:grid; gap:10px; }
    </style>
    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    <main class="wrapper">
        <div class="screen-toggle">
            <a class="pill {{ $activeView === 'case' ? 'active' : '' }}"
               href="{{ \App\Filament\Resources\FileResource::getUrl('communications', ['record' => $file->id, 'view' => 'case', 'case_tab' => $caseTab, 'thread_id' => $selectedCaseThread?->id]) }}">{{ $file->mga_reference ?? ('Case #' . $file->id) }}</a>
            <a class="pill {{ $activeView === 'ops' ? 'active' : '' }}"
               href="{{ \App\Filament\Resources\FileResource::getUrl('communications', ['record' => $file->id, 'view' => 'ops', 'case_tab' => $caseTab, 'opsThread' => $selectedOpsThread?->id]) }}">Operations Inbox View</a>
        </div>

        @if($activeView === 'case')
        <section class="gmail-shell" id="case-view">
            <div class="layout">
                <aside class="sidebar">
                    <button class="compose" type="button">✉️ Compose</button>
                    <ul class="nav">
                        <li class="active"><span>Linked Threads</span><span class="count">{{ $allCaseThreads->count() }}</span></li>
                        <li><span>Unread</span><span class="count">{{ $allCaseThreads->where('is_read', false)->count() }}</span></li>
                    </ul>
                </aside>

                <section class="column">
                    <div class="column-h">
                        <span>File: {{ $file->mga_reference ?? ('Case #' . $file->id) }} - Communications</span>
                        <div class="tabs">
                            <a class="tab {{ $caseTab === 'client' ? 'active' : '' }}" href="{{ \App\Filament\Resources\FileResource::getUrl('communications', ['record' => $file->id, 'view' => 'case', 'case_tab' => 'client', 'thread_id' => $selectedCaseThread?->id]) }}">Client</a>
                            <a class="tab {{ $caseTab === 'provider' ? 'active' : '' }}" href="{{ \App\Filament\Resources\FileResource::getUrl('communications', ['record' => $file->id, 'view' => 'case', 'case_tab' => 'provider', 'thread_id' => $selectedCaseThread?->id]) }}">Providers</a>
                        </div>
                    </div>
                    <ul class="list">
                        @php
                            $threadPool = $caseTab === 'provider' ? $providerThreads : $clientThreads;
                            if ($threadPool->isEmpty()) { $threadPool = $allCaseThreads; }
                        @endphp
                        @forelse ($threadPool as $thread)
                            @php
                                $latest = $thread->latestMessage->first();
                                $active = $selectedCaseThread && $selectedCaseThread->id === $thread->id;
                                $badgeClass = $thread->category === 'client' ? 'client' : ($thread->category === 'provider' ? 'provider' : 'neutral');
                            @endphp
                            <li>
                                <a class="item {{ !$thread->is_read ? 'unread' : '' }} {{ $active ? 'active' : '' }}"
                                   href="{{ \App\Filament\Resources\FileResource::getUrl('communications', ['record' => $file->id, 'view' => 'case', 'case_tab' => $caseTab, 'thread_id' => $thread->id]) }}">
                                    <div class="item-top">
                                        <span class="badge {{ $badgeClass }}">{{ ucfirst($thread->category) }}</span>
                                        <span class="time">{{ optional($thread->last_message_at)->format('d M H:i') ?? '—' }}</span>
                                    </div>
                                    <div class="subject">{{ $thread->subject ?: '(No subject)' }}</div>
                                    <div class="preview">{{ \Illuminate\Support\Str::limit((string) (optional($latest)->body_text ?: 'No message body'), 72) }}</div>
                                </a>
                            </li>
                        @empty
                            <li class="empty">No linked threads yet for this case.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="reader">
                    <div class="reader-h">
                        <div class="title">{{ $selectedCaseThread?->subject ?: 'No thread selected' }}</div>
                        <div style="display:flex; gap:8px;">
                            @if($selectedCaseThread)
                                <form method="POST" action="{{ route('files.communications.mark-read', ['file' => $file->id, 'thread' => $selectedCaseThread->id, 'view' => 'case', 'case_tab' => $caseTab]) }}">
                                    @csrf
                                    <button class="btn" type="submit">✅ Mark Read</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="toolbar">
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <span class="badge neutral">Unread: {{ $selectedCaseThread?->messages?->where('is_unread', true)->count() ?? 0 }}</span>
                            <span class="badge neutral">Awaiting Reply: {{ $selectedCaseThread && $selectedCaseThread->awaiting_reply ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>
                    <div class="body">
                        @if($selectedCaseThread)
                            @forelse($selectedCaseThread->messages as $message)
                                @php
                                    $typeClass = $selectedCaseThread->category === 'client' ? 'client' : ($selectedCaseThread->category === 'provider' ? 'provider' : 'neutral');
                                    if ($message->direction === 'outgoing') { $typeClass = 'neutral'; }
                                    $fromEmail = strtolower((string) ($message->from_email ?? ''));
                                    $senderLabel = 'Contact';
                                    if (
                                        $message->direction === 'outgoing' ||
                                        str_contains($fromEmail, 'mga.operation@medguarda.com') ||
                                        str_contains($fromEmail, 'medguarda.com')
                                    ) {
                                        $senderLabel = 'MGA';
                                    } elseif ($selectedCaseThread->category === 'provider') {
                                        $senderLabel = 'Provider';
                                    } elseif ($selectedCaseThread->category === 'client') {
                                        $senderLabel = 'Client';
                                    }
                                    $senderBadgeClass = $senderLabel === 'Provider' ? 'provider' : ($senderLabel === 'Client' ? 'client' : 'neutral');
                                @endphp
                                <article class="msg {{ $typeClass }} {{ $message->is_unread ? 'unread' : '' }}">
                                    <div class="meta">
                                        <span>{{ optional($message->sent_at)->format('d M Y H:i') ?? '—' }}</span>
                                        <span class="badge {{ $senderBadgeClass }}">FROM {{ strtoupper($senderLabel) }}</span>
                                    </div>
                                    <div class="msg-body">{{ $message->display_body ?: '(No body)' }}</div>
                                    @if($message->attachments->isNotEmpty())
                                        <div class="attach">
                                            @foreach($message->attachments as $attachment)
                                                <div class="chip-actions">
                                                    <span class="file">📎 {{ $attachment->filename ?: 'Attachment' }}</span>
                                                    <button type="button" class="chip-btn" wire:click="handleAttachmentAction({{ $attachment->id }}, 'open')">👁️ Open</button>
                                                    <button type="button" class="chip-btn" wire:click="handleAttachmentAction({{ $attachment->id }}, 'download')">⬇️ Download</button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @empty
                                <div class="empty">No messages in this thread.</div>
                            @endforelse
                        @else
                            <div class="empty">No thread selected.</div>
                        @endif
                        @if($selectedCaseThread && $selectedCaseThread->messages->count() >= $caseMessagesLimit && $caseMessagesLimit < 100)
                            <div>
                                <button class="btn" type="button" wire:click="loadOlderCaseMessages">⬆️ Load older messages</button>
                            </div>
                        @endif
                    </div>
                    <div class="toolbar">
                        @if($selectedCaseThread)
                            <form method="POST" action="{{ route('files.communications.reply', ['file' => $file->id, 'thread' => $selectedCaseThread->id, 'view' => 'case', 'case_tab' => $caseTab]) }}" style="display:grid; gap:8px; width:100%;">
                                @csrf
                                <input class="btn" style="text-align:left;" name="subject" placeholder="Subject (optional)" />
                                <textarea class="btn" style="height:90px; border-radius:12px; font-weight:500;" name="body" placeholder="Write reply..." required>{{ old('body') }}</textarea>
                                <div style="display:flex; justify-content:flex-end; gap:8px;">
                                    <button class="btn primary" type="submit">📨 Send Reply</button>
                                </div>
                            </form>
                        @else
                            <div class="empty">Select a thread to reply.</div>
                        @endif
                    </div>
                </section>
            </div>
        </section>
        @endif

        @if($activeView === 'ops')
        <section class="gmail-shell" id="ops-view">
            <div class="layout">
                <aside class="sidebar">
                    <button class="compose" type="button">✉️ Compose</button>
                    <ul class="nav">
                        <li class="active"><span>Operations Inbox</span><span class="count">{{ $opsThreads->count() }}</span></li>
                        <li><span>Unread</span><span class="count">{{ $opsThreads->where('is_read', false)->count() }}</span></li>
                    </ul>
                </aside>

                <section class="column">
                    <div class="column-h">
                        <span>Operations Inbox</span>
                        <div class="tabs">
                            <button type="button" class="tab {{ $opsFilter === 'general' ? 'active' : '' }}" wire:click="setOpsFilter('general')">📥 General ({{ $opsFilterCounts['general'] ?? 0 }})</button>
                            <button type="button" class="tab {{ $opsFilter === 'open_cases' ? 'active' : '' }}" wire:click="setOpsFilter('open_cases')">📂 Open Cases ({{ $opsFilterCounts['open_cases'] ?? 0 }})</button>
                            <button type="button" class="tab {{ $opsFilter === 'unlinked' ? 'active' : '' }}" wire:click="setOpsFilter('unlinked')">🧩 Unlinked ({{ $opsFilterCounts['unlinked'] ?? 0 }})</button>
                            <button type="button" class="tab {{ $opsFilter === 'providers' ? 'active' : '' }}" wire:click="setOpsFilter('providers')">🏥 Providers ({{ $opsFilterCounts['providers'] ?? 0 }})</button>
                        </div>
                    </div>
                    <div class="toolbar">
                        <div class="toolbar-actions">
                            <button type="button" class="tab {{ $opsUnreadOnly ? 'active' : '' }}" wire:click="toggleOpsUnreadOnly">👀 Unread only</button>
                            <button type="button" class="tab {{ $opsLinkedOnly ? 'active' : '' }}" wire:click="toggleOpsLinkedOnly">🔗 Linked only</button>
                            <button type="button" class="tab {{ $opsCategory === 'all' ? 'active' : '' }}" wire:click="setOpsCategory('all')">📋 All</button>
                            <button type="button" class="tab {{ $opsCategory === 'client' ? 'active' : '' }}" wire:click="setOpsCategory('client')">🧑 Client only</button>
                            <button type="button" class="tab {{ $opsCategory === 'provider' ? 'active' : '' }}" wire:click="setOpsCategory('provider')">🏥 Provider only</button>
                        </div>
                    </div>
                    <ul class="list">
                        @forelse($opsThreads as $thread)
                            @php
                                $latest = $thread->latestMessage->first();
                                $badgeClass = $thread->category === 'client' ? 'client' : ($thread->category === 'provider' ? 'provider' : 'neutral');
                                $active = $selectedOpsThread && $selectedOpsThread->id === $thread->id;
                            @endphp
                            <li>
                                <button type="button"
                                    class="item item-btn {{ !$thread->is_read ? 'unread' : '' }} {{ $active ? 'active' : '' }}"
                                    wire:click="selectOpsThread({{ $thread->id }})">
                                    <div class="item-top">
                                        <span class="badge {{ $badgeClass }}">{{ ucfirst($thread->category) }}</span>
                                        <span class="time">{{ optional($thread->last_message_at)->format('d M H:i') ?? '—' }}</span>
                                    </div>
                                    <div class="subject">{{ $thread->subject ?: '(No subject)' }}</div>
                                    <div class="preview">
                                        {{ \Illuminate\Support\Str::limit((string) (optional($latest)->body_text ?: 'No message body'), 70) }}
                                        @if($thread->file) · {{ $thread->file->mga_reference ?: ('Case #' . $thread->file->id) }} @endif
                                    </div>
                                </button>
                            </li>
                        @empty
                            <li class="empty">No inbox threads yet.</li>
                        @endforelse
                    </ul>
                    <div class="toolbar">
                        <button class="btn" type="button" wire:click="loadMoreOpsThreads">⬇️ Load more threads</button>
                    </div>
                </section>

                <section class="reader">
                    <div class="reader-h">
                        <div class="title">{{ $selectedOpsThread?->subject ?: 'Thread Viewer' }}</div>
                    </div>
                    <div class="toolbar">
                        <div class="toolbar-actions">
                            <button class="btn" type="button" wire:click="openOpsReplyModal" @disabled(!$selectedOpsThread)>↩️ Reply</button>
                            <button class="btn" type="button" wire:click="openOpsForwardModal" @disabled(!$selectedOpsThread)>➡️ Forward</button>
                            <button class="btn" type="button" wire:click="openLinkToFileModal" @disabled(!$selectedOpsThread)>🔗 Link to File</button>
                        </div>
                    </div>
                    <div class="body">
                        @if($selectedOpsThread)
                            @foreach($selectedOpsThread->messages as $message)
                                @php
                                    $typeClass = $selectedOpsThread->category === 'client' ? 'client' : ($selectedOpsThread->category === 'provider' ? 'provider' : 'neutral');
                                    if ($message->direction === 'outgoing') { $typeClass = 'neutral'; }
                                    $fromEmail = strtolower((string) ($message->from_email ?? ''));
                                    $senderLabel = 'Contact';
                                    if (
                                        $message->direction === 'outgoing' ||
                                        str_contains($fromEmail, 'mga.operation@medguarda.com') ||
                                        str_contains($fromEmail, 'medguarda.com')
                                    ) {
                                        $senderLabel = 'MGA';
                                    } elseif ($selectedOpsThread->category === 'provider') {
                                        $senderLabel = 'Provider';
                                    } elseif ($selectedOpsThread->category === 'client') {
                                        $senderLabel = 'Client';
                                    }
                                    $senderBadgeClass = $senderLabel === 'Provider' ? 'provider' : ($senderLabel === 'Client' ? 'client' : 'neutral');
                                @endphp
                                <article class="msg {{ $typeClass }} {{ $message->is_unread ? 'unread' : '' }}">
                                    <div class="meta">
                                        <span>{{ optional($message->sent_at)->format('d M Y H:i') ?? '—' }}</span>
                                        <span class="badge {{ $senderBadgeClass }}">FROM {{ strtoupper($senderLabel) }}</span>
                                    </div>
                                    <div class="msg-body">{{ $message->display_body ?: '(No body)' }}</div>
                                    @if($message->attachments->isNotEmpty())
                                        <div class="attach">
                                            @foreach($message->attachments as $attachment)
                                                <div class="chip-actions">
                                                    <span class="file">📎 {{ $attachment->filename ?: 'Attachment' }}</span>
                                                    <button type="button" class="chip-btn" wire:click="handleAttachmentAction({{ $attachment->id }}, 'open')">👁️ Open</button>
                                                    <button type="button" class="chip-btn" wire:click="handleAttachmentAction({{ $attachment->id }}, 'download')">⬇️ Download</button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        @else
                            <div class="empty">No thread selected in inbox.</div>
                        @endif
                        @if($selectedOpsThread && $selectedOpsThread->messages->count() >= $opsMessagesLimit && $opsMessagesLimit < 100)
                            <div>
                                <button class="btn" type="button" wire:click="loadOlderOpsMessages">⬆️ Load older messages</button>
                            </div>
                        @endif
                    </div>
                    <div class="suggestions">
                        <section class="panel">
                            <div class="panel-h">Thread Details</div>
                            <div class="panel-b">
                                <div class="row"><span>Linked File</span><span>{{ $selectedOpsThread?->file?->mga_reference ?? 'Unlinked' }}</span></div>
                                <div class="row"><span>Participants</span><span>{{ implode(', ', array_slice($selectedOpsThread?->participants ?? [], 0, 3)) ?: '—' }}</span></div>
                            </div>
                        </section>
                        <section class="panel">
                            <div class="panel-h">Status</div>
                            <div class="panel-b">
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <span class="badge neutral">Read: {{ $selectedOpsThread && $selectedOpsThread->is_read ? 'Yes' : 'No' }}</span>
                                    <span class="badge neutral">Awaiting Reply: {{ $selectedOpsThread && $selectedOpsThread->awaiting_reply ? 'Yes' : 'No' }}</span>
                                </div>
                            </div>
                        </section>
                    </div>
                </section>
            </div>
        </section>
        @endif

        @if($showOpsReplyModal)
            <div class="modal-backdrop" wire:click="closeOpsReplyModal"></div>
            <div class="modal">
                <div class="modal-h">
                    <span>Reply to thread</span>
                    <button type="button" class="btn" wire:click="closeOpsReplyModal">✖️ Close</button>
                </div>
                <form class="modal-b" wire:submit="sendOpsReply">
                    <input class="btn" style="text-align:left;" wire:model.defer="opsReplySubject" placeholder="Subject (optional)" />
                    <textarea class="btn" style="height:120px; border-radius:12px; font-weight:500;" wire:model.defer="opsReplyBody" placeholder="Write reply..." required></textarea>
                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button class="btn primary" type="submit">📨 Send Reply</button>
                    </div>
                </form>
            </div>
        @endif

        @if($showOpsForwardModal)
            <div class="modal-backdrop" wire:click="closeOpsForwardModal"></div>
            <div class="modal">
                <div class="modal-h">
                    <span>Forward thread message</span>
                    <button type="button" class="btn" wire:click="closeOpsForwardModal">✖️ Close</button>
                </div>
                <form class="modal-b" wire:submit="sendOpsForward">
                    <input class="btn" style="text-align:left;" wire:model.defer="opsForwardTo" placeholder="Forward to email" type="email" required />
                    <textarea class="btn" style="height:120px; border-radius:12px; font-weight:500;" wire:model.defer="opsForwardBody" placeholder="Optional note before forwarded content..."></textarea>
                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button class="btn primary" type="submit">➡️ Send Forward</button>
                    </div>
                </form>
            </div>
        @endif

        @if($showLinkModal)
            <div class="modal-backdrop" wire:click="closeLinkToFileModal"></div>
            <div class="modal">
                <div class="modal-h">
                    <span>Link thread to file</span>
                    <button type="button" class="btn" wire:click="closeLinkToFileModal">✖️ Close</button>
                </div>
                <form class="modal-b" wire:submit="linkOpsThreadToFile">
                    <input class="btn" style="text-align:left;" wire:model.live.debounce.300ms="linkSearch" placeholder="Search MGA ref / patient / client ref" />
                    <select class="btn" wire:model.defer="linkFileId" required>
                        <option value="">Select file</option>
                        @foreach($linkFileOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select class="btn" wire:model.defer="linkType" required>
                        <option value="client">Client thread</option>
                        <option value="provider">Provider thread</option>
                    </select>
                    @if($linkType === 'provider')
                        <select class="btn" wire:model.defer="linkProviderBranchId">
                            <option value="">Provider branch (optional)</option>
                            @foreach($providerBranchOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button class="btn primary" type="submit">🔗 Link</button>
                    </div>
                </form>
            </div>
        @endif
    </main>
    <script data-livewire-id="{{ $this->getId() }}">
        (function () {
            const componentId = document.currentScript?.dataset?.livewireId;
            if (!componentId || !window.Livewire) {
                return;
            }

            document.addEventListener('keydown', function (event) {
                const target = event.target;
                const tag = (target?.tagName || '').toLowerCase();
                const isTyping = target?.isContentEditable || tag === 'input' || tag === 'textarea' || tag === 'select';
                if (isTyping) {
                    return;
                }

                const component = window.Livewire.find(componentId);
                if (!component) {
                    return;
                }

                if (event.key === 'j') {
                    event.preventDefault();
                    component.call('selectNextOpsThread');
                } else if (event.key === 'k') {
                    event.preventDefault();
                    component.call('selectPrevOpsThread');
                } else if (event.key === 'r') {
                    event.preventDefault();
                    component.call('quickReplyShortcut');
                }
            });
        })();
    </script>
</x-filament-panels::page>

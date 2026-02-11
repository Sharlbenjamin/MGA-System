<x-filament-panels::page>
    <style>
        :root { --bg:#f1f4f9; --surface:#fff; --soft:#f8fafc; --line:#dfe3ea; --text:#1f2937; --muted:#6b7280; --client:#22c55e; --provider:#8b5cf6; --neutral:#9ca3af; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter,"Segoe UI",Roboto,Arial,sans-serif; background:var(--bg); color:var(--text); min-width:1240px; }
        .appbar { position:sticky; top:0; z-index:40; background:rgba(241,244,249,.95); backdrop-filter:blur(8px); border-bottom:1px solid var(--line); padding:10px 16px; display:grid; grid-template-columns:280px 1fr auto; gap:12px; align-items:center; }
        .brand { font-weight:700; font-size:18px; display:flex; gap:8px; align-items:center; }
        .dot { width:12px; height:12px; border-radius:999px; background:linear-gradient(120deg,#22c55e,#8b5cf6); }
        .search { border:1px solid var(--line); background:#eaf1fb; border-radius:999px; padding:10px 14px; font-size:13px; color:#475569; }
        .top-actions { display:flex; gap:8px; }
        .btn { border:1px solid var(--line); border-radius:10px; background:#fff; color:#1f2937; padding:8px 12px; font-size:12px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
        .btn.primary { background:#e8f0fe; border-color:#bfdbfe; color:#1d4ed8; }
        .btn.success { background:#ecfdf3; border-color:#86efac; color:#166534; }
        .btn.warning { background:#fff7ed; border-color:#fdba74; color:#b45309; }
        .btn.danger { background:#fef2f2; border-color:#fca5a5; color:#b91c1c; }
        .btn.info { background:#eff6ff; border-color:#93c5fd; color:#1d4ed8; }
        .wrapper { padding:12px 16px 16px; }
        .screen-toggle { display:flex; gap:8px; margin-bottom:8px; }
        .pill { border:1px solid var(--line); border-radius:999px; padding:7px 12px; font-size:12px; font-weight:700; background:#fff; color:#64748b; text-decoration:none; }
        .pill.active { background:#e8f0fe; color:#1d4ed8; border-color:#bfdbfe; }
        .gmail-shell { border:1px solid var(--line); border-radius:18px; overflow:hidden; background:#fff; box-shadow:0 8px 26px rgba(15,23,42,.08); }
        .layout { display:grid; grid-template-columns:230px 430px 1fr; min-height:calc(100vh - 140px); }
        .sidebar { border-right:1px solid var(--line); background:#f7f9fc; padding:14px; }
        .compose { width:100%; border:0; border-radius:999px; background:#dbeafe; color:#1e3a8a; font-size:13px; font-weight:700; padding:11px 16px; margin-bottom:12px; }
        .nav { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:4px; }
        .nav li { border-radius:999px; padding:8px 12px; font-size:13px; font-weight:600; display:flex; justify-content:space-between; color:#334155; }
        .nav li.active { background:#dbeafe; color:#1e40af; }
        .count { border:1px solid var(--line); border-radius:999px; padding:2px 7px; background:#fff; font-size:10px; font-weight:700; color:#64748b; }
        .column { border-right:1px solid var(--line); display:flex; flex-direction:column; min-height:0; }
        .column-h { border-bottom:1px solid var(--line); padding:12px 14px; display:flex; justify-content:space-between; align-items:center; gap:8px; font-size:13px; font-weight:700; }
        .tabs { display:flex; gap:6px; flex-wrap:wrap; }
        .tab { border:1px solid var(--line); border-radius:999px; padding:6px 10px; font-size:11px; font-weight:700; color:#64748b; background:#fff; text-decoration:none; }
        .tab.active { background:#e8f0fe; color:#1d4ed8; border-color:#bfdbfe; }
        .list { margin:0; padding:0; list-style:none; overflow:auto; }
        .item { border-bottom:1px solid #edf0f4; padding:10px 12px; background:#f3f4f6; text-decoration:none; color:inherit; display:block; }
        .item.unread { background:#fff; border-left:3px solid #93c5fd; padding-left:9px; }
        .item.active { outline:2px solid #bfdbfe; outline-offset:-2px; }
        .item-top { display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:5px; }
        .time { font-size:11px; color:#64748b; }
        .badge { border-radius:999px; font-size:10px; font-weight:700; padding:3px 8px; border:1px solid transparent; }
        .badge.client { background:#ecfdf3; color:#15803d; border-color:#bbf7d0; }
        .badge.provider { background:#f3eeff; color:#6d28d9; border-color:#ddd6fe; }
        .badge.neutral { background:#f3f4f6; color:#4b5563; border-color:#e5e7eb; }
        .subject { font-size:12px; font-weight:700; color:#1e293b; margin-bottom:3px; }
        .preview { font-size:12px; color:#64748b; }
        .reader { display:flex; flex-direction:column; min-height:0; }
        .reader-h { border-bottom:1px solid var(--line); padding:12px 14px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .title { font-size:18px; font-weight:700; }
        .toolbar { border-bottom:1px solid var(--line); padding:10px 14px; display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap; }
        .body { padding:14px; overflow:auto; display:grid; gap:12px; }
        .msg { border:1px solid var(--line); border-left:4px solid var(--neutral); border-radius:12px; background:#fff; padding:10px 11px; font-size:13px; line-height:1.35; }
        .msg-body { white-space:pre-wrap; }
        .msg.unread { box-shadow:inset 0 0 0 1px #bfdbfe; }
        .msg.client { border-left-color:var(--client); }
        .msg.provider { border-left-color:var(--provider); }
        .msg.neutral { border-left-color:var(--neutral); background:#fafafa; }
        .meta { display:flex; gap:8px; align-items:center; margin-bottom:6px; font-size:11px; color:#64748b; font-weight:600; flex-wrap:wrap; }
        .attach { margin-top:8px; display:flex; gap:7px; flex-wrap:wrap; }
        .file { border:1px solid var(--line); border-radius:999px; padding:5px 10px; font-size:11px; font-weight:600; color:#334155; background:#fff; }
        .empty { padding:16px; color:var(--muted); font-size:13px; }
        .suggestions { border-top:1px solid var(--line); padding:12px 14px 14px; display:grid; grid-template-columns:1fr 1fr; gap:12px; background:#fafbff; }
        .panel { border:1px solid var(--line); border-radius:12px; background:#fff; }
        .panel-h { padding:10px 12px; border-bottom:1px solid var(--line); font-size:13px; font-weight:700; }
        .panel-b { padding:10px 12px; }
        .row { border:1px dashed var(--line); border-radius:8px; padding:7px 8px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; gap:8px; font-size:13px; }
        .alert { margin:8px 16px 0; padding:10px 12px; border:1px solid #86efac; background:#ecfdf3; color:#166534; border-radius:10px; font-size:13px; font-weight:600; }
    </style>
    <header class="appbar">
        <div class="brand"><span class="dot"></span>MGA System</div>
        <div class="search">Case: {{ $file->mga_reference ?? ('#' . $file->id) }} · {{ $allCaseThreads->count() }} linked thread(s)</div>
        <div class="top-actions">
            <a class="btn" href="{{ \App\Filament\Resources\FileResource::getUrl('communications', ['record' => $file->id, 'view' => $activeView, 'case_tab' => $caseTab, 'thread_id' => $selectedCaseThread?->id, 'inbox_thread_id' => $selectedOpsThread?->id]) }}">Refresh</a>
            <a class="btn" href="{{ route('filament.admin.resources.files.view', ['record' => $file->id]) }}">Back to File</a>
        </div>
    </header>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    <main class="wrapper">
        <div class="screen-toggle">
            <a class="pill {{ $activeView === 'case' ? 'active' : '' }}"
               href="{{ \App\Filament\Resources\FileResource::getUrl('communications', ['record' => $file->id, 'view' => 'case', 'case_tab' => $caseTab, 'thread_id' => $selectedCaseThread?->id]) }}">Case-Level View</a>
            <a class="pill {{ $activeView === 'ops' ? 'active' : '' }}"
               href="{{ \App\Filament\Resources\FileResource::getUrl('communications', ['record' => $file->id, 'view' => 'ops', 'case_tab' => $caseTab, 'inbox_thread_id' => $selectedOpsThread?->id]) }}">Operations Inbox View</a>
        </div>

        @if($activeView === 'case')
        <section class="gmail-shell" id="case-view">
            <div class="layout">
                <aside class="sidebar">
                    <button class="compose" type="button">+ Compose</button>
                    <ul class="nav">
                        <li class="active"><span>Linked Threads</span><span class="count">{{ $allCaseThreads->count() }}</span></li>
                        <li><span>Client</span><span class="count">{{ $clientThreads->count() }}</span></li>
                        <li><span>Providers</span><span class="count">{{ $providerThreads->count() }}</span></li>
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
                                $latest = $thread->messages()->latest('sent_at')->first();
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
                                    <div class="preview">{{ \Illuminate\Support\Str::limit(optional($latest)->display_body ?: 'No message body', 72) }}</div>
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
                                    <button class="btn" type="submit">Mark Read</button>
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
                                @endphp
                                <article class="msg {{ $typeClass }} {{ $message->is_unread ? 'unread' : '' }}">
                                    <div class="meta">
                                        <span>{{ optional($message->sent_at)->format('d M Y H:i') ?? '—' }}</span>
                                        <span class="badge {{ $typeClass }}">{{ strtoupper($message->direction) }}</span>
                                        <span>{{ $message->from_email }}</span>
                                    </div>
                                    <div class="msg-body">{{ $message->display_body ?: '(No body)' }}</div>
                                    @if($message->attachments->isNotEmpty())
                                        <div class="attach">
                                            @foreach($message->attachments as $attachment)
                                                <span class="file">📎 {{ $attachment->filename ?: 'Attachment' }}</span>
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
                    </div>
                    <div class="toolbar">
                        @if($selectedCaseThread)
                            <form method="POST" action="{{ route('files.communications.reply', ['file' => $file->id, 'thread' => $selectedCaseThread->id, 'view' => 'case', 'case_tab' => $caseTab]) }}" style="display:grid; gap:8px; width:100%;">
                                @csrf
                                <input class="btn" style="text-align:left;" name="subject" placeholder="Subject (optional)" />
                                <textarea class="btn" style="height:90px; border-radius:12px; font-weight:500;" name="body" placeholder="Write reply..." required>{{ old('body') }}</textarea>
                                <div style="display:flex; justify-content:flex-end; gap:8px;">
                                    <button class="btn primary" type="submit">Send Reply</button>
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
                    <button class="compose" type="button">+ Compose</button>
                    <ul class="nav">
                        <li class="active"><span>Operations Inbox</span><span class="count">{{ $opsThreads->count() }}</span></li>
                        <li><span>General</span><span class="count">{{ $opsThreads->where('category','general')->count() }}</span></li>
                        <li><span>Open Cases</span><span class="count">{{ $opsThreads->whereNotNull('linked_file_id')->count() }}</span></li>
                        <li><span>Unlinked</span><span class="count">{{ $opsThreads->whereNull('linked_file_id')->count() }}</span></li>
                        <li><span>Providers</span><span class="count">{{ $opsThreads->where('category','provider')->count() }}</span></li>
                    </ul>
                </aside>

                <section class="column">
                    <div class="column-h">
                        <span>Operations Inbox</span>
                        <div class="tabs">
                            <span class="tab active">General</span>
                            <span class="tab">Open Cases</span>
                            <span class="tab">Unlinked</span>
                        </div>
                    </div>
                    <ul class="list">
                        @forelse($opsThreads as $thread)
                            @php
                                $latest = $thread->messages()->latest('sent_at')->first();
                                $badgeClass = $thread->category === 'client' ? 'client' : ($thread->category === 'provider' ? 'provider' : 'neutral');
                                $active = $selectedOpsThread && $selectedOpsThread->id === $thread->id;
                            @endphp
                            <li>
                                <a class="item {{ !$thread->is_read ? 'unread' : '' }} {{ $active ? 'active' : '' }}"
                                   href="{{ \App\Filament\Resources\FileResource::getUrl('communications', ['record' => $file->id, 'view' => 'ops', 'case_tab' => $caseTab, 'inbox_thread_id' => $thread->id]) }}">
                                    <div class="item-top">
                                        <span class="badge {{ $badgeClass }}">{{ ucfirst($thread->category) }}</span>
                                        <span class="time">{{ optional($thread->last_message_at)->format('d M H:i') ?? '—' }}</span>
                                    </div>
                                    <div class="subject">{{ $thread->subject ?: '(No subject)' }}</div>
                                    <div class="preview">
                                        {{ \Illuminate\Support\Str::limit(optional($latest)->display_body ?: 'No message body', 70) }}
                                        @if($thread->file) · {{ $thread->file->mga_reference ?: ('Case #' . $thread->file->id) }} @endif
                                    </div>
                                </a>
                            </li>
                        @empty
                            <li class="empty">No inbox threads yet.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="reader">
                    <div class="reader-h">
                        <div class="title">{{ $selectedOpsThread?->subject ?: 'Thread Viewer' }}</div>
                    </div>
                    <div class="body">
                        @if($selectedOpsThread)
                            @foreach($selectedOpsThread->messages as $message)
                                @php
                                    $typeClass = $selectedOpsThread->category === 'client' ? 'client' : ($selectedOpsThread->category === 'provider' ? 'provider' : 'neutral');
                                    if ($message->direction === 'outgoing') { $typeClass = 'neutral'; }
                                @endphp
                                <article class="msg {{ $typeClass }} {{ $message->is_unread ? 'unread' : '' }}">
                                    <div class="meta">
                                        <span>{{ optional($message->sent_at)->format('d M Y H:i') ?? '—' }}</span>
                                        <span class="badge {{ $typeClass }}">{{ strtoupper($message->direction) }}</span>
                                        <span>{{ $message->from_email }}</span>
                                    </div>
                                    <div class="msg-body">{{ $message->display_body ?: '(No body)' }}</div>
                                    @if($message->attachments->isNotEmpty())
                                        <div class="attach">
                                            @foreach($message->attachments as $attachment)
                                                <span class="file">📎 {{ $attachment->filename ?: 'Attachment' }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        @else
                            <div class="empty">No thread selected in inbox.</div>
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
    </main>
</x-filament-panels::page>

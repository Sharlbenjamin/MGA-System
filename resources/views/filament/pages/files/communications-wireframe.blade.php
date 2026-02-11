<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>MGA System - Communications & Operations Inbox Mockup</title>
    <style>
        :root {
            --bg: #f6f8fb;
            --surface: #ffffff;
            --surface-soft: #f9fafb;
            --border: #e5e7eb;
            --text: #1f2937;
            --muted: #6b7280;
            --shadow: 0 8px 24px rgba(15, 23, 42, 0.08);

            --yellow-bg: #fff7d6;
            --read-bg: #f3f4f6;
            --client: #22c55e;
            --client-bg: #ecfdf3;
            --provider: #8b5cf6;
            --provider-bg: #f3eeff;
            --neutral: #9ca3af;
            --neutral-bg: #f3f4f6;

            --confirm: #22c55e;
            --adjust: #f59e0b;
            --apply: #3b82f6;
            --danger: #ef4444;

            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 10px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--text);
            background: var(--bg);
            min-width: 1280px;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--border);
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .brand {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .view-switch {
            display: flex;
            gap: 8px;
        }

        .switch-btn {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--muted);
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .switch-btn.active {
            color: #111827;
            border-color: #cbd5e1;
            background: #eef2ff;
        }

        .page {
            padding: 22px;
        }

        .shell {
            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 18px;
        }

        .screen {
            display: none;
        }

        .screen.active {
            display: block;
        }

        .screen-title {
            font-size: 22px;
            font-weight: 700;
            margin: 4px 0 16px;
            letter-spacing: 0.2px;
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .tab {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--muted);
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .tab.active {
            color: #111827;
            background: #eef2ff;
            border-color: #cbd5e1;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }

        .card-h {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .card-b {
            padding: 14px;
        }

        .timeline {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .msg {
            border: 1px solid var(--border);
            border-left: 4px solid var(--neutral);
            border-radius: var(--radius-sm);
            background: #fff;
            padding: 10px 11px;
            font-size: 13px;
            line-height: 1.35;
        }

        .msg.unread {
            background: var(--yellow-bg);
        }

        .msg.client {
            border-left-color: var(--client);
        }

        .msg.provider {
            border-left-color: var(--provider);
        }

        .msg.system {
            border-left-color: var(--neutral);
            background: #fafafa;
        }

        .meta {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 6px;
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
        }

        .badge {
            font-size: 10px;
            border-radius: 999px;
            padding: 3px 8px;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .badge.client {
            background: var(--client-bg);
            color: #15803d;
            border-color: #bbf7d0;
        }

        .badge.provider {
            background: var(--provider-bg);
            color: #6d28d9;
            border-color: #ddd6fe;
        }

        .badge.neutral {
            background: var(--neutral-bg);
            color: #4b5563;
            border-color: #e5e7eb;
        }

        .badge.waiting {
            background: #f3f4f6;
            color: #4b5563;
            border-color: #d1d5db;
        }

        .badge.requested {
            background: #fff7ed;
            color: #b45309;
            border-color: #fed7aa;
        }

        .badge.confirmed {
            background: #ecfdf3;
            color: #15803d;
            border-color: #bbf7d0;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .btn {
            border: 1px solid var(--border);
            background: #fff;
            color: #111827;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn.confirm {
            background: #ecfdf3;
            color: #166534;
            border-color: #86efac;
        }

        .btn.adjust {
            background: #fff7ed;
            color: #b45309;
            border-color: #fdba74;
        }

        .btn.apply {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #93c5fd;
        }

        .btn.danger {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fca5a5;
        }

        .btn.primary {
            background: #eef2ff;
            color: #3730a3;
            border-color: #c7d2fe;
        }

        .case-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .provider-switcher {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .provider-pill {
            border: 1px solid #ddd6fe;
            background: var(--provider-bg);
            color: #6d28d9;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .suggestions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .kv {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border: 1px dashed var(--border);
            border-radius: 8px;
            padding: 8px;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .inbox-grid {
            display: grid;
            grid-template-columns: 0.95fr 1.35fr 0.85fr;
            gap: 12px;
            min-height: 680px;
        }

        .thread-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .thread-item {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            background: var(--read-bg);
            cursor: pointer;
        }

        .thread-item.unread {
            background: var(--yellow-bg);
        }

        .thread-item.active {
            outline: 2px solid #c7d2fe;
        }

        .thread-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 6px;
        }

        .thread-subject {
            font-size: 12px;
            color: #111827;
            margin-bottom: 3px;
            font-weight: 600;
        }

        .thread-meta {
            font-size: 11px;
            color: var(--muted);
        }

        .detail-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 13px;
        }

        .detail-item {
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fafafa;
            padding: 10px;
        }

        .detail-label {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 4px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .inline-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
        }

        @media (max-width: 1350px) {
            body {
                min-width: 0;
            }

            .inbox-grid {
                grid-template-columns: 1fr;
            }

            .suggestions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">MGA System</div>
        <div class="view-switch">
            <button class="switch-btn active" data-screen-target="case-view">Case-Level View</button>
            <button class="switch-btn" data-screen-target="ops-view">Operations Inbox View</button>
        </div>
    </header>

    <main class="page">
        <section class="shell screen active" id="case-view">
            <h1 class="screen-title">File: Case #12345 - Communications</h1>

            <div class="tabs">
                <button class="tab active" data-case-tab="client-thread">Client Thread</button>
                <button class="tab" data-case-tab="provider-threads">Provider Threads</button>
            </div>

            <div class="case-grid">
                <article class="card case-panel" id="client-thread">
                    <div class="card-h">Client Thread</div>
                    <div class="card-b">
                        <div class="timeline">
                            <div class="msg client">
                                <div class="meta">
                                    <span>09:08</span>
                                    <span class="badge client">Client</span>
                                </div>
                                Please arrange consultation for Case #12345 at the earliest slot.
                            </div>
                            <div class="msg system">
                                <div class="meta">
                                    <span>09:15</span>
                                    <span class="badge neutral">MGA Ops</span>
                                </div>
                                Noted. We are contacting providers and will update shortly.
                            </div>
                            <div class="msg client unread">
                                <div class="meta">
                                    <span>09:24</span>
                                    <span class="badge client">Client • Unread</span>
                                </div>
                                Can you also confirm required documents and expected timeline?
                            </div>
                        </div>
                        <div class="actions">
                            <button class="btn">Refresh</button>
                            <button class="btn primary">Reply</button>
                        </div>
                    </div>
                </article>

                <article class="card case-panel" id="provider-threads" style="display: none;">
                    <div class="card-h">Provider Threads</div>
                    <div class="card-b">
                        <div class="provider-switcher">
                            <span class="provider-pill">Clinic A</span>
                            <span class="provider-pill">Dr. Smith</span>
                            <span class="provider-pill">Hospital X</span>
                        </div>
                        <div class="timeline">
                            <div class="msg provider unread">
                                <div class="meta">
                                    <span>10:02</span>
                                    <span class="badge provider">Clinic A</span>
                                    <span class="badge requested">Requested</span>
                                </div>
                                Earliest available slot is Thursday at 14:30.
                            </div>
                            <div class="msg system">
                                <div class="meta">
                                    <span>10:10</span>
                                    <span class="badge neutral">MGA Ops</span>
                                    <span class="badge waiting">Waiting</span>
                                </div>
                                Please share consultation price and required documents.
                            </div>
                            <div class="msg provider">
                                <div class="meta">
                                    <span>10:22</span>
                                    <span class="badge provider">Clinic A</span>
                                    <span class="badge confirmed">Confirmed</span>
                                </div>
                                Consultation fee confirmed. Documents list attached.
                            </div>
                        </div>
                        <div class="actions">
                            <button class="btn">New Request</button>
                            <button class="btn primary">Reply</button>
                            <button class="btn danger">Cancel</button>
                        </div>
                    </div>
                </article>

                <article class="card">
                    <div class="card-h">Linking &amp; Suggestions</div>
                    <div class="card-b suggestions">
                        <section class="card" style="box-shadow:none;">
                            <div class="card-h">Suggested Thread Match</div>
                            <div class="card-b">
                                <p style="margin:0 0 12px; font-size:13px; color:var(--muted);">
                                    Likely linked to Case #12345 based on subject + participant mapping.
                                </p>
                                <span class="badge confirmed" style="font-size:11px;">Confidence: 92%</span>
                                <div class="actions" style="justify-content:flex-start; margin-top:14px;">
                                    <button class="btn confirm">Confirm</button>
                                    <button class="btn adjust">Adjust</button>
                                </div>
                            </div>
                        </section>

                        <section class="card" style="box-shadow:none;">
                            <div class="card-h">Missing Fields Detected</div>
                            <div class="card-b">
                                <div class="kv">
                                    <span>DOB: 01/10/1992</span>
                                    <button class="btn apply">Apply</button>
                                </div>
                                <div class="kv">
                                    <span>Address: 123 Main St</span>
                                    <button class="btn apply">Apply</button>
                                </div>
                                <div class="kv" style="margin-bottom:0;">
                                    <span>Symptoms: Fever &amp; cough</span>
                                    <button class="btn apply">Apply</button>
                                </div>
                            </div>
                        </section>
                    </div>
                </article>
            </div>
        </section>

        <section class="shell screen" id="ops-view">
            <h1 class="screen-title">Operations Inbox</h1>

            <div class="tabs">
                <span class="tab active">General</span>
                <span class="tab">Open Cases</span>
                <span class="tab">Assisted</span>
                <span class="tab">Unlinked</span>
                <span class="tab">Providers</span>
            </div>

            <div class="inbox-grid">
                <article class="card">
                    <div class="card-h">Threads</div>
                    <div class="card-b">
                        <ul class="thread-list">
                            <li class="thread-item unread active">
                                <div class="thread-top">
                                    <span class="badge client">Client</span>
                                    <span class="thread-meta">10:24 AM</span>
                                </div>
                                <div class="thread-subject">Case #12345 • HERO • New Case Intake</div>
                                <div class="thread-meta">Unread • linked</div>
                            </li>
                            <li class="thread-item">
                                <div class="thread-top">
                                    <span class="badge provider">Provider</span>
                                    <span class="thread-meta">09:58 AM</span>
                                </div>
                                <div class="thread-subject">Case #12345 • Clinic A • Price Details</div>
                                <div class="thread-meta">Read • linked</div>
                            </li>
                            <li class="thread-item unread">
                                <div class="thread-top">
                                    <span class="badge neutral">Unlinked</span>
                                    <span class="thread-meta">09:44 AM</span>
                                </div>
                                <div class="thread-subject">Provider X • Subject: Partnership Inquiry</div>
                                <div class="thread-meta">Unread • unlinked</div>
                            </li>
                            <li class="thread-item">
                                <div class="thread-top">
                                    <span class="badge client">Client</span>
                                    <span class="thread-meta">Yesterday</span>
                                </div>
                                <div class="thread-subject">Case #12298 • Re: Medical Report Follow-up</div>
                                <div class="thread-meta">Read • linked</div>
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="card">
                    <div class="card-h">Thread Viewer</div>
                    <div class="card-b">
                        <div class="timeline">
                            <div class="msg client unread">
                                <div class="meta">
                                    <span>10:24</span>
                                    <span class="badge client">Client</span>
                                    <span class="badge neutral">Case #12345</span>
                                </div>
                                We need confirmation on provider availability and expected fee today.
                            </div>
                            <div class="msg system">
                                <div class="meta">
                                    <span>10:29</span>
                                    <span class="badge neutral">MGA Ops</span>
                                </div>
                                Acknowledged. Requests have been sent to selected providers.
                            </div>
                            <div class="msg provider">
                                <div class="meta">
                                    <span>10:36</span>
                                    <span class="badge provider">Clinic A</span>
                                </div>
                                Thursday slot available, awaiting your confirmation.
                            </div>
                        </div>

                        <div class="actions">
                            <button class="btn primary">Reply</button>
                            <button class="btn">Forward</button>
                            <button class="btn apply">Link to File</button>
                        </div>
                    </div>
                </article>

                <article class="card">
                    <div class="card-h">Thread Details</div>
                    <div class="card-b detail-list">
                        <div class="detail-item">
                            <div class="detail-label">Linked File</div>
                            <div>Case #12345 - Communications</div>
                            <a href="#" class="inline-link">Open File</a>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Participants</div>
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <span class="badge client">Client: HERO</span>
                                <span class="badge provider">Provider: Clinic A</span>
                                <span class="badge neutral">MGA Ops</span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Case Status</div>
                            <span class="badge requested">Open</span>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Provider Status</div>
                            <span class="badge waiting">Waiting</span>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </main>

    <script>
        const screenButtons = document.querySelectorAll("[data-screen-target]");
        const screens = document.querySelectorAll(".screen");

        screenButtons.forEach((button) => {
            button.addEventListener("click", () => {
                const target = button.getAttribute("data-screen-target");
                screenButtons.forEach((btn) => btn.classList.remove("active"));
                screens.forEach((screen) => screen.classList.remove("active"));
                button.classList.add("active");
                document.getElementById(target).classList.add("active");
            });
        });

        const caseTabs = document.querySelectorAll("[data-case-tab]");
        const casePanels = document.querySelectorAll(".case-panel");

        caseTabs.forEach((tab) => {
            tab.addEventListener("click", () => {
                const target = tab.getAttribute("data-case-tab");
                caseTabs.forEach((node) => node.classList.remove("active"));
                casePanels.forEach((panel) => { panel.style.display = "none"; });
                tab.classList.add("active");
                document.getElementById(target).style.display = "block";
            });
        });
    </script>
</body>
</html>

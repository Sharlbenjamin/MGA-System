<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>MGA System - Gmail Style Mockup</title>
    <style>
        :root {
            --bg: #f1f4f9;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --line: #dfe3ea;
            --text: #1f2937;
            --muted: #6b7280;

            --yellow-bg: #ffffff;
            --read-bg: #f3f4f6;
            --client: #22c55e;
            --client-soft: #ecfdf3;
            --provider: #8b5cf6;
            --provider-soft: #f3eeff;
            --neutral: #9ca3af;
            --neutral-soft: #f3f4f6;

            --confirm: #22c55e;
            --adjust: #f59e0b;
            --apply: #3b82f6;
            --danger: #ef4444;

            --radius: 14px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
            color: var(--text);
            background: var(--bg);
            min-width: 1240px;
        }

        .appbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(241, 244, 249, 0.95);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--line);
            padding: 10px 16px;
            display: grid;
            grid-template-columns: 280px 1fr auto;
            gap: 12px;
            align-items: center;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 18px;
        }

        .brand-dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: linear-gradient(120deg, #22c55e 0%, #8b5cf6 100%);
        }

        .search {
            border: 1px solid var(--line);
            background: #eaf1fb;
            border-radius: 999px;
            padding: 10px 14px;
            font-size: 13px;
            color: #475569;
        }

        .top-actions {
            display: flex;
            gap: 8px;
        }

        .top-btn {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
        }

        .view-toggle {
            padding: 12px 16px 0;
            display: flex;
            gap: 8px;
        }

        .view-btn {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--muted);
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .view-btn.active {
            background: #e8f0fe;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .screen {
            display: none;
            padding: 12px 16px 16px;
        }

        .screen.active {
            display: block;
        }

        .gmail-shell {
            border: 1px solid var(--line);
            background: var(--surface);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 26px rgba(15, 23, 42, 0.08);
        }

        .layout {
            display: grid;
            grid-template-columns: 240px 420px 1fr;
            min-height: calc(100vh - 140px);
        }

        .sidebar {
            border-right: 1px solid var(--line);
            background: #f7f9fc;
            padding: 14px;
        }

        .compose {
            border: 0;
            border-radius: 999px;
            background: #dbeafe;
            color: #1e3a8a;
            font-size: 13px;
            font-weight: 700;
            padding: 11px 16px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            margin-bottom: 14px;
        }

        .nav {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav li {
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav li.active {
            background: #dbeafe;
            color: #1e40af;
        }

        .chip {
            font-size: 10px;
            font-weight: 700;
            border-radius: 999px;
            padding: 3px 7px;
            border: 1px solid var(--line);
            background: #fff;
            color: #64748b;
        }

        .thread-column {
            border-right: 1px solid var(--line);
            background: #fff;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .column-header {
            border-bottom: 1px solid var(--line);
            padding: 12px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
        }

        .filter-tabs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .tab {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            background: #fff;
            cursor: pointer;
        }

        .tab.active {
            background: #e8f0fe;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .thread-list {
            margin: 0;
            padding: 0;
            list-style: none;
            overflow: auto;
        }

        .thread {
            border-bottom: 1px solid #edf0f4;
            padding: 10px 12px;
            background: var(--read-bg);
            cursor: pointer;
        }

        .thread.unread {
            background: var(--yellow-bg);
            border-left: 3px solid #93c5fd;
            padding-left: 9px;
        }

        .thread.active {
            outline: 2px solid #bfdbfe;
            outline-offset: -2px;
        }

        .thread-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }

        .thread-time {
            font-size: 11px;
            color: #64748b;
        }

        .type-badge {
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border: 1px solid transparent;
        }

        .type-badge.client {
            background: var(--client-soft);
            color: #15803d;
            border-color: #bbf7d0;
        }

        .type-badge.provider {
            background: var(--provider-soft);
            color: #6d28d9;
            border-color: #ddd6fe;
        }

        .type-badge.neutral {
            background: var(--neutral-soft);
            color: #4b5563;
            border-color: #e5e7eb;
        }

        .thread-subject {
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 3px;
        }

        .thread-preview {
            font-size: 12px;
            color: #64748b;
        }

        .reader {
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .reader-top {
            border-bottom: 1px solid var(--line);
            padding: 12px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .title {
            font-size: 19px;
            font-weight: 700;
        }

        .reader-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            color: #1f2937;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn.confirm {
            background: #ecfdf3;
            border-color: #86efac;
            color: #166534;
        }

        .btn.adjust {
            background: #fff7ed;
            border-color: #fdba74;
            color: #b45309;
        }

        .btn.apply {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8;
        }

        .btn.danger {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #b91c1c;
        }

        .btn.primary {
            background: #e8f0fe;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .mail-toolbar {
            border-bottom: 1px solid var(--line);
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .mail-tools {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .mail-body {
            padding: 14px;
            overflow: auto;
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .message {
            border: 1px solid var(--line);
            border-left: 4px solid var(--neutral);
            background: #fff;
            border-radius: 12px;
            padding: 10px 11px;
            font-size: 13px;
            line-height: 1.35;
        }

        .message.unread {
            background: var(--yellow-bg);
            box-shadow: inset 0 0 0 1px #bfdbfe;
        }

        .message.client {
            border-left-color: var(--client);
        }

        .message.provider {
            border-left-color: var(--provider);
        }

        .message.neutral {
            border-left-color: var(--neutral);
            background: #fafafa;
        }

        .msg-meta {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 6px;
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
        }

        .attachments {
            margin-top: 9px;
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .attachment {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 600;
            color: #334155;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .attachment::before {
            content: "📎";
            font-size: 12px;
            line-height: 1;
        }

        .attachment.client {
            background: var(--client-soft);
            border-color: #bbf7d0;
            color: #166534;
        }

        .attachment.provider {
            background: var(--provider-soft);
            border-color: #ddd6fe;
            color: #6d28d9;
        }

        .attachment.neutral {
            background: var(--neutral-soft);
            border-color: #e5e7eb;
            color: #475569;
        }

        .status {
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
            color: #475569;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 7px;
        }

        .status.requested {
            background: #fff7ed;
            color: #b45309;
            border-color: #fed7aa;
        }

        .status.waiting {
            background: #f3f4f6;
            color: #4b5563;
            border-color: #d1d5db;
        }

        .status.confirmed {
            background: #ecfdf3;
            color: #15803d;
            border-color: #bbf7d0;
        }

        .suggestions {
            border-top: 1px solid var(--line);
            padding: 12px 14px 14px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            background: #fafbff;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
        }

        .panel-h {
            padding: 10px 12px;
            border-bottom: 1px solid var(--line);
            font-size: 13px;
            font-weight: 700;
        }

        .panel-b {
            padding: 10px 12px;
        }

        .row {
            border: 1px dashed var(--line);
            border-radius: 8px;
            padding: 7px 8px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .row:last-child {
            margin-bottom: 0;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 1360px) {
            body {
                min-width: 0;
            }

            .layout {
                grid-template-columns: 220px 360px 1fr;
            }

            .suggestions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="appbar">
        <div class="brand">
            <span class="brand-dot"></span>
            MGA System
        </div>
        <div class="search">Search mail, case ID, provider, sender...</div>
        <div class="top-actions">
            <button class="top-btn">Refresh</button>
            <button class="top-btn">Settings</button>
            <button class="top-btn">Profile</button>
        </div>
    </header>

    <div class="view-toggle">
        <button class="view-btn active" data-screen="case">Case-Level View</button>
        <button class="view-btn" data-screen="ops">Operations Inbox View</button>
    </div>

    <section class="screen active" id="screen-case">
        <div class="gmail-shell">
            <div class="layout">
                <aside class="sidebar">
                    <button class="compose">+ Compose</button>
                    <ul class="nav">
                        <li class="active"><span>Inbox</span><span class="chip">12</span></li>
                        <li><span>Client Thread</span><span class="chip">4</span></li>
                        <li><span>Provider Threads</span><span class="chip">6</span></li>
                        <li><span>Open Cases</span><span class="chip">9</span></li>
                        <li><span>Unlinked</span><span class="chip">3</span></li>
                        <li><span>Assisted</span><span class="chip">5</span></li>
                    </ul>
                </aside>

                <section class="thread-column">
                    <div class="column-header">
                        <span>File: Case #12345 - Communications</span>
                        <div class="filter-tabs">
                            <button class="tab active" data-case-tab="client">Client</button>
                            <button class="tab" data-case-tab="provider">Providers</button>
                        </div>
                    </div>
                    <ul class="thread-list" id="case-thread-list">
                        <li class="thread unread active">
                            <div class="thread-top">
                                <span class="type-badge client">Client</span>
                                <span class="thread-time">09:24</span>
                            </div>
                            <div class="thread-subject">Case #12345 - Additional Info Required</div>
                            <div class="thread-preview">Can you confirm required documents and timeline?</div>
                        </li>
                        <li class="thread">
                            <div class="thread-top">
                                <span class="type-badge neutral">System</span>
                                <span class="thread-time">09:15</span>
                            </div>
                            <div class="thread-subject">MGA Ops Internal Note</div>
                            <div class="thread-preview">Requests sent to shortlisted providers.</div>
                        </li>
                        <li class="thread unread">
                            <div class="thread-top">
                                <span class="type-badge client">Client</span>
                                <span class="thread-time">08:58</span>
                            </div>
                            <div class="thread-subject">Re: Appointment Window</div>
                            <div class="thread-preview">Please advise if same-day slot is possible.</div>
                        </li>
                    </ul>
                    <ul class="thread-list hidden" id="provider-thread-list">
                        <li class="thread unread active">
                            <div class="thread-top">
                                <span class="type-badge provider">Clinic A</span>
                                <span class="thread-time">10:22</span>
                            </div>
                            <div class="thread-subject">Case #12345 - Provider Response</div>
                            <div class="thread-preview">Thursday 14:30 available. Fee confirmed.</div>
                        </li>
                        <li class="thread">
                            <div class="thread-top">
                                <span class="type-badge provider">Dr. Smith</span>
                                <span class="thread-time">10:06</span>
                            </div>
                            <div class="thread-subject">Availability Update</div>
                            <div class="thread-preview">Waiting on schedule approval from assistant.</div>
                        </li>
                        <li class="thread">
                            <div class="thread-top">
                                <span class="type-badge provider">Hospital X</span>
                                <span class="thread-time">09:47</span>
                            </div>
                            <div class="thread-subject">Request Received</div>
                            <div class="thread-preview">Case accepted for triage. Pending details.</div>
                        </li>
                    </ul>
                </section>

                <section class="reader">
                    <div class="reader-top">
                        <div class="title">Case #12345 - Communications</div>
                        <div class="reader-actions">
                            <button class="btn">Refresh</button>
                            <button class="btn primary">Reply</button>
                        </div>
                    </div>

                    <div class="mail-toolbar">
                        <div class="mail-tools">
                            <button class="btn" id="client-view-btn">Client Thread</button>
                            <button class="btn" id="provider-view-btn">Provider Threads</button>
                        </div>
                        <div class="mail-tools" id="provider-toolbar" style="display:none;">
                            <span class="status requested">Clinic A: Requested</span>
                            <span class="status waiting">Dr. Smith: Waiting</span>
                            <span class="status confirmed">Hospital X: Confirmed</span>
                            <button class="btn">New Request</button>
                            <button class="btn danger">Cancel</button>
                        </div>
                    </div>

                    <div class="mail-body" id="reader-client">
                        <article class="message client unread">
                            <div class="msg-meta">
                                <span>09:24</span>
                                <span class="type-badge client">Client • Unread</span>
                            </div>
                            Can you confirm required documents and expected timeline for Case #12345?
                            <div class="attachments">
                                <span class="attachment client">Passport_Copy.pdf</span>
                                <span class="attachment client">Insurance_Card.jpg</span>
                            </div>
                        </article>
                        <article class="message neutral">
                            <div class="msg-meta">
                                <span>09:15</span>
                                <span class="type-badge neutral">MGA Ops</span>
                            </div>
                            Noted. We are contacting providers and updating your case details.
                        </article>
                        <article class="message client">
                            <div class="msg-meta">
                                <span>09:08</span>
                                <span class="type-badge client">Client</span>
                            </div>
                            Please arrange consultation at the earliest available slot.
                            <div class="attachments">
                                <span class="attachment client">Referral_Letter.pdf</span>
                            </div>
                        </article>
                    </div>

                    <div class="mail-body hidden" id="reader-provider">
                        <article class="message provider unread">
                            <div class="msg-meta">
                                <span>10:22</span>
                                <span class="type-badge provider">Clinic A • Unread</span>
                                <span class="status confirmed">Confirmed</span>
                            </div>
                            Earliest available appointment is Thursday at 14:30. Fee details attached.
                            <div class="attachments">
                                <span class="attachment provider">Quote_ClinicA.pdf</span>
                                <span class="attachment provider">Available_Slots.xlsx</span>
                            </div>
                        </article>
                        <article class="message neutral">
                            <div class="msg-meta">
                                <span>10:10</span>
                                <span class="type-badge neutral">MGA Ops</span>
                                <span class="status waiting">Waiting</span>
                            </div>
                            Please share consultation fee and any pre-check requirements.
                        </article>
                        <article class="message provider">
                            <div class="msg-meta">
                                <span>10:02</span>
                                <span class="type-badge provider">Clinic A</span>
                                <span class="status requested">Requested</span>
                            </div>
                            Request acknowledged. Coordinating with doctor schedule.
                            <div class="attachments">
                                <span class="attachment provider">Service_Catalog.pdf</span>
                            </div>
                        </article>
                    </div>

                    <div class="suggestions">
                        <section class="panel">
                            <div class="panel-h">Suggested Thread Match</div>
                            <div class="panel-b">
                                <p style="margin:0 0 10px; font-size:13px; color:var(--muted);">Detected strong match with Case #12345 based on sender and subject.</p>
                                <span class="status confirmed">Confidence 92%</span>
                                <div style="display:flex; gap:8px; margin-top:10px;">
                                    <button class="btn confirm">Confirm</button>
                                    <button class="btn adjust">Adjust</button>
                                </div>
                            </div>
                        </section>
                        <section class="panel">
                            <div class="panel-h">Missing Fields Detected</div>
                            <div class="panel-b">
                                <div class="row"><span>DOB: 01/10/1992</span><button class="btn apply">Apply</button></div>
                                <div class="row"><span>Address: 123 Main St</span><button class="btn apply">Apply</button></div>
                                <div class="row"><span>Symptoms: Fever &amp; cough</span><button class="btn apply">Apply</button></div>
                            </div>
                        </section>
                    </div>
                </section>
            </div>
        </div>
    </section>

    <section class="screen" id="screen-ops">
        <div class="gmail-shell">
            <div class="layout">
                <aside class="sidebar">
                    <button class="compose">+ Compose</button>
                    <ul class="nav">
                        <li class="active"><span>Operations Inbox</span><span class="chip">38</span></li>
                        <li><span>General</span><span class="chip">8</span></li>
                        <li><span>Open Cases</span><span class="chip">14</span></li>
                        <li><span>Assisted</span><span class="chip">7</span></li>
                        <li><span>Unlinked</span><span class="chip">5</span></li>
                        <li><span>Providers</span><span class="chip">4</span></li>
                    </ul>
                </aside>

                <section class="thread-column">
                    <div class="column-header">
                        <span>Operations Inbox</span>
                        <div class="filter-tabs">
                            <span class="tab active">General</span>
                            <span class="tab">Open Cases</span>
                            <span class="tab">Assisted</span>
                            <span class="tab">Unlinked</span>
                            <span class="tab">Providers</span>
                        </div>
                    </div>
                    <ul class="thread-list">
                        <li class="thread unread active">
                            <div class="thread-top"><span class="type-badge client">Client</span><span class="thread-time">10:24</span></div>
                            <div class="thread-subject">Case #12345 • HERO • New Case</div>
                            <div class="thread-preview">Please confirm provider availability today.</div>
                        </li>
                        <li class="thread">
                            <div class="thread-top"><span class="type-badge provider">Provider</span><span class="thread-time">09:58</span></div>
                            <div class="thread-subject">Case #12345 • Clinic A • Price Details</div>
                            <div class="thread-preview">Fee and slot details attached.</div>
                        </li>
                        <li class="thread unread">
                            <div class="thread-top"><span class="type-badge neutral">Unlinked</span><span class="thread-time">09:44</span></div>
                            <div class="thread-subject">Provider X • Partnership Inquiry</div>
                            <div class="thread-preview">Potential network collaboration inquiry.</div>
                        </li>
                        <li class="thread">
                            <div class="thread-top"><span class="type-badge client">Client</span><span class="thread-time">Yesterday</span></div>
                            <div class="thread-subject">Case #12298 • Follow-up Needed</div>
                            <div class="thread-preview">Waiting for final medical report.</div>
                        </li>
                    </ul>
                </section>

                <section class="reader">
                    <div class="reader-top">
                        <div class="title">Thread Viewer</div>
                        <div class="reader-actions">
                            <button class="btn primary">Reply</button>
                            <button class="btn">Forward</button>
                            <button class="btn apply">Link to File</button>
                        </div>
                    </div>
                    <div class="mail-body">
                        <article class="message client unread">
                            <div class="msg-meta"><span>10:24</span><span class="type-badge client">Client • Unread</span><span class="chip">Case #12345</span></div>
                            We need provider availability and confirmation of cost by today.
                            <div class="attachments">
                                <span class="attachment client">Medical_Summary.pdf</span>
                            </div>
                        </article>
                        <article class="message neutral">
                            <div class="msg-meta"><span>10:29</span><span class="type-badge neutral">MGA Ops</span></div>
                            Acknowledged. We have sent requests to Clinic A, Dr. Smith, and Hospital X.
                        </article>
                        <article class="message provider">
                            <div class="msg-meta"><span>10:36</span><span class="type-badge provider">Clinic A</span><span class="status waiting">Waiting</span></div>
                            Slot available Thursday at 14:30. Awaiting your approval to proceed.
                            <div class="attachments">
                                <span class="attachment provider">ClinicA_Fee_Sheet.pdf</span>
                            </div>
                        </article>
                    </div>
                    <div class="suggestions" style="border-top: 1px solid var(--line);">
                        <section class="panel">
                            <div class="panel-h">Thread Details</div>
                            <div class="panel-b">
                                <div class="row"><span>Linked File</span><span>Case #12345</span></div>
                                <div class="row"><span>Participants</span><span>HERO, Clinic A, MGA Ops</span></div>
                            </div>
                        </section>
                        <section class="panel">
                            <div class="panel-h">Status</div>
                            <div class="panel-b">
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <span class="status requested">Case: Open</span>
                                    <span class="status waiting">Provider: Waiting</span>
                                </div>
                                <div class="attachments" style="margin-top:10px;">
                                    <span class="attachment neutral">Attachments (4)</span>
                                    <span class="attachment neutral">Latest: Quote_ClinicA.pdf</span>
                                </div>
                            </div>
                        </section>
                    </div>
                </section>
            </div>
        </div>
    </section>

    <script>
        const viewButtons = document.querySelectorAll(".view-btn");
        const screens = {
            case: document.getElementById("screen-case"),
            ops: document.getElementById("screen-ops"),
        };

        viewButtons.forEach((button) => {
            button.addEventListener("click", () => {
                const screen = button.getAttribute("data-screen");
                viewButtons.forEach((b) => b.classList.remove("active"));
                Object.values(screens).forEach((s) => s.classList.remove("active"));
                button.classList.add("active");
                screens[screen].classList.add("active");
            });
        });

        const caseTabs = document.querySelectorAll("[data-case-tab]");
        const clientList = document.getElementById("case-thread-list");
        const providerList = document.getElementById("provider-thread-list");
        const clientReader = document.getElementById("reader-client");
        const providerReader = document.getElementById("reader-provider");
        const providerToolbar = document.getElementById("provider-toolbar");

        function setCaseMode(mode) {
            const providerMode = mode === "provider";
            caseTabs.forEach((t) => t.classList.remove("active"));
            document.querySelector(`[data-case-tab="${mode}"]`).classList.add("active");
            clientList.classList.toggle("hidden", providerMode);
            providerList.classList.toggle("hidden", !providerMode);
            clientReader.classList.toggle("hidden", providerMode);
            providerReader.classList.toggle("hidden", !providerMode);
            providerToolbar.style.display = providerMode ? "flex" : "none";
        }

        caseTabs.forEach((tab) => {
            tab.addEventListener("click", () => setCaseMode(tab.getAttribute("data-case-tab")));
        });

        document.getElementById("client-view-btn").addEventListener("click", () => setCaseMode("client"));
        document.getElementById("provider-view-btn").addEventListener("click", () => setCaseMode("provider"));
    </script>
</body>
</html>

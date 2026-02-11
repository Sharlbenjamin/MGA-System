<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>MGA System - Communications Wireframe</title>
    <style>
        :root {
            --wf-bg: #f4f4f5;
            --wf-panel: #ffffff;
            --wf-line: #d4d4d8;
            --wf-line-dark: #a1a1aa;
            --wf-text: #18181b;
            --wf-muted: #52525b;
            --wf-chip: #ededee;
            --wf-radius-lg: 16px;
            --wf-radius-md: 12px;
            --wf-radius-sm: 10px;
            --wf-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--wf-bg);
            color: var(--wf-text);
            min-width: 1360px;
        }

        .topbar {
            background: #fff;
            border-bottom: 1px solid var(--wf-line);
            padding: 14px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .topbar-title {
            font-size: 19px;
            font-weight: 700;
        }

        .topbar a {
            border: 1px solid var(--wf-line-dark);
            background: #fff;
            color: #111;
            border-radius: 10px;
            padding: 8px 14px;
            font-weight: 600;
            font-size: 12px;
            text-decoration: none;
        }

        .page {
            padding: 24px;
        }

        .wire-frame {
            width: 100%;
        }

        .dashboard {
            background: #fafafa;
            border: 1px solid var(--wf-line);
            border-radius: 20px;
            box-shadow: var(--wf-shadow);
            padding: 20px;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            gap: 18px;
            min-height: calc(100vh - 140px);
        }

        .panel {
            background: var(--wf-panel);
            border: 1px solid var(--wf-line);
            border-radius: var(--wf-radius-lg);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .panel-header {
            padding: 16px 18px;
            border-bottom: 1px solid var(--wf-line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .panel-title {
            font-size: 19px;
            font-weight: 700;
            letter-spacing: 0.2px;
            color: var(--wf-text);
        }

        .tabs {
            display: flex;
            gap: 8px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--wf-line);
            background: #fcfcfc;
            flex-wrap: wrap;
        }

        .tab {
            border: 1px solid var(--wf-line);
            background: #fff;
            border-radius: 999px;
            padding: 7px 14px;
            font-size: 12px;
            font-weight: 600;
            color: var(--wf-muted);
            user-select: none;
        }

        .tab.active {
            color: #000;
            border-color: var(--wf-line-dark);
            background: var(--wf-chip);
        }

        .left-body {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 14px;
            padding: 14px;
            min-height: 0;
            flex: 1;
        }

        .subcard {
            border: 1px solid var(--wf-line);
            border-radius: var(--wf-radius-md);
            background: #fff;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .subcard-title {
            padding: 12px 14px;
            border-bottom: 1px solid var(--wf-line);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            color: var(--wf-muted);
        }

        .subcard-inline-title {
            margin: -12px -12px 0;
            border-bottom: 1px solid var(--wf-line);
        }

        .thread-list {
            list-style: none;
            padding: 10px;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .thread-item {
            border: 1px solid var(--wf-line);
            border-radius: var(--wf-radius-sm);
            background: #fcfcfc;
            padding: 10px 11px;
            font-size: 13px;
            line-height: 1.25;
            color: #202024;
        }

        .thread-item.active {
            border-color: var(--wf-line-dark);
            background: #f2f2f2;
        }

        .viewer {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 11px;
            min-height: 0;
        }

        .subject {
            border: 1px dashed var(--wf-line-dark);
            border-radius: var(--wf-radius-sm);
            background: #fafafa;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
            color: var(--wf-text);
        }

        .timeline {
            border: 1px solid var(--wf-line);
            border-radius: var(--wf-radius-sm);
            padding: 10px;
            background: #fcfcfc;
            display: flex;
            flex-direction: column;
            gap: 9px;
            flex: 1;
        }

        .timeline-compact {
            min-height: 126px;
        }

        .msg {
            border: 1px solid var(--wf-line);
            border-radius: 10px;
            background: #fff;
            padding: 9px 10px;
            font-size: 12px;
            line-height: 1.35;
            color: var(--wf-text);
        }

        .msg.meta {
            background: #f7f7f7;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            padding-top: 2px;
        }

        .btn {
            border: 1px solid var(--wf-line-dark);
            background: #fff;
            color: #111;
            border-radius: 10px;
            padding: 9px 16px;
            font-weight: 600;
            font-size: 12px;
            cursor: default;
        }

        .right-body {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 14px;
            flex: 1;
        }

        .right-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .right-tab {
            border: 1px solid var(--wf-line);
            border-radius: 12px;
            background: #fcfcfc;
            text-align: center;
            padding: 14px 12px;
            font-size: 16px;
            font-weight: 650;
            color: var(--wf-text);
        }

        .right-tab.active {
            background: #f0f0f0;
            border-color: var(--wf-line-dark);
        }

        .thread-area {
            border: 1px solid var(--wf-line);
            border-radius: var(--wf-radius-md);
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #fff;
            min-height: 260px;
        }

        .provider-switcher {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .provider-btn {
            border: 1px solid var(--wf-line);
            border-radius: 999px;
            background: #fff;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--wf-text);
        }

        .provider-btn.active {
            background: #ededed;
            border-color: var(--wf-line-dark);
        }

        .bottom-linking {
            border: 1px solid var(--wf-line);
            border-radius: var(--wf-radius-md);
            background: #fcfcfc;
            overflow: hidden;
        }

        .bottom-header {
            padding: 12px 14px;
            border-bottom: 1px solid var(--wf-line);
            font-size: 14px;
            font-weight: 700;
            color: var(--wf-text);
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 12px;
        }

        .suggest-card {
            border: 1px solid var(--wf-line);
            border-radius: 12px;
            background: #fff;
            padding: 12px;
            min-height: 126px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .suggest-text {
            font-size: 13px;
            line-height: 1.4;
            color: var(--wf-text);
        }

        .stack {
            display: flex;
            gap: 8px;
            margin-top: auto;
        }

        .fields {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
        }

        .field-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px dashed var(--wf-line);
            border-radius: 8px;
            padding: 7px 8px;
            gap: 8px;
            color: var(--wf-text);
        }

        .apply {
            border: 1px solid var(--wf-line-dark);
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 700;
            background: #fff;
            white-space: nowrap;
            color: #111;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-title">MGA System · Communications</div>
        <a href="javascript:history.back()">Back</a>
    </div>
    <div class="page">
        <main class="wire-frame">
            <section class="dashboard" aria-label="MGA System Wireframe">
                    <article class="panel">
                        <div class="panel-header">
                            <h1 class="panel-title">Operations Inbox</h1>
                        </div>

                        <div class="tabs">
                            <div class="tab active">General</div>
                            <div class="tab">Open Cases</div>
                            <div class="tab">Unlinked</div>
                            <div class="tab">Providers</div>
                        </div>

                        <div class="left-body">
                            <section class="subcard">
                                <div class="subcard-title">Threads</div>
                                <ul class="thread-list">
                                    <li class="thread-item active">[Case 12345] HERO -> New Case</li>
                                    <li class="thread-item">[Case 12345] You -> Re: Info</li>
                                    <li class="thread-item">[Unlinked] Provider X -> Prices</li>
                                    <li class="thread-item">[General] Inquiry -> Partnership</li>
                                </ul>
                            </section>

                            <section class="subcard">
                                <div class="subcard-title">Thread Viewer</div>
                                <div class="viewer">
                                    <div class="subject">Subject: Case #12345 - Additional Info Required</div>
                                    <div class="timeline">
                                        <div class="msg meta"><strong>09:08</strong> HERO: We have opened a new case and attached initial details.</div>
                                        <div class="msg"><strong>09:15</strong> MGA Ops: Received. We are reviewing provider options now.</div>
                                        <div class="msg"><strong>09:24</strong> HERO: Please confirm if all required fields are complete.</div>
                                    </div>
                                    <div class="actions">
                                        <button class="btn">Reply</button>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </article>

                    <article class="panel">
                        <div class="panel-header">
                            <h2 class="panel-title">File: Case #12345 - Communications</h2>
                        </div>

                        <div class="right-body">
                            <div class="right-tabs">
                                <div class="right-tab active">Client Thread</div>
                                <div class="right-tab">Provider Threads</div>
                            </div>

                            <section class="thread-area">
                                <div class="subcard-title subcard-inline-title">Client Thread</div>
                                <div class="timeline">
                                    <div class="msg"><strong>09:02</strong> Client: Please arrange consultation for Case #12345.</div>
                                    <div class="msg meta"><strong>09:11</strong> MGA Team: Noted. We are contacting providers.</div>
                                    <div class="msg"><strong>09:19</strong> Client: Thank you, please keep us updated.</div>
                                </div>
                                <div class="actions">
                                    <button class="btn">Reply</button>
                                </div>
                            </section>

                            <section class="thread-area">
                                <div class="subcard-title subcard-inline-title">Provider Threads</div>
                                <div class="provider-switcher">
                                    <div class="provider-btn active">Clinic A</div>
                                    <div class="provider-btn">Dr. Smith</div>
                                    <div class="provider-btn">Hospital X</div>
                                </div>
                                <div class="timeline timeline-compact">
                                    <div class="msg"><strong>10:02</strong> Clinic A: Earliest available slot is Thursday at 14:30.</div>
                                    <div class="msg meta"><strong>10:10</strong> MGA Team: Please share consultation price and required documents.</div>
                                </div>
                            </section>

                            <section class="bottom-linking">
                                <div class="bottom-header">Linking &amp; Suggestions</div>
                                <div class="bottom-grid">
                                    <div class="suggest-card">
                                        <div class="suggest-text">Suggested Thread: Matches Case ID</div>
                                        <div class="stack">
                                            <button class="btn">Confirm</button>
                                            <button class="btn">Adjust</button>
                                        </div>
                                    </div>

                                    <div class="suggest-card">
                                        <div class="suggest-text">Missing Fields:</div>
                                        <ul class="fields">
                                            <li class="field-row"><span>DOB: 01/10/1992</span><button class="apply">Apply</button></li>
                                            <li class="field-row"><span>Address: 123 Main St</span><button class="apply">Apply</button></li>
                                        </ul>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </article>
            </section>
        </main>
    </div>
</body>
</html>

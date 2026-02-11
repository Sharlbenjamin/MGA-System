<?php

namespace App\Http\Controllers;

use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\File;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class FileCompactViewController extends Controller
{
    use AuthorizesRequests;

    /**
     * Redirect to Filament file view (compact view is shown there; no app layout / Vite).
     */
    public function show(Request $request, File $file)
    {
        $this->authorize('view', $file);

        return redirect()->route('filament.admin.resources.files.view', ['record' => $file]);
    }

    /**
     * Show standalone communications/threads wireframe for a file.
     */
    public function communications(Request $request, File $file)
    {
        $this->authorize('view', $file);

        $allCaseThreads = CommunicationThread::query()
            ->where('linked_file_id', $file->id)
            ->orderByDesc('last_message_at')
            ->get();

        $clientThreads = $allCaseThreads->where('category', 'client')->values();
        $providerThreads = $allCaseThreads->where('category', 'provider')->values();
        $caseTab = $request->query('case_tab', 'client');
        $casePool = $caseTab === 'provider' ? $providerThreads : $clientThreads;
        if ($casePool->isEmpty()) {
            $casePool = $allCaseThreads;
        }

        $selectedCaseThread = null;
        if ($casePool->isNotEmpty()) {
            $selectedCaseThread = $casePool->firstWhere('id', (int) $request->query('thread_id')) ?? $casePool->first();
            $selectedCaseThread?->load(['messages' => fn ($q) => $q->with('attachments')->orderBy('sent_at')]);
        }

        $opsThreads = CommunicationThread::query()
            ->with('file:id,mga_reference,status')
            ->orderByDesc('last_message_at')
            ->limit(300)
            ->get();

        $selectedOpsThread = null;
        if ($opsThreads->isNotEmpty()) {
            $selectedOpsThread = $opsThreads->firstWhere('id', (int) $request->query('inbox_thread_id')) ?? $opsThreads->first();
            $selectedOpsThread?->load(['messages' => fn ($q) => $q->with('attachments')->orderBy('sent_at')]);
        }

        return view('filament.pages.files.communications-wireframe', [
            'file' => $file,
            'caseTab' => $caseTab,
            'allCaseThreads' => $allCaseThreads,
            'clientThreads' => $clientThreads,
            'providerThreads' => $providerThreads,
            'selectedCaseThread' => $selectedCaseThread,
            'opsThreads' => $opsThreads,
            'selectedOpsThread' => $selectedOpsThread,
        ]);
    }

    public function markRead(Request $request, File $file, CommunicationThread $thread)
    {
        $this->authorize('view', $file);
        if ((int) $thread->linked_file_id !== (int) $file->id) {
            abort(403);
        }

        $thread->update([
            'is_read' => true,
            'awaiting_reply' => false,
        ]);

        $thread->messages()
            ->where('direction', 'incoming')
            ->where('is_unread', true)
            ->update(['is_unread' => false]);

        return redirect()->route('files.communications-wireframe', [
            'file' => $file->id,
            'thread_id' => $thread->id,
            'case_tab' => $request->query('case_tab', 'client'),
        ]);
    }

    public function sendReply(Request $request, File $file, CommunicationThread $thread)
    {
        $this->authorize('view', $file);
        if ((int) $thread->linked_file_id !== (int) $file->id) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'max:255'],
        ]);

        $latestIncoming = $thread->messages()
            ->where('direction', 'incoming')
            ->latest('sent_at')
            ->first();

        $toEmail = strtolower((string) ($latestIncoming->from_email ?? ''));
        if ($toEmail === '') {
            return back()->withErrors(['body' => 'No recipient email was found on this thread.']);
        }

        $fromAddress = config('mail.from.address', 'mga.operation@medguarda.com');
        $fromName = config('mail.from.name', 'MGA Operation');
        $subject = $validated['subject'] ?: ('Re: ' . ($thread->subject ?: 'Case Communication'));
        $body = (string) $validated['body'];

        Mail::raw($body, function ($message) use ($toEmail, $subject, $fromAddress, $fromName) {
            $message->from($fromAddress, $fromName)
                ->to($toEmail)
                ->subject($subject);
        });

        CommunicationMessage::create([
            'communication_thread_id' => $thread->id,
            'mailbox' => strtolower((string) $fromAddress),
            'direction' => 'outgoing',
            'from_email' => strtolower((string) $fromAddress),
            'from_name' => $fromName,
            'to_emails' => [$toEmail],
            'subject' => $subject,
            'body_text' => $body,
            'sent_at' => now(),
            'is_unread' => false,
            'has_attachments' => false,
            'metadata' => ['sent_via' => 'web_communications_page'],
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

        return redirect()->route('files.communications-wireframe', [
            'file' => $file->id,
            'thread_id' => $thread->id,
            'case_tab' => $request->query('case_tab', 'client'),
        ])->with('status', 'Reply sent.');
    }
}

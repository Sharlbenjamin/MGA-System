<?php

namespace App\Http\Controllers;

use App\Filament\Resources\FileResource;
use App\Models\CommunicationAttachment;
use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\File;
use App\Services\GmailImapPollingService;
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

        $routeParams = ['record' => $file->id];
        $queryParams = $request->only(['view', 'case_tab', 'thread_id', 'inbox_thread_id']);
        $target = FileResource::getUrl('communications', $routeParams);

        return redirect()->to($queryParams ? ($target . '?' . http_build_query($queryParams)) : $target);
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

        $target = FileResource::getUrl('communications', ['record' => $file->id]);
        $query = http_build_query([
            'view' => $request->query('view', 'case'),
            'thread_id' => $thread->id,
            'case_tab' => $request->query('case_tab', 'client'),
        ]);

        return redirect()->to($target . '?' . $query);
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

        $target = FileResource::getUrl('communications', ['record' => $file->id]);
        $query = http_build_query([
            'view' => $request->query('view', 'case'),
            'thread_id' => $thread->id,
            'case_tab' => $request->query('case_tab', 'client'),
        ]);

        return redirect()->to($target . '?' . $query)->with('status', 'Reply sent.');
    }

    public function downloadAttachment(
        Request $request,
        CommunicationAttachment $attachment,
        GmailImapPollingService $imapService
    ) {
        $attachment->load('message.thread.file');

        $threadFile = $attachment->message?->thread?->file;
        if ($threadFile) {
            $this->authorize('view', $threadFile);
        }

        if (!empty($attachment->url)) {
            return redirect()->away($attachment->url);
        }

        $message = $attachment->message;
        if (!$message || !$message->mailbox_uid || !$attachment->part_number) {
            return back()->withErrors(['attachment' => 'Attachment payload is not available yet.']);
        }

        $payload = $imapService->fetchAttachmentContent(
            (string) ($message->mailbox ?: config('mail.from.address', 'mga.operation@medguarda.com')),
            (int) $message->mailbox_uid,
            (string) $attachment->part_number
        );

        if (!$payload) {
            return back()->withErrors(['attachment' => 'Attachment payload could not be fetched from mailbox.']);
        }

        $filename = $attachment->filename ?: ($payload['filename'] ?? ('attachment-' . $attachment->id));
        $safeFilename = str_replace(['"', "\r", "\n"], '', (string) $filename);
        $mode = $request->query('mode', 'open') === 'download' ? 'attachment' : 'inline';

        return response($payload['content'], 200, [
            'Content-Type' => (string) ($payload['mime_type'] ?? 'application/octet-stream'),
            'Content-Disposition' => $mode . '; filename="' . $safeFilename . '"',
            'Cache-Control' => 'private, max-age=0, no-cache',
        ]);
    }
}

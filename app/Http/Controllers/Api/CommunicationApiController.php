<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\File;
use App\Services\CommunicationLinkingService;
use App\Services\GmailImapPollingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CommunicationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CommunicationThread::query()
            ->with(['latestMessage', 'file:id,mga_reference,status'])
            ->orderByDesc('last_message_at');

        $tab = $request->input('tab');
        if ($tab === 'providers') {
            $query->where('category', 'provider');
        } elseif ($tab === 'unlinked') {
            $query->whereNull('linked_file_id');
        } elseif ($tab === 'open_cases') {
            $query->whereHas('file', fn ($q) => $q->where('status', '!=', 'Assisted'));
        } elseif ($tab === 'assisted') {
            $query->whereHas('file', fn ($q) => $q->where('status', 'Assisted'));
        }

        if ($request->filled('is_read')) {
            $query->where('is_read', filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('file_id')) {
            $query->where('linked_file_id', $request->integer('file_id'));
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('subject', 'like', '%' . $term . '%')
                    ->orWhere('normalized_subject', 'like', '%' . strtolower($term) . '%')
                    ->orWhereJsonContains('participants', strtolower($term));
            });
        }

        $perPage = (int) $request->input('per_page', 25);
        $paginator = $query->paginate(max(1, min(100, $perPage)))->withQueryString();

        return response()->json($paginator);
    }

    public function show(int $threadId): JsonResponse
    {
        $thread = CommunicationThread::query()
            ->with([
                'file:id,mga_reference,status,patient_id',
                'messages' => fn ($q) => $q->with('attachments')->orderBy('sent_at'),
            ])
            ->find($threadId);

        if (!$thread) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        return response()->json($thread);
    }

    public function markRead(int $threadId): JsonResponse
    {
        $thread = CommunicationThread::find($threadId);
        if (!$thread) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        $thread->update([
            'is_read' => true,
            'awaiting_reply' => false,
        ]);

        $thread->messages()
            ->where('direction', 'incoming')
            ->where('is_unread', true)
            ->update(['is_unread' => false]);

        return response()->json(['message' => 'Thread marked as read']);
    }

    public function reply(Request $request, int $threadId): JsonResponse
    {
        $thread = CommunicationThread::with('messages')->find($threadId);
        if (!$thread) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        $validated = $request->validate([
            'body' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'max:255'],
            'to_email' => ['nullable', 'email'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email'],
        ]);

        $latestIncoming = $thread->messages()
            ->where('direction', 'incoming')
            ->latest('sent_at')
            ->first();

        $toEmail = strtolower($validated['to_email'] ?? ($latestIncoming->from_email ?? ''));
        if (!$toEmail) {
            return response()->json(['message' => 'No recipient email found for this thread'], 422);
        }

        $subject = $validated['subject'] ?? ('Re: ' . ($thread->subject ?: 'Case Communication'));
        $cc = array_map('strtolower', $validated['cc'] ?? []);
        $body = (string) $validated['body'];

        Mail::raw($body, function ($message) use ($toEmail, $cc, $subject) {
            $message->from('mga.operation@medguarda.com', 'MGA Operation')
                ->to($toEmail)
                ->subject($subject);

            if (!empty($cc)) {
                $message->cc($cc);
            }
        });

        $outgoing = CommunicationMessage::create([
            'communication_thread_id' => $thread->id,
            'mailbox' => 'mga.operation@medguarda.com',
            'direction' => 'outgoing',
            'from_email' => 'mga.operation@medguarda.com',
            'from_name' => 'MGA Operation',
            'to_emails' => [$toEmail],
            'cc_emails' => $cc,
            'subject' => $subject,
            'body_text' => $body,
            'sent_at' => now(),
            'is_unread' => false,
            'has_attachments' => false,
            'metadata' => ['sent_via' => 'laravel_mail'],
        ]);

        $participants = array_values(array_unique(array_map('strtolower', array_filter(array_merge(
            $thread->participants ?? [],
            ['mga.operation@medguarda.com', $toEmail],
            $cc
        )))));

        $thread->update([
            'subject' => $thread->subject ?: $subject,
            'participants' => $participants,
            'is_read' => true,
            'awaiting_reply' => false,
            'last_message_at' => $outgoing->sent_at,
        ]);

        $thread->messages()
            ->where('direction', 'incoming')
            ->where('is_unread', true)
            ->update(['is_unread' => false]);

        return response()->json([
            'message' => 'Reply sent',
            'data' => $outgoing->load('attachments'),
        ], 201);
    }

    public function poll(Request $request, GmailImapPollingService $service): JsonResponse
    {
        $validated = $request->validate([
            'mailbox' => ['nullable', 'email'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $result = $service->poll(
            $validated['mailbox'] ?? 'mga.operation@medguarda.com',
            (int) ($validated['limit'] ?? 100)
        );

        return response()->json($result);
    }

    public function fileThreads(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $threads = CommunicationThread::query()
            ->where('linked_file_id', $fileId)
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json($threads);
    }

    public function linkThreadToFile(Request $request, int $threadId): JsonResponse
    {
        $validated = $request->validate([
            'file_id' => ['required', 'integer', 'exists:files,id'],
        ]);

        $thread = CommunicationThread::find($threadId);
        if (!$thread) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        $thread->update([
            'linked_file_id' => (int) $validated['file_id'],
            'category' => $thread->category === 'unlinked' ? 'general' : $thread->category,
        ]);

        return response()->json(['message' => 'Thread linked to file', 'thread' => $thread->fresh()]);
    }

    public function linkForFile(int $fileId, CommunicationLinkingService $service): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $linked = $service->linkForFile($file);
        return response()->json([
            'message' => 'Auto-link executed',
            'linked_count' => $linked,
        ]);
    }
}

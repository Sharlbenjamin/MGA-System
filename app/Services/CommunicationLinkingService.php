<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CommunicationThread;
use App\Models\File;
use App\Models\Provider;

class CommunicationLinkingService
{
    /**
     * Link unlinked threads to a newly created file based on case references and participants.
     *
     * Priority:
     * 1) Case references in subject (mga_reference/client_reference/Case #id)
     * 2) Known participant emails (client operation_email/email, file email)
     */
    public function linkForFile(File $file): int
    {
        $emails = $this->candidateEmails($file);
        $caseNeedles = array_filter([
            $file->mga_reference,
            $file->client_reference,
            'Case #' . $file->id,
            '[Case ' . $file->id . ']',
        ]);

        $query = CommunicationThread::query()
            ->whereNull('linked_file_id')
            ->where(function ($q) use ($caseNeedles, $emails) {
                foreach ($caseNeedles as $needle) {
                    $q->orWhere('subject', 'like', '%' . $needle . '%');
                }

                foreach ($emails as $email) {
                    $q->orWhereJsonContains('participants', strtolower($email));
                }
            });

        $threads = $query->get();
        if ($threads->isEmpty()) {
            return 0;
        }

        foreach ($threads as $thread) {
            $category = $this->inferCategory($thread, $emails);
            $thread->update([
                'linked_file_id' => $file->id,
                'category' => $category,
            ]);
        }

        return $threads->count();
    }

    /**
     * Infer thread category from participant emails.
     */
    public function inferCategory(CommunicationThread $thread, array $fileEmails = []): string
    {
        $participants = array_map('strtolower', $thread->participants ?? []);
        $knownClientEmails = array_map('strtolower', $fileEmails);

        foreach ($participants as $email) {
            if (in_array($email, $knownClientEmails, true)) {
                return 'client';
            }
        }

        $providerMatch = Provider::query()
            ->whereNotNull('email')
            ->whereIn('email', $participants)
            ->exists();

        if ($providerMatch) {
            return 'provider';
        }

        return $thread->linked_file_id ? 'general' : 'unlinked';
    }

    /**
     * Candidate participant emails used for auto-linking.
     *
     * @return array<int, string>
     */
    private function candidateEmails(File $file): array
    {
        $emails = [];

        $client = Client::query()
            ->where('id', optional($file->patient)->client_id)
            ->first();

        foreach ([
            $file->email,
            $client?->operation_email,
            $client?->email,
        ] as $email) {
            if ($email) {
                $emails[] = strtolower(trim($email));
            }
        }

        return array_values(array_unique($emails));
    }
}

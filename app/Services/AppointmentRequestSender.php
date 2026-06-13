<?php

namespace App\Services;

use App\Mail\AppointmentRequestMailable;
use App\Models\File;
use App\Models\Gop;
use App\Models\ProviderBranch;
use App\Models\Task;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AppointmentRequestSender
{
    /**
     * @return array{success: int, failure: int, skipped: bool, message: ?string}
     */
    public function send(File $file, array $data, User $user, ?Gop $gop = null): array
    {
        $selectedBranchIds = $data['selected_branches'] ?? [];
        $customEmails = collect($data['custom_emails'] ?? [])->pluck('email')->filter();

        if (empty($selectedBranchIds) && $customEmails->isNotEmpty()) {
            return $this->sendToCustomEmailsOnly($file, $customEmails, $user, $gop);
        }

        if (empty($selectedBranchIds)) {
            return [
                'success' => 0,
                'failure' => 0,
                'skipped' => true,
                'message' => 'Please select at least one provider branch or add custom email recipients.',
            ];
        }

        $gopPdfContent = $this->generateGopPdfContent($file, $gop);
        $successCount = 0;
        $failureCount = 0;
        $branches = ProviderBranch::whereIn('id', $selectedBranchIds)->get();

        foreach ($branches as $branch) {
            try {
                $hasBranchEmail = ! empty($branch->email);
                $hasCustomEmails = $customEmails->isNotEmpty();

                if (! $hasBranchEmail && ! $hasCustomEmails) {
                    $this->createManualFollowUpTaskForBranch($branch, $file, $user);
                    $failureCount++;
                    continue;
                }

                Mail::send(new AppointmentRequestMailable(
                    $file,
                    $branch,
                    $customEmails->toArray(),
                    $gop,
                    $user,
                    $gopPdfContent,
                ));

                $successCount++;
            } catch (\Throwable $e) {
                Log::error('Failed to send appointment request', [
                    'branch_id' => $branch->id,
                    'error' => $e->getMessage(),
                ]);
                $failureCount++;
            }
        }

        return [
            'success' => $successCount,
            'failure' => $failureCount,
            'skipped' => false,
            'message' => null,
        ];
    }

    /**
     * @return array{success: int, failure: int, skipped: bool, message: ?string}
     */
    protected function sendToCustomEmailsOnly(File $file, Collection $customEmails, User $user, ?Gop $gop): array
    {
        try {
            Mail::send(new AppointmentRequestMailable(
                $file,
                null,
                $customEmails->toArray(),
                $gop,
                $user,
                $this->generateGopPdfContent($file, $gop),
            ));

            return [
                'success' => $customEmails->count(),
                'failure' => 0,
                'skipped' => false,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to send appointment request to custom emails', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => 0,
                'failure' => $customEmails->count(),
                'skipped' => false,
                'message' => 'Failed to send appointment request to custom emails.',
            ];
        }
    }

    protected function generateGopPdfContent(File $file, ?Gop $gop): ?string
    {
        $gopForAttachment = $gop && $gop->type === 'Out'
            ? $gop
            : $file->gops()->where('type', 'Out')->orderByDesc('date')->orderByDesc('id')->first();

        if (! $gopForAttachment) {
            return null;
        }

        try {
            return Pdf::loadView('pdf.gop', ['gop' => $gopForAttachment])->output();
        } catch (\Throwable $exception) {
            Log::warning('Failed to generate GOP PDF for appointment request', [
                'file_id' => $file->id,
                'gop_id' => $gopForAttachment->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function createManualFollowUpTaskForBranch(ProviderBranch $branch, File $record, User $user): void
    {
        Task::create([
            'title' => 'Manual follow-up required for appointment request',
            'description' => "File: {$record->mga_reference} - Patient: {$record->patient->name} - Branch: {$branch->branch_name}",
            'taskable_type' => ProviderBranch::class,
            'taskable_id' => $branch->id,
            'user_id' => $user->id,
            'file_id' => $record->id,
            'department' => 'Operation',
            'due_date' => now()->addDay(),
        ]);
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAcceptedGopInCommand extends Command
{
    protected $signature = 'files:backfill-accepted-gop-in
                            {--before=2026-07-07 : Only files with service_date strictly before this date (Y-m-d)}
                            {--dry-run : Preview affected files without writing}
                            {--apply : Perform the updates/inserts}';

    protected $description = 'Ensure files older than a cutoff have an Accepted GOP In (update existing In, or insert if missing)';

    public function handle(): int
    {
        $before = (string) $this->option('before');
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run') || ! $apply;

        if ($apply && $this->option('dry-run')) {
            $this->error('Use either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $before)) {
            $this->error('Invalid --before date. Use Y-m-d, e.g. 2026-07-07.');

            return self::FAILURE;
        }

        $missingAccepted = $this->filesMissingAcceptedGopIn($before);
        $toUpdate = $this->latestInGopIdsForBackfill($before);
        $toInsert = $this->filesWithNoGopIn($before);

        $this->info("Cutoff: service_date < {$before}");
        $this->info('Files missing Accepted GOP In: '.$missingAccepted->count());
        $this->info('Will update existing GOP In → Accepted: '.$toUpdate->count());
        $this->info('Will insert new Accepted GOP In: '.$toInsert->count());

        if ($missingAccepted->isNotEmpty()) {
            $this->table(
                ['File ID', 'MGA ref', 'Service date', 'Action'],
                $missingAccepted->take(30)->map(function (object $file) use ($toInsert): array {
                    $action = $toInsert->contains('id', $file->id) ? 'insert' : 'update existing In';

                    return [
                        $file->id,
                        $file->mga_reference,
                        $file->service_date,
                        $action,
                    ];
                })->all(),
            );

            if ($missingAccepted->count() > 30) {
                $this->comment('... and '.($missingAccepted->count() - 30).' more.');
            }
        }

        if ($dryRun) {
            $this->comment('Dry run only. Re-run with --apply to write changes.');

            return self::SUCCESS;
        }

        $updated = 0;
        $inserted = 0;
        $now = now();

        DB::transaction(function () use ($toUpdate, $toInsert, $now, &$updated, &$inserted): void {
            if ($toUpdate->isNotEmpty()) {
                $updated = DB::table('gops')
                    ->whereIn('id', $toUpdate->pluck('gop_id'))
                    ->update([
                        'status' => 'Accepted',
                        'updated_at' => $now,
                    ]);
            }

            foreach ($toInsert as $file) {
                DB::table('gops')->insert([
                    'file_id' => $file->id,
                    'provider_branch_id' => $file->provider_branch_id,
                    'service_type_id' => $file->service_type_id,
                    'type' => 'In',
                    'status' => 'Accepted',
                    'amount' => 0,
                    'offered_cost' => 0,
                    'file_fee' => 0,
                    'notes' => 'Backfilled Accepted GOP In for historical file (service_date before cutoff).',
                    'date' => $file->service_date ?? $now->toDateString(),
                    'gop_google_drive_link' => null,
                    'document_path' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            }
        });

        $this->info("Done. Updated {$updated} GOP(s), inserted {$inserted} GOP(s).");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{id:int,mga_reference:?string,service_date:?string}>
     */
    protected function filesMissingAcceptedGopIn(string $before)
    {
        return DB::table('files as f')
            ->select(['f.id', 'f.mga_reference', 'f.service_date'])
            ->whereNotNull('f.service_date')
            ->where('f.service_date', '<', $before)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('gops as g')
                    ->whereColumn('g.file_id', 'f.id')
                    ->where('g.type', 'In')
                    ->where('g.status', 'Accepted');
            })
            ->orderBy('f.id')
            ->get();
    }

    /**
     * Latest type=In GOP per file that needs Accepted backfill.
     *
     * @return \Illuminate\Support\Collection<int, object{file_id:int,gop_id:int}>
     */
    protected function latestInGopIdsForBackfill(string $before)
    {
        return DB::table('gops as g')
            ->join('files as f', 'f.id', '=', 'g.file_id')
            ->selectRaw('g.file_id, MAX(g.id) as gop_id')
            ->where('g.type', 'In')
            ->whereNotNull('f.service_date')
            ->where('f.service_date', '<', $before)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('gops as ax')
                    ->whereColumn('ax.file_id', 'g.file_id')
                    ->where('ax.type', 'In')
                    ->where('ax.status', 'Accepted');
            })
            ->groupBy('g.file_id')
            ->get();
    }

    /**
     * Files with no type=In GOP at all.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function filesWithNoGopIn(string $before)
    {
        return DB::table('files as f')
            ->select([
                'f.id',
                'f.mga_reference',
                'f.service_date',
                'f.provider_branch_id',
                'f.service_type_id',
            ])
            ->whereNotNull('f.service_date')
            ->where('f.service_date', '<', $before)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('gops as g')
                    ->whereColumn('g.file_id', 'f.id')
                    ->where('g.type', 'In');
            })
            ->orderBy('f.id')
            ->get();
    }
}

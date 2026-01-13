<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanOldTreatmentPlanFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'treatment-plans:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete treatment plan JSON files older than 3 months';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = Storage::disk('files');
        $directory = 'treatment-plans';

        if (!$disk->exists($directory)) {
            $this->info('Directory not found.');
            return Command::SUCCESS;
        }

        $files = $disk->files($directory);
        $cutoffDate = Carbon::now('Asia/Kolkata')->subMonths(3);

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(
                $disk->lastModified($file),
                'Asia/Kolkata'
            );

            if ($lastModified->lt($cutoffDate)) {
                $disk->delete($file);
                $this->info("Deleted: {$file}");
            }
        }

        return Command::SUCCESS;
    }
}

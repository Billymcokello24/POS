<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupTempPdfs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pdf:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up temporary PDF files older than 1 hour';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tempPath = storage_path('app/temp');

        if (!File::exists($tempPath)) {
            $this->info('No temp directory found.');
            return 0;
        }

        $files = File::files($tempPath);
        $deletedCount = 0;
        $oneHourAgo = now()->subHour()->timestamp;

        foreach ($files as $file) {
            if ($file->getMTime() < $oneHourAgo) {
                File::delete($file->getPathname());
                $deletedCount++;
            }
        }

        $this->info("Cleaned up {$deletedCount} temporary PDF file(s).");
        return 0;
    }
}

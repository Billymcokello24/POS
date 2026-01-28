<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PailCommand extends Command
{
    /**
     * The name and signature of the console command.
     * This matches the simple interface used in the dev script.
     *
     * @var string
     */
    protected $signature = 'pail {--timeout=0 : Time in seconds to keep tailing (0 = forever)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lightweight replacement for laravel/pail for local development (tails storage/logs/laravel.log)';

    public function handle()
    {
        $timeout = (int) $this->option('timeout');
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            $this->error('Log file not found: ' . $logFile);
            return 1;
        }

        $this->info('Tailing ' . $logFile . ($timeout ? " for $timeout seconds" : ''));

        $start = time();
        $fp = fopen($logFile, 'r');
        if ($fp === false) {
            $this->error('Failed to open log file');
            return 1;
        }

        // Seek to end initially (like `tail -f`)
        fseek($fp, 0, SEEK_END);

        // Run until timeout (if set) or forever
        while (true) {
            // Read any new lines available
            while (($line = fgets($fp)) !== false) {
                $this->output->write($line);
            }

            // If we reached EOF, wait briefly and retry — this is the blocking behaviour
            clearstatcache(false, $logFile);
            // If file was truncated/rotated, reposition to end
            $currentPos = ftell($fp);
            $fileSize = filesize($logFile);
            if ($fileSize < $currentPos) {
                // file truncated (log rotate), seek to start
                fseek($fp, 0, SEEK_END);
            }

            // Sleep briefly before checking for new data
            usleep(300000); // 300ms

            if ($timeout && (time() - $start) > $timeout) {
                break;
            }
        }

        fclose($fp);
        return 0;
    }
}

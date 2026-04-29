<?php

/**
 * Pantree PHP SDK — Laravel Artisan command for health reports
 *
 * Copy this file to app/Console/Commands/PantreeHealthCommand.php
 *
 * Usage:
 *   php artisan pantree:health
 *
 * Cron (alternative to the built-in scheduler):
 *   * /30 * * * *  www-data  php /var/www/html/artisan pantree:health
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Pantree\Laravel\Pantree;

class PantreeHealthCommand extends Command
{
    protected $signature   = 'pantree:health';
    protected $description = 'Send an encrypted Pantree health report';

    public function handle(): int
    {
        $result  = Pantree::sendHealthReport();
        $success = ($result['status'] ?? 0) === 200;

        if ($success) {
            $this->info('[Pantree] Health report sent successfully.');
        } else {
            $this->error('[Pantree] Health report failed (HTTP ' . ($result['status'] ?? 0) . ').');
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }
}

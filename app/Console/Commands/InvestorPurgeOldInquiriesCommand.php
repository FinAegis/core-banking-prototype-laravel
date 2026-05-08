<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InvestorInquiry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily retention sweep for the public investor inquiry form.
 *
 * Drops `investor_inquiries` rows older than 24 months. Runs daily at 03:00
 * via routes/console.php. Idempotent — safe to re-run on the same day.
 *
 *   php artisan investor:purge-old-inquiries
 *   php artisan investor:purge-old-inquiries --dry-run
 */
class InvestorPurgeOldInquiriesCommand extends Command
{
    protected $signature = 'investor:purge-old-inquiries
        {--dry-run : Print the count without deleting anything}';

    protected $description = 'Delete investor_inquiries rows older than 24 months (GDPR retention).';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subMonths(24);

        $query = InvestorInquiry::query()->where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->info('No investor inquiries older than 24 months. Nothing to do.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn(sprintf(
                'Found %d inquiry/inquiries older than %s. Re-run without --dry-run to purge.',
                $count,
                $cutoff->toDateTimeString(),
            ));

            return self::SUCCESS;
        }

        $deleted = InvestorInquiry::query()->where('created_at', '<', $cutoff)->delete();
        $this->info(sprintf('Purged %d investor inquiry/inquiries older than 24 months.', $deleted));

        return self::SUCCESS;
    }
}

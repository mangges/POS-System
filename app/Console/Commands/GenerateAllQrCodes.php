<?php

namespace App\Console\Commands;

use App\Models\Table;
use App\Services\QrCodeService;
use Illuminate\Console\Command;

class GenerateAllQrCodes extends Command
{
    protected $signature   = 'qr:generate-all';
    protected $description = 'Generate QR codes for all tables that do not have one yet';

    public function handle(QrCodeService $service): int
    {
        $tables = Table::all();
        $this->info("Found {$tables->count()} tables.");

        $bar = $this->output->createProgressBar($tables->count());
        $bar->start();

        $generated = 0;
        $failed    = 0;

        foreach ($tables as $table) {
            try {
                $service->generate($table);
                $generated++;
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("Failed for {$table->name}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Generated: {$generated}, Failed: {$failed}");

        return self::SUCCESS;
    }
}

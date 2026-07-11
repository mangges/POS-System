<?php

namespace Database\Seeders;

use App\Models\Table;
use App\Services\QrCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TableSeeder extends Seeder
{
    /**
     * Seed 10 sample tables and generate a QR code for each.
     *
     * The qr_token is set explicitly so we get predictable tokens in dev.
     * In production the model's booted() hook auto-assigns a random token
     * when qr_token is left null.
     */
    public function run(): void
    {
        $service = app(QrCodeService::class);

        // Link storage to public if not already linked (safe to call multiple times)
        if (! file_exists(public_path('storage'))) {
            \Artisan::call('storage:link');
        }

        $tableNames = [
            'Meja 1',  'Meja 2',  'Meja 3',  'Meja 4',  'Meja 5',
            'Meja 6',  'Meja 7',  'Meja 8',  'VIP 1',   'VIP 2',
        ];

        foreach ($tableNames as $name) {
            // Skip if already seeded (idempotent)
            $exists = Table::where('name', $name)->exists();
            if ($exists) {
                continue;
            }

            /** @var Table $table */
            $table = Table::create([
                'name'      => $name,
                'number'    => $name,           // keep legacy column populated
                'barcode'   => 'TBL-' . strtoupper(Str::random(6)),
                'qr_token'  => Str::random(16),
                'status'    => 'available',
            ]);

            // Generate QR PNG and persist the qr_codes record
            try {
                $service->generate($table);
                $this->command->info("✓ QR generated for {$name}");
            } catch (\Throwable $e) {
                $this->command->warn("⚠ QR generation failed for {$name}: " . $e->getMessage());
            }
        }
    }
}

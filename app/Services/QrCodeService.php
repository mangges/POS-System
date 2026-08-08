<?php

namespace App\Services;

use App\Models\QrCode;
use App\Models\Table;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;

class QrCodeService
{
    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Generate a QR SVG for the given table, persist to storage, and upsert
     * the qr_codes record.
     *
     * We use SVG format instead of PNG because SVG rendering does NOT require
     * the PHP imagick extension, making it compatible with any standard PHP
     * installation. SVG is also resolution-independent (looks crisp at any
     * size) and is natively renderable in browsers and Filament ImageColumn.
     *
     * File path pattern:
     *   qrcodes/table_{table_id}/table_{table_id}_{timestamp}.svg
     *
     * @return QrCode  The persisted QrCode model.
     */
    public function generate(Table $table): QrCode
    {
        $url      = $table->getOrderUrl();
        $filePath = $this->buildFilePath($table);

        // Render QR as SVG string (no imagick needed)
        $svgData = QrCodeGenerator::format('svg')
            ->size(400)
            ->errorCorrection('H')
            ->margin(2)
            ->generate($url);

        // Ensure directory exists and write file
        Storage::disk('public')->put($filePath, $svgData);

        // Upsert qr_codes record (one active record per table)
        $qrCode = QrCode::updateOrCreate(
            ['table_id' => $table->id],
            [
                'qr_url'    => $url,
                'file_path' => $filePath,
            ]
        );

        return $qrCode;
    }

    /**
     * Re-generate QR for a table:
     *   1. Delete the old SVG from storage (if exists).
     *   2. Rotate the qr_token on the Table (old QR links become invalid).
     *   3. Generate fresh SVG and update the qr_codes record.
     *
     * IMPORTANT: rotateToken() is called BEFORE generate() so the newly
     * encoded URL already contains the fresh token — the old token is
     * permanently invalidated at that point.
     *
     * @return QrCode  The updated QrCode model.
     */
    public function regenerate(Table $table): QrCode
    {
        // Delete old file if present
        $existingQr = $table->qrCode;
        if ($existingQr && Storage::disk('public')->exists($existingQr->file_path)) {
            Storage::disk('public')->delete($existingQr->file_path);
        }

        // Rotate token FIRST so the new URL is already encoded into the QR
        $table->rotateToken();

        // Reload to ensure fresh token is used inside generate()
        $table->refresh();

        return $this->generate($table);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function buildFilePath(Table $table): string
    {
        $timestamp = now()->format('YmdHis');
        return "qrcodes/table_{$table->id}/table_{$table->id}_{$timestamp}.svg";
    }
}

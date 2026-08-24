<?php

namespace App\Filament\Pages\Reports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsExcel
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     * @param  array<int, int>  $moneyColumns  1-based column indexes to format as Rupiah
     */
    protected function exportExcel(
        string $filenamePrefix,
        array $headers,
        iterable $rows,
        array $moneyColumns = [],
        ?string $totalLabel = null,
        float|int|null $totalValue = null,
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $columnCount = count($headers);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(20);

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray(array_map($this->escapeFormula(...), $row), null, "A{$rowIndex}");
            $rowIndex++;
        }
        $lastDataRow = $rowIndex - 1;

        foreach ($moneyColumns as $columnIndex) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getStyle("{$column}2:{$column}{$lastDataRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        }

        $lastStyledRow = $lastDataRow;

        if ($totalLabel !== null) {
            $sheet->setCellValue("A{$rowIndex}", $totalLabel);
            $sheet->setCellValue("{$lastColumn}{$rowIndex}", $totalValue);
            $sheet->mergeCells("A{$rowIndex}:" . Coordinate::stringFromColumnIndex($columnCount - 1) . $rowIndex);
            $sheet->getStyle("A{$rowIndex}:{$lastColumn}{$rowIndex}")->applyFromArray([
                'font' => ['bold' => true],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
            ]);
            $sheet->getStyle("A{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$lastColumn}{$rowIndex}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
            $lastStyledRow = $rowIndex;
        }

        $sheet->getStyle("A1:{$lastColumn}{$lastStyledRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        foreach (range(1, $columnCount) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, "{$filenamePrefix}-" . now()->format('Ymd-His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function escapeFormula(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}

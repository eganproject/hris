<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Turns a failed employee import into a downloadable Excel workbook built on top
 * of the file the user uploaded: the original data sheet is kept as-is but the
 * offending cells are highlighted and a trailing "Kesalahan" column spells out
 * what is wrong per row, and a dedicated "Kesalahan" sheet lists every problem
 * as Baris / Kolom / Keterangan so the user can see exactly where to fix it.
 */
class EmployeeImportErrorReport
{
    private const RED_FILL = 'FFC7CE';

    private const RED_TEXT = '9C0006';

    /**
     * @param  list<array{row: ?int, column: ?string, message: string}>  $errors
     */
    public static function download(string $sourcePath, array $errors, string $downloadName): BinaryFileResponse
    {
        $spreadsheet = IOFactory::load($sourcePath);

        $dataSheet = $spreadsheet->getSheet(0);
        self::annotateDataSheet($dataSheet, $errors);
        self::addErrorSheet($spreadsheet, $errors);

        $spreadsheet->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'import-error-').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tmp);
        $spreadsheet->disconnectWorksheets();

        return response()
            ->download($tmp, $downloadName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Highlight the offending cells and append a per-row "Kesalahan" column.
     *
     * @param  list<array{row: ?int, column: ?string, message: string}>  $errors
     */
    private static function annotateDataSheet(Worksheet $sheet, array $errors): void
    {
        // Map each header (lowercased) in row 1 to its column letter so a
        // problem tied to a column can be traced back to the physical cell.
        $lastColIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $headerToLetter = [];
        for ($col = 1; $col <= $lastColIndex; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $header = strtolower(trim((string) $sheet->getCell($letter.'1')->getValue()));
            if ($header !== '') {
                $headerToLetter[$header] = $letter;
            }
        }

        $noteLetter = Coordinate::stringFromColumnIndex($lastColIndex + 1);
        $sheet->setCellValue($noteLetter.'1', 'Kesalahan');

        // Group problems per row so each row gets one combined note.
        $byRow = [];
        foreach ($errors as $error) {
            if ($error['row'] === null) {
                continue;
            }
            $byRow[$error['row']][] = $error;

            // Highlight the specific cell when we can map its column.
            $key = $error['column'] !== null ? strtolower($error['column']) : null;
            if ($key !== null && isset($headerToLetter[$key])) {
                self::fillRed($sheet, $headerToLetter[$key].$error['row']);
            }
        }

        foreach ($byRow as $rowNumber => $rowErrors) {
            $lines = array_map(
                fn (array $e) => ($e['column'] !== null ? $e['column'].': ' : '').$e['message'],
                $rowErrors,
            );

            $noteCell = $noteLetter.$rowNumber;
            $sheet->setCellValue($noteCell, implode("\n", $lines));
            self::fillRed($sheet, $noteCell);
            $sheet->getStyle($noteCell)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        }

        $sheet->getStyle('A1:'.$noteLetter.'1')->getFont()->setBold(true);
        $sheet->getColumnDimension($noteLetter)->setWidth(55);
    }

    /**
     * A flat, sortable list of every problem: Baris / Kolom / Keterangan.
     *
     * @param  list<array{row: ?int, column: ?string, message: string}>  $errors
     */
    private static function addErrorSheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, array $errors): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Kesalahan');

        $sheet->fromArray(['Baris', 'Kolom', 'Keterangan Kesalahan'], null, 'A1');

        $row = 2;
        foreach ($errors as $error) {
            $sheet->setCellValue('A'.$row, $error['row'] !== null ? 'Baris '.$error['row'] : 'File');
            $sheet->setCellValue('B'.$row, $error['column'] ?? '(seluruh baris)');
            $sheet->setCellValue('C'.$row, $error['message']);
            $row++;
        }

        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(26);
        $sheet->getColumnDimension('C')->setWidth(80);
        $sheet->getStyle('C2:C'.max(2, $row - 1))->getAlignment()->setWrapText(true);
    }

    private static function fillRed(Worksheet $sheet, string $cell): void
    {
        $style = $sheet->getStyle($cell);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::RED_FILL);
        $style->getFont()->getColor()->setRGB(self::RED_TEXT);
    }
}

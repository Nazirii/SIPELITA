<?php

namespace App\Services;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


class ExcelService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    public function import(string $path)
    {
        return SimpleExcelReader::create($path)->getRows()->toArray();
    }

    // --- EXPORT / GENERATE ---
     public function simpleExport(string $path, array $rows, string $disk = 'pusdatin')
    {
        // Pastikan folder tersedia
        Storage::disk($disk)->makeDirectory(dirname($path));

        // Full local path
        $fullPath = Storage::disk($disk)->path($path);

        // Create writer
        $writer = SimpleExcelWriter::create($fullPath);

        // Tambahkan header otomatis
        if (!empty($rows)) {
            $writer->addHeader(array_keys($rows[0]));
        }

        // Tambahkan data row
        foreach ($rows as $row) {
            $writer->addRow($row);
        }

        $writer->close();

        return $path; // path relative
    }
   
// ...existing code...

public function generateTemplatePenilaianPenghargaan(array $eligible,array $multiplier,string $path,string $disk='templates'){
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // ================================
    // 1. HEADER BARIS 1 (MERGED)
    // ================================
    $sheet->setCellValue('A1', 'ID Dinas');
    $sheet->mergeCells('A1:A2');
    
    $sheet->setCellValue('B1', 'Dinas Lingkungan Hidup');
    $sheet->mergeCells('B1:B2');

    $sheet->setCellValue('C1', 'Adipura');
    $sheet->mergeCells('C1:E1');

    $sheet->setCellValue('F1', 'Adiwiyata');
    $sheet->mergeCells('F1:H1');

    $sheet->setCellValue('I1', 'Proklim');
    $sheet->mergeCells('I1:K1');

    $sheet->setCellValue('L1', 'Proper');
    $sheet->mergeCells('L1:N1');

    $sheet->setCellValue('O1', 'Kaltaparu');
    $sheet->mergeCells('O1:Q1');

    // ================================
    // 2. HEADER BARIS 2
    // ================================
    $sheet->setCellValue('C2', 'Jumlah Wilayah');
    $sheet->setCellValue('D2', 'Skor max');
    $sheet->setCellValue('E2', 'Skor');

    $sheet->setCellValue('F2', 'Jumlah Sekolah Adiwiyata');
    $sheet->setCellValue('G2', 'Skor max');
    $sheet->setCellValue('H2', 'Skor');

    $sheet->setCellValue('I2', 'Jumlah Desa Proklim');
    $sheet->setCellValue('J2', 'Skor max');
    $sheet->setCellValue('K2', 'Skor');

    $sheet->setCellValue('L2', 'Jumlah Perusahaan');
    $sheet->setCellValue('M2', 'Skor max');
    $sheet->setCellValue('N2', 'Skor');

    $sheet->setCellValue('O2', 'Jumlah Penerima');
    $sheet->setCellValue('P2', 'Skor max');
    $sheet->setCellValue('Q2', 'Skor');

    //HIAS DIKIT
    $headerStyle = [
        'font' => ['bold' => true],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9D9D9'],
        ],
    ];

    // Apply ke seluruh header (A1:Q2)
    $sheet->getStyle('A1:Q2')->applyFromArray($headerStyle);

    //INJECT MULTIPLIER
    $inject = new Worksheet($spreadsheet, 'inject');
    $spreadsheet->addSheet($inject);

    $row = 1;
    foreach ($multiplier as $key => $val) {
        $inject->setCellValue("A{$row}", $key);
        $inject->setCellValue("B{$row}", $val);
        $row++;
    }
    $inject->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

    //ISI DATA YG LOLOS EUY
    $start = 3; // data dimulai di baris 3

    foreach ($eligible as $i => $p) {
        $r = $start + $i;

        // **FIX: TAMBAHKAN ID_DINAS**
        $sheet->setCellValue("A{$r}", $p['id_dinas']);
        $sheet->setCellValue("B{$r}", $p['nama_dinas']);

        // =============== ADIPURA ===============
        $sheet->setCellValue("D{$r}", "=C{$r} * VLOOKUP(\"adipura\", inject!A:B, 2, FALSE)");

        // =============== ADIWIYATA ===============
        $sheet->setCellValue("G{$r}", "=F{$r} * VLOOKUP(\"adiwiyata\", inject!A:B, 2, FALSE)");

        // =============== PROKLIM ===============
        $sheet->setCellValue("J{$r}", "=I{$r} * VLOOKUP(\"proklim\", inject!A:B, 2, FALSE)");

        // =============== PROPER ===============
        $sheet->setCellValue("M{$r}", "=L{$r} * VLOOKUP(\"proper\", inject!A:B, 2, FALSE)");

        // =============== KALTAPARU ===============
        $sheet->setCellValue("P{$r}", "=O{$r} * VLOOKUP(\"kaltaparu\", inject!A:B, 2, FALSE)");
    }

    //LOCK SHEET BIAR G SEMBARANGAN DIUBAH
    $lastRow = $start + count($eligible) - 1;

    // Unlock kolom input user saja (GESER KE KANAN KARENA ADA ID_DINAS)
    $sheet->getStyle("C3:C{$lastRow}")->getProtection()->setLocked(false);
    $sheet->getStyle("E3:E{$lastRow}")->getProtection()->setLocked(false);
    
    $sheet->getStyle("F3:F{$lastRow}")->getProtection()->setLocked(false);
    $sheet->getStyle("H3:H{$lastRow}")->getProtection()->setLocked(false);

    $sheet->getStyle("I3:I{$lastRow}")->getProtection()->setLocked(false);
    $sheet->getStyle("K3:K{$lastRow}")->getProtection()->setLocked(false);

    $sheet->getStyle("L3:L{$lastRow}")->getProtection()->setLocked(false);
    $sheet->getStyle("N3:N{$lastRow}")->getProtection()->setLocked(false);

    $sheet->getStyle("O3:O{$lastRow}")->getProtection()->setLocked(false);
    $sheet->getStyle("Q3:Q{$lastRow}")->getProtection()->setLocked(false);

    // Protect sheet
    $sheet->getProtection()->setSheet(true);
    $sheet->getProtection()->setPassword('locked');

    //hias lagi dikit
    foreach (range('A','Q') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // =============================
    // DATA VALIDATION UNTUK SKOR
    // =============================
    foreach ($eligible as $i => $p) {
        $r = $start + $i;

        // ADIPURA → E <= D
        $dv = $sheet->getCell("E{$r}")->getDataValidation();
        $dv->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
        $dv->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_LESSTHANOREQUAL);
        $dv->setAllowBlank(true);
        $dv->setShowErrorMessage(true);
        $dv->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $dv->setError('Skor tidak boleh melebihi skor maksimum!');
        $dv->setFormula1("D{$r}");

        // ADIWIYATA → H <= G
        $dv = $sheet->getCell("H{$r}")->getDataValidation();
        $dv->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
        $dv->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_LESSTHANOREQUAL);
        $dv->setAllowBlank(true);
        $dv->setShowErrorMessage(true);
        $dv->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $dv->setError('Skor tidak boleh melebihi skor maksimum!');
        $dv->setFormula1("G{$r}");

        // PROKLIM → K <= J
        $dv = $sheet->getCell("K{$r}")->getDataValidation();
        $dv->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
        $dv->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_LESSTHANOREQUAL);
        $dv->setAllowBlank(true);
        $dv->setShowErrorMessage(true);
        $dv->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $dv->setError('Skor tidak boleh melebihi skor maksimum!');
        $dv->setFormula1("J{$r}");

        // PROPER → N <= M
        $dv = $sheet->getCell("N{$r}")->getDataValidation();
        $dv->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
        $dv->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_LESSTHANOREQUAL);
        $dv->setAllowBlank(true);
        $dv->setShowErrorMessage(true);
        $dv->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $dv->setError('Skor tidak boleh melebihi skor maksimum!');
        $dv->setFormula1("M{$r}");

        // KALTAPARU → Q <= P
        $dv = $sheet->getCell("Q{$r}")->getDataValidation();
        $dv->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
        $dv->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_LESSTHANOREQUAL);
        $dv->setAllowBlank(true);
        $dv->setShowErrorMessage(true);
        $dv->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $dv->setError('Skor tidak boleh melebihi skor maksimum!');
        $dv->setFormula1("P{$r}");
    }

    //SIMPAN EUY
    Storage::disk($disk)->makeDirectory(dirname($path));
    
    // Hapus file lama jika ada
    if (Storage::disk($disk)->exists($path)) {
        Storage::disk($disk)->delete($path);
        
    }
    
    $fullPath = Storage::disk($disk)->path($path);

    $writer = new Xlsx($spreadsheet);
    $writer->save($fullPath);

    return $path;
}
}

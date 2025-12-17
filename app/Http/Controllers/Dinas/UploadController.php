<?php

namespace App\Http\Controllers\Dinas;

use App\Http\Controllers\Controller;
use App\Models\Files\LaporanUtama;
use App\Models\Files\RingkasanEksekutif;
use App\Models\Files\TabelUtama;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Files\Iklh;
use App\Helpers\MatraConstants;  
class UploadController extends Controller
{
    private const DLH_DISK = 'dlh';
    private const LEGACY_DISK = 'public';

    protected $DocumentFinalizer;
    public function __construct(\App\Services\DocumentFinalizer $DocumentFinalizer)
    {
        $this->DocumentFinalizer = $DocumentFinalizer;
    }
    private function deleteExistingPath(?string $path): void
    {
        if (! $path) {
            return;
        }

        foreach ([self::DLH_DISK, self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }
    public function uploadRingkasanEksekutif(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:5020', 

        ],[
            'file.required' => 'File ringkasan eksekutif wajib diunggah.',
            'file.mimes' => 'File harus berformat PDF.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);
        $submission = $request->submission;
        $tahun = $submission->tahun;
        $id_dinas = $submission->id_dinas;
        
        $existing = RingkasanEksekutif::where('submission_id', $submission->id)->first();
        if ($existing) {
            $this->deleteExistingPath($existing->path);
        }

        $folder = "uploads/{$tahun}/dlh_{$id_dinas}/ringkasan_eksekutif";
        $path = $request->file('file')->storeAs(
            $folder,
            "{$id_dinas}.{$tahun}.{$request->file('file')->getClientOriginalExtension()}",
            self::DLH_DISK 
        );

        if ($existing) {
            $existing->update([
                'path' => $path,
                'status' => 'draft',
            ]);
        }
        else {

            RingkasanEksekutif::create([
                    'submission_id' => $submission->id,
                    'status' => 'draft',
                    'path' => $path,
                ]);
        }
         return response()->json([
        'message' => $existing ? 'File berhasil diganti' : 'File berhasil diupload',
        'path' => $path,
        ]);
    }
    // }
    public function uploadLaporanUtama(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:5020', 

        ],[
            'file.required' => 'File laporan utama wajib diunggah.',
            'file.mimes' => 'File harus berformat PDF.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ]);
        $submission = $request->submission;
        $tahun = $submission->tahun;
        $id_dinas = $submission->id_dinas;
        
        $existing = LaporanUtama::where('submission_id', $submission->id)->first();
        if ($existing) {
            $this->deleteExistingPath($existing->path);
        }
    
        $folder = "uploads/{$tahun}/dlh_{$id_dinas}/laporan_utama";
        $path = $request->file('file')->storeAs(
            $folder,
            "{$id_dinas}.{$tahun}.{$request->file('file')->getClientOriginalExtension()}",
            self::DLH_DISK 
        );
    
        if ($existing) {
            $existing->update([
                'path' => $path,
                'status' => 'draft',
            ]);
        }
        else {
    
            LaporanUtama::create([
                    'submission_id' => $submission->id,
                    'status' => 'draft',
                    'path' => $path,
                ]);
        }
         return response()->json([
        'message' => $existing ? 'File berhasil diganti' : 'File berhasil diupload',
        'path' => $path,
        ]);
        // Logic for uploading Laporan Utama
    }
    public function uploadTabelUtama(Request $request)
    {   
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx,csv|max:5020', 
            'kode_tabel' => 'required|string',
            'matra' => 'required|string',
        ],[
            'file.required' => 'File tabel utama wajib diunggah.',
            'file.mimes' => 'File harus berformat XLS, XLSX, atau CSV.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
            'kode_tabel.required' => 'Kode tabel wajib diisi.',
            'matra.required' => 'Matra wajib diisi.',
        ]);
        
        // Validasi matra harus ada di MatraConstants
        $validMatras = MatraConstants::MATRA_LIST;
        $matra = $request->input('matra');
        
        if (!in_array($matra, $validMatras)) {
            return response()->json([
                'message' => 'Matra tidak valid. Pilih salah satu dari: ' . implode(', ', $validMatras),
            ], 422);
        }
        
        // Logic for uploading Tabel Utama
        $submission = $request->submission;
        $tahun = $submission->tahun;
        $id_dinas = $submission->id_dinas;

        $kode_tabel = $request->input('kode_tabel');
        
        // Validasi: kode_tabel harus sesuai dengan matra yang benar menurut MatraConstants
        $expectedMatra = MatraConstants::getMatraByKode($kode_tabel);
        
        if ($expectedMatra === null) {
            return response()->json([
                'message' => "Kode tabel {$kode_tabel} tidak valid.",
            ], 422);
        }
        
        if ($expectedMatra !== $matra) {
            return response()->json([
                'message' => "Kode tabel {$kode_tabel} tidak sesuai dengan matra {$matra}. Kode tabel ini seharusnya masuk kategori matra {$expectedMatra}.",
            ], 422);
        }
        
        // Cek existing berdasarkan submission_id dan kode_tabel (matra sudah pasti benar)
        $existing = TabelUtama::where([
            'submission_id' => $submission->id,
            'kode_tabel' => $kode_tabel,
        ])->first();
        
        if ($existing) {
            $this->deleteExistingPath($existing->path);
        }
        
        // Sanitize matra name: hapus spasi, koma, dan karakter khusus untuk nama folder
        $matraSanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', str_replace(' ', '_', $matra));
        // Sanitize kode_tabel: hapus karakter yang tidak valid untuk filename Windows (||, (), dll)
        $kodeTabelSanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', str_replace(['||', ' ', '(', ')', ',', '.'], '_', $kode_tabel));
        // Hapus underscore berlebih
        $kodeTabelSanitized = preg_replace('/_+/', '_', trim($kodeTabelSanitized, '_'));
        
        $folder = "uploads/{$tahun}/dlh_{$id_dinas}/tabel_utama/{$matraSanitized}";
        $filename = "{$id_dinas}.{$tahun}.{$kodeTabelSanitized}.{$request->file('file')->getClientOriginalExtension()}";
        
        $path = $request->file('file')->storeAs(
            $folder,
            $filename,
            self::DLH_DISK 
        );
        
        // Debug: pastikan path tidak false atau empty
        if (!$path) {
            return response()->json([
                'message' => 'Gagal menyimpan file ke storage',
                'debug' => [
                    'folder' => $folder,
                    'filename' => $filename,
                    'disk' => self::DLH_DISK,
                ]
            ], 500);
        }
        
        if ($existing) {
            $existing->update([
                'path' => $path,
                'status' => 'draft',
            ]);
        }
        else {
    
            TabelUtama::create([
                    'submission_id' => $submission->id,
                    'kode_tabel' => $kode_tabel,
                    'matra' => $matra,
                    'status' => 'draft',
                    'path' => $path,
                ]);
        }
         return response()->json([
        'message' => $existing ? 'File berhasil diganti' : 'File berhasil diupload',
        'path' => $path,
        ]);

    } 

    public function uploadIklh(Request $request)
    {   
        $submission = $request->submission;
        
        // Debug: cek apakah id_dinas ada
      
        $dinas = $submission->dinas;
        
        // Debug: cek apakah dinas berhasil di-load
      
        $hasPesisir = $dinas->region->has_pesisir;

        // Validasi dinamis berdasarkan has_pesisir
        $rules = [
            'indeks_kualitas_air' => 'required|numeric|min:0|max:100',
            'indeks_kualitas_udara' => 'required|numeric|min:0|max:100',
            'indeks_kualitas_lahan' => 'required|numeric|min:0|max:100',
            'indeks_kualitas_kehati' => 'required|numeric|min:0|max:100',
        ];

        $messages = [
            'indeks_kualitas_air.required' => 'Indeks kualitas air wajib diisi.',
            'indeks_kualitas_air.numeric' => 'Indeks kualitas air harus berupa angka.',
            'indeks_kualitas_air.min' => 'Indeks kualitas air minimal 0.',
            'indeks_kualitas_air.max' => 'Indeks kualitas air maksimal 100.',
            'indeks_kualitas_udara.required' => 'Indeks kualitas udara wajib diisi.',
            'indeks_kualitas_udara.numeric' => 'Indeks kualitas udara harus berupa angka.',
            'indeks_kualitas_udara.min' => 'Indeks kualitas udara minimal 0.',
            'indeks_kualitas_udara.max' => 'Indeks kualitas udara maksimal 100.',
            'indeks_kualitas_lahan.required' => 'Indeks kualitas lahan wajib diisi.',
            'indeks_kualitas_lahan.numeric' => 'Indeks kualitas lahan harus berupa angka.',
            'indeks_kualitas_lahan.min' => 'Indeks kualitas lahan minimal 0.',
            'indeks_kualitas_lahan.max' => 'Indeks kualitas lahan maksimal 100.',
            'indeks_kualitas_kehati.required' => 'Indeks kualitas kehati wajib diisi.',
            'indeks_kualitas_kehati.numeric' => 'Indeks kualitas kehati harus berupa angka.',
            'indeks_kualitas_kehati.min' => 'Indeks kualitas kehati minimal 0.',
            'indeks_kualitas_kehati.max' => 'Indeks kualitas kehati maksimal 100.',
        ];

        // Tambahkan validasi pesisir hanya jika region memiliki pesisir
        if ($hasPesisir) {
            $rules['indeks_kualitas_pesisir_laut'] = 'required|numeric|min:0|max:100';
            $messages['indeks_kualitas_pesisir_laut.required'] = 'Indeks kualitas pesisir dan laut wajib diisi.';
            $messages['indeks_kualitas_pesisir_laut.numeric'] = 'Indeks kualitas pesisir dan laut harus berupa angka.';
            $messages['indeks_kualitas_pesisir_laut.min'] = 'Indeks kualitas pesisir dan laut minimal 0.';
            $messages['indeks_kualitas_pesisir_laut.max'] = 'Indeks kualitas pesisir dan laut maksimal 100.';
        }

        $request->validate($rules, $messages);

        // Logic for uploading Iklh
        $existing = Iklh::where('submission_id', $submission->id)->first();

        $data = [
            'indeks_kualitas_air' => $request->input('indeks_kualitas_air'),
            'indeks_kualitas_udara' => $request->input('indeks_kualitas_udara'),
            'indeks_kualitas_lahan' => $request->input('indeks_kualitas_lahan'),
            'indeks_kualitas_kehati' => $request->input('indeks_kualitas_kehati'),
            'status' => 'draft',
        ];

        // Hanya tambahkan indeks pesisir jika region memiliki pesisir
        if ($hasPesisir) {
            $data['indeks_kualitas_pesisir_laut'] = $request->input('indeks_kualitas_pesisir_laut');
        } else {
            $data['indeks_kualitas_pesisir_laut'] = null;
        }

        if ($existing) {
            $existing->update($data);
        } else {
            $data['submission_id'] = $submission->id;
            Iklh::create($data);
        }

        return response()->json([
            'message' => $existing ? 'Nilai berhasil diganti' : 'Nilai berhasil diupload',
        ]);
    }
   
 
        // Logic for finalizing ringkasan
    public function finalizeSubmission(Request $request)
    {
        // Logic for finalizing submission
      
        $submission = $request->submission->load('ringkasanEksekutif', 'laporanUtama', 'tabelUtama', 'iklh');
       
        if($submission->status=='finalized' || $submission->status=='approved'){
            return response()->json([
                'message' => 'Submission tahun ini sudah difinalisasi, tidak dapat diubah.'
            ], 403);
        }
        try{
            $this->DocumentFinalizer->finalizeall([
                'ringkasanEksekutif'=>$submission->ringkasanEksekutif,
                'laporanUtama'=>$submission->laporanUtama,
                'tabelUtama'=>['document'=>$submission->tabelUtama, 'expected_count'=>TabelUtama::MIN_COUNT],
                'iklh'=>$submission->iklh,
            ]);

            $submission->update([
                'status'=>'finalized',
            ]);
            return response()->json([
                'message' => 'Submission berhasil difinalisasi.',
            ]);
        }catch(\Exception $e){
            $errorMessages = json_decode($e->getMessage(), true);
            return response()->json([
                'message' => 'Gagal memfinalisasi submission.',
                'errors' => $errorMessages,
            ], 400);
        }
    }
    public function finalizeOne(Request $request,$type){

        $submission = $request->submission->load($type);
        try{
            $document = $submission->$type;
            if($document instanceof \Illuminate\Support\Collection || is_array($document)){
                $modelClass = $submission->$type()->getModel()::class;
               $count= $modelClass::MIN_COUNT ?? null;
               $this->DocumentFinalizer->finalizecollection($document,$type,$count);
            }else{
                $this->DocumentFinalizer->finalize($document,$type);
            }
            return response()->json([
                'message' => "$type berhasil difinalisasi.",
            ]);
        }catch(\Exception $e){
            return response()->json([
                'message' => "Gagal memfinalisasi $type. ",
                'error' => $e->getMessage(),
            ], 400);
        }


}   
    
    public function getStatusDokumen(Request $request)
    {
        $submission = $request->submission->load([
            'ringkasanEksekutif',
            'laporanUtama', 
            'tabelUtama',
            'iklh'
        ]);

        $statusDokumen = [];

        // Status Ringkasan Eksekutif
        $ringkasanExists = $submission->ringkasanEksekutif !== null;
        $statusDokumen[] = [
            'jenis_dokumen' => 'Ringkasan Eksekutif',
            'status_upload' => $ringkasanExists ? 'Dokumen Diunggah' : 'Belum Diunggah',
            'tanggal_upload' => $ringkasanExists ? $submission->ringkasanEksekutif->updated_at->format('d-m-Y') : null,
            'status' => $ringkasanExists ? $submission->ringkasanEksekutif->status : '-',
        ];

        // Status Laporan Utama
        $laporanExists = $submission->laporanUtama !== null;
        $statusDokumen[] = [
            'jenis_dokumen' => 'Laporan Utama',
            'status_upload' => $laporanExists ? 'Dokumen Diunggah' : 'Belum Diunggah',
            'tanggal_upload' => $laporanExists ? $submission->laporanUtama->updated_at->format('d-m-Y') : null,
            'status' => $laporanExists ? $submission->laporanUtama->status : '-',
        ];

        // Status Tabel Utama
        $tabelUtamaExists = $submission->tabelUtama->count() > 0;
        $tabelUtamaUploadDate = null;
        $tabelUtamaStatus = '-';
        
        if ($tabelUtamaExists) {
            $tabelUtamaUploadDate = $submission->tabelUtama->max('updated_at');
            // Ambil status yang paling rendah (prioritas: draft > finalized > approved > rejected)
            $statuses = $submission->tabelUtama->pluck('status')->unique();
            if ($statuses->contains('draft')) {
                $tabelUtamaStatus = 'draft';
            }
             elseif ($statuses->every(fn($s) => $s === 'finalized')) {
                $tabelUtamaStatus = 'finalized';
            } 
        }

        $statusDokumen[] = [
            'jenis_dokumen' => 'SLHD Tabel Utama',
            'status_upload' => $tabelUtamaExists ? 'Dokumen Diunggah' : 'Belum Diunggah',
            'tanggal_upload' => $tabelUtamaUploadDate ? $tabelUtamaUploadDate->format('d-m-Y') : null,
            'status' => $tabelUtamaStatus,
        ];

        // Status IKLH
        $iklhExists = $submission->iklh !== null;
        $statusDokumen[] = [
            'nama' => 'iklh',
            'jenis_dokumen' => 'IKLH',
            'status_upload' => $iklhExists ? 'Dokumen Diunggah' : 'Belum Diunggah',
            'tanggal_upload' => $iklhExists ? $submission->iklh->updated_at->format('d-m-Y') : null,
            'status' => $iklhExists ? $submission->iklh->status : '-',
            'uploaded' => $iklhExists,
            'data' => $iklhExists ? [
                'indeks_kualitas_air' => $submission->iklh->indeks_kualitas_air,
                'indeks_kualitas_udara' => $submission->iklh->indeks_kualitas_udara,
                'indeks_kualitas_lahan' => $submission->iklh->indeks_kualitas_lahan,
                'indeks_kualitas_pesisir_laut' => $submission->iklh->indeks_kualitas_pesisir_laut,
                'indeks_kualitas_kehati' => $submission->iklh->indeks_kualitas_kehati,
            ] : null,
        ];

        return response()->json([
            'data' => $statusDokumen
        ]);
    }

    /**
     * Get list of all matra categories
     */
    public function getMatraList()
    {
        $matraList = collect(MatraConstants::MATRA_LIST)->map(function($matra) {
            // Hitung jumlah tabel per matra
            $tableCount = collect(MatraConstants::TABEL_TO_MATRA)
                ->filter(fn($m) => $m === $matra)
                ->count();

            return [
                'nama_matra' => $matra,
                'jumlah_tabel' => $tableCount,
            ];
        });

        return response()->json([
            'data' => $matraList
        ]);
    }

    /**
     * Get list of tables by matra
     */
    public function getTabelByMatra(Request $request, string $matra)
    {
        // Validasi matra
        if (!in_array($matra, MatraConstants::MATRA_LIST)) {
            return response()->json([
                'message' => 'Matra tidak valid.'
            ], 422);
        }

        // Ambil semua tabel untuk matra ini
        $tabelList = collect(MatraConstants::TABEL_TO_MATRA)
            ->filter(fn($m) => $m === $matra)
            ->map(function($m, $kodeTabel) {
                return [
                    'kode_tabel' => $kodeTabel,
                    'matra' => $m,
                    'has_template' => $this->checkTemplateExists($kodeTabel),
                ];
            })
            ->values();

        return response()->json([
            'matra' => $matra,
            'data' => $tabelList
        ]);
    }

    /**
     * Download template for specific kode tabel
     */
    public function downloadTemplate(string $kodeTabel)
    {
        // Validasi kode tabel
        if (!MatraConstants::isValidKode($kodeTabel)) {
            return response()->json([
                'message' => 'Kode tabel tidak valid.'
            ], 422);
        }

        $matra = MatraConstants::getMatraByKode($kodeTabel);
        
        // Sanitize untuk path
        $matraSanitized = str_replace([' ', ',', '.'], '_', $matra);
        $kodeTabelSanitized = str_replace([' '], '_', $kodeTabel);
        
        // Path template: tabel_utama/{matra}/{kodeTabel}.xlsx (di disk templates)
        $templatePath = "tabel_utama/{$matraSanitized}/{$kodeTabelSanitized}.xlsx";

        if (!Storage::disk('templates')->exists($templatePath)) {
            return response()->json([
                'message' => "Template untuk {$kodeTabel} belum tersedia."
            ], 404);
        }
        @ob_clean();

        return Storage::disk('templates')->download($templatePath, "{$kodeTabelSanitized}_template.xlsx");
    }

    /**
     * Check if template exists for kode tabel
     */
    private function checkTemplateExists(string $kodeTabel): bool
    {
        $matra = MatraConstants::getMatraByKode($kodeTabel);
        $matraSanitized = str_replace([' ', ',', '.'], '_', $matra);
        $kodeTabelSanitized = str_replace([' '], '_', $kodeTabel);
        $templatePath = "tabel_utama/{$matraSanitized}/{$kodeTabelSanitized}.xlsx";
        return Storage::disk('templates')->exists($templatePath);
    }

    /**
     * Download all templates ZIP (pre-made by admin)
     */
    public function downloadAllTemplatesZip()
    {
        $zipPath = "tabel_utama_zip/Tabel Utama.zip";

        if (!Storage::disk('templates')->exists($zipPath)) {
            return response()->json([
                'message' => 'File template ZIP belum tersedia.'
            ], 404);
        }
        @ob_clean();
        return Storage::disk('templates')->download($zipPath, "Template_Tabel_Utama_SLHD.zip");
    }

    /**
     * Download templates ZIP per matra
     * Mapping matra ke nama file ZIP
     */
    public function downloadMatraZip(string $matra)
    {
        // Mapping nama matra ke nama file ZIP
        $matraToZipMap = [
            'Keanekaragaman Hayati' => 'Keanekaragaman_Hayati.zip',
            'Kualitas Air' => 'Kualitas_Air.zip',
            'Kualitas Udara' => 'Kualitas_Udara.zip',
            'Lahan dan Hutan' => 'Lahan_Dan_Hutan.zip',
            'Laut, Pesisir, dan Pantai' => 'Laut,Pantai,Pesisir.zip',
            'Pengelolaan Sampah dan Limbah' => 'Pengelolaan_limbah_sampah.zip',
            'Perubahan Iklim' => 'perubahan_iklim.zip',
            'Risiko Bencana' => 'resiko_bencana.zip',
            'Dokumen Non Matra' => 'non-matra.zip',
        ];

        // Decode URL
        $matra = urldecode($matra);

        if (!isset($matraToZipMap[$matra])) {
            return response()->json([
                'message' => "Matra '{$matra}' tidak valid.",
                'valid_matra' => array_keys($matraToZipMap)
            ], 422);
        }

        $zipFileName = $matraToZipMap[$matra];
        $zipPath = "tabel_utama_zip/{$zipFileName}";

        if (!Storage::disk('templates')->exists($zipPath)) {
            return response()->json([
                'message' => "File ZIP untuk matra '{$matra}' belum tersedia.",
                'expected_path' => $zipPath
            ], 404);
        }

        @ob_clean();
        
        // Sanitize nama file untuk download
        $downloadName = str_replace([' ', ','], '_', $matra) . '_Templates.zip';
        
        return Storage::disk('templates')->download($zipPath, $downloadName);
    }

    /**
     * Preview dokumen DLH sendiri (termasuk draft) - untuk iframe
     */
    public function previewDocument(Request $request, string $documentType)
    {
        $submission = $request->submission;
        
        // Map document type
        $document = null;
        if ($documentType === 'ringkasan-eksekutif') {
            $document = $submission->ringkasanEksekutif;
        } elseif ($documentType === 'laporan-utama') {
            $document = $submission->laporanUtama;
        }

        if (!$document) {
            abort(404, 'Dokumen tidak ditemukan');
        }

        $filePath = $document->path;
        $disk = Storage::disk(self::DLH_DISK);
        
        if (!$disk->exists($filePath)) {
            abort(404, 'File tidak ditemukan di storage');
        }

        @ob_clean();
        
        // Return file dengan Content-Disposition: inline (preview, bukan download)
        return response()->file($disk->path($filePath), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
        ]);
    }

    /**
     * Download dokumen DLH sendiri (termasuk draft)
     */
    public function downloadDocument(Request $request, string $documentType)
    {
        $submission = $request->submission;
        
        // Map document type
        $document = null;
        if ($documentType === 'ringkasan-eksekutif') {
            $document = $submission->ringkasanEksekutif;
        } elseif ($documentType === 'laporan-utama') {
            $document = $submission->laporanUtama;
        }

        if (!$document) {
            return response()->json([
                'message' => 'Dokumen tidak ditemukan'
            ], 404);
        }

        $filePath = $document->path;
        $disk = Storage::disk(self::DLH_DISK);
        
        if (!$disk->exists($filePath)) {
            return response()->json([
                'message' => 'File tidak ditemukan di storage',
                'path' => $filePath
            ], 404);
        }

        @ob_clean();

        $fileName = basename($filePath);
        
        return $disk->download($filePath, $fileName, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Download Tabel Utama file (Excel)
     * Endpoint: GET /api/dinas/upload/tabel-utama/download/{kodeTabel}
     */
    public function downloadTabelUtama(Request $request, string $kodeTabel)
    {
        $submission = $request->submission;
        
        // Decode kode_tabel (karena bisa ada spasi dan karakter khusus)
        $kodeTabel = urldecode($kodeTabel);
        
        // Validasi kode tabel
        if (!MatraConstants::isValidKode($kodeTabel)) {
            return response()->json([
                'message' => "Kode tabel tidak valid."
            ], 422);
        }

        // Cari tabel yang sudah diupload
        $tabel = TabelUtama::where([
            'submission_id' => $submission->id,
            'kode_tabel' => $kodeTabel,
        ])->first();

        if (!$tabel) {
            return response()->json([
                'message' => "Tabel {$kodeTabel} belum diupload."
            ], 404);
        }

        $filePath = $tabel->path;
        $disk = Storage::disk(self::DLH_DISK);
        
        if (!$disk->exists($filePath)) {
            return response()->json([
                'message' => 'File tidak ditemukan di storage',
                'path' => $filePath
            ], 404);
        }

        @ob_clean();

        $fileName = basename($filePath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        // Determine content type based on extension
        $contentTypes = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv',
        ];
        $contentType = $contentTypes[$extension] ?? 'application/octet-stream';
        
        return $disk->download($filePath, $fileName, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Get Tabel Utama status by matra - with upload status from submission
     * Endpoint: GET /api/dinas/upload/tabel-utama/matra/{matra}
     */
    public function getTabelUtamaByMatraWithStatus(Request $request, string $matra)
    {
        // Validasi matra
        if (!in_array($matra, MatraConstants::MATRA_LIST)) {
            return response()->json([
                'message' => 'Matra tidak valid.'
            ], 422);
        }

        $submission = $request->submission;
        
        // Get all uploaded tabel for this submission
        $uploadedTabels = TabelUtama::where('submission_id', $submission->id)
            ->whereIn('kode_tabel', collect(MatraConstants::TABEL_TO_MATRA)
                ->filter(fn($m) => $m === $matra)
                ->keys()
                ->toArray())
            ->get()
            ->keyBy('kode_tabel');

        // Build response data
        $tabelList = collect(MatraConstants::TABEL_TO_MATRA)
            ->filter(fn($m) => $m === $matra)
            ->map(function($m, $kodeTabel) use ($uploadedTabels) {
                $uploaded = $uploadedTabels->get($kodeTabel);
                
                return [
                    'kode_tabel' => $kodeTabel,
                    'nomor_tabel' => MatraConstants::extractNomorTabel($kodeTabel),
                    'matra' => $m,
                    'has_template' => $this->checkTemplateExists($kodeTabel),
                    'uploaded' => $uploaded !== null,
                    'status' => $uploaded?->status,
                    'updated_at' => $uploaded?->updated_at?->format('d-m-Y H:i'),
                    'path' => $uploaded?->path,
                ];
            })
            ->sortBy('nomor_tabel')
            ->values();

        // Calculate summary
        $total = $tabelList->count();
        $uploadedCount = $tabelList->where('uploaded', true)->count();
        $finalizedCount = $tabelList->where('status', 'finalized')->count();

        return response()->json([
            'matra' => $matra,
            'summary' => [
                'total' => $total,
                'uploaded' => $uploadedCount,
                'finalized' => $finalizedCount,
            ],
            'data' => $tabelList
        ]);
    }

    /**
     * Finalize single Tabel Utama by kode_tabel
     * Endpoint: PATCH /api/dinas/upload/tabel-utama/finalize
     */
    public function finalizeTabelUtama(Request $request)
    {
        $request->validate([
            'kode_tabel' => 'required|string',
        ], [
            'kode_tabel.required' => 'Kode tabel wajib diisi.',
        ]);

        $kodeTabel = $request->input('kode_tabel');
        
        // Validasi kode tabel
        if (!MatraConstants::isValidKode($kodeTabel)) {
            return response()->json([
                'message' => "Kode tabel {$kodeTabel} tidak valid.",
            ], 422);
        }

        $submission = $request->submission;
        
        // Cari tabel yang sudah diupload
        $tabel = TabelUtama::where([
            'submission_id' => $submission->id,
            'kode_tabel' => $kodeTabel,
        ])->first();

        if (!$tabel) {
            return response()->json([
                'message' => "Tabel {$kodeTabel} belum diupload. Silakan upload terlebih dahulu.",
            ], 404);
        }

        // Cek status submission
        if (in_array($submission->status, ['finalized', 'approved'])) {
            return response()->json([
                'message' => 'Submission sudah difinalisasi, tidak dapat mengubah status tabel.',
            ], 403);
        }

        // Cek status tabel
        if ($tabel->status === 'finalized') {
            return response()->json([
                'message' => "Tabel {$kodeTabel} sudah difinalisasi.",
            ], 400);
        }

        if ($tabel->status === 'rejected') {
            return response()->json([
                'message' => "Tabel {$kodeTabel} ditolak, tidak dapat difinalisasi. Mohon perbaiki sesuai catatan admin.",
            ], 400);
        }

        // Finalize tabel
        try {
            $this->DocumentFinalizer->finalize($tabel, "tabelUtama:{$kodeTabel}");
            
            return response()->json([
                'message' => "Tabel {$kodeTabel} berhasil difinalisasi.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => "Gagal memfinalisasi tabel {$kodeTabel}.",
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get overall Tabel Utama status per matra (for main page)
     * Endpoint: GET /api/dinas/upload/tabel-utama/status
     */
    public function getTabelUtamaStatus(Request $request)
    {
        $submission = $request->submission;
        
        // Get all uploaded tabel for this submission
        $uploadedTabels = TabelUtama::where('submission_id', $submission->id)->get();

        // Build status per matra
        $statusPerMatra = collect(MatraConstants::MATRA_LIST)->map(function($matra) use ($uploadedTabels) {
            // Get all kode_tabel for this matra
            $kodeTabelsMatra = collect(MatraConstants::TABEL_TO_MATRA)
                ->filter(fn($m) => $m === $matra)
                ->keys()
                ->toArray();

            $total = count($kodeTabelsMatra);
            $uploaded = $uploadedTabels->whereIn('kode_tabel', $kodeTabelsMatra)->count();
            $finalized = $uploadedTabels->whereIn('kode_tabel', $kodeTabelsMatra)->where('status', 'finalized')->count();

            return [
                'matra' => $matra,
                'total' => $total,
                'uploaded' => $uploaded,
                'finalized' => $finalized,
            ];
        });

        // Calculate overall totals
        $totalAll = $statusPerMatra->sum('total');
        $uploadedAll = $statusPerMatra->sum('uploaded');
        $finalizedAll = $statusPerMatra->sum('finalized');

        return response()->json([
            'summary' => [
                'total' => $totalAll,
                'uploaded' => $uploadedAll,
                'finalized' => $finalizedAll,
            ],
            'data' => $statusPerMatra
        ]);
    }
}
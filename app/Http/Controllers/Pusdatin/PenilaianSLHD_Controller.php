<?php

namespace App\Http\Controllers\Pusdatin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTemplatePenilaianPenghargaan;
use App\Models\Pusdatin\PenilaianSLHD;
use App\Services\LogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Phiki\Grammar\Injections\Path;
use App\Jobs\ParsePenilaianSLHDJob;
use App\Models\Pusdatin\Parsed\PenilaianSLHD_Parsed;
use App\Services\ExcelService;
use App\services\SLHDService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Illuminate\Support\Facades\Log;
use App\Models\Submission;
use App\Models\Dinas;
use App\Models\Region;

class PenilaianSLHD_Controller extends Controller
{
    private const SLHD_TEMPLATE_PATH = 'penilaian/template_penilaian_slhd.xlsx';
    private const STORAGE_DISK = 'pusdatin';
    protected $logService;
    protected $excelService;
    protected $slhdService;
    protected $tahapanService;

    public function deleteExistingPath($filePath){
        $disk = Storage::disk(self::STORAGE_DISK);
        if(!$filePath) return;
        if($disk->exists($filePath)){
            $disk->delete($filePath);
        }
    }
     
    public function __construct(LogService $logService, ExcelService $excelService, SLHDService $slhdService, \App\Services\TahapanPenilaianService $tahapanService)
    {
        $this->logService = $logService;
        $this->excelService = $excelService;
        $this->slhdService = $slhdService;
        $this->tahapanService = $tahapanService;

        
    }
    public function tes(Request $request){
        return response()->json(['message' => 'Test successful','id'=>$request->user()->id]);
    }

    /**
     * Get submissions dengan status kelayakan dokumen (finalized/approved status)
     * Untuk tabel "Kelayakan Administrasi Dokumen"
     */
    public function getSubmissionsStatus(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $tipe = $request->input('tipe'); // 'provinsi' atau 'kabupaten/kota'
        $provinsi = $request->input('provinsi'); // Filter by nama provinsi
        
        // Get all dinas with their submissions
        $query = Dinas::with(['region.parent', 'submissions' => function($q) use ($year) {
            $q->where('tahun', $year)
              ->with(['laporanUtama', 'tabelUtama', 'ringkasanEksekutif']);
        }]);
        
        // Filter by tipe
        if ($tipe && $tipe !== 'all') {
            $query->whereHas('region', function($q) use ($tipe) {
                $q->where('type', $tipe);
            });
        }
        
        // Filter by provinsi (untuk kab/kota)
        if ($provinsi && $provinsi !== 'all' && $provinsi !== '') {
            $query->whereHas('region', function($q) use ($provinsi) {
                $q->where('nama_region', $provinsi)
                  ->orWhereHas('parent', function($q2) use ($provinsi) {
                      $q2->where('nama_region', $provinsi);
                  });
            });
        }
        
        $dinasData = $query->get();
        
        $result = $dinasData->map(function($dinas) {
            $submission = $dinas->submissions->first();
            $region = $dinas->region;
            
            // Get provinsi name
            $provinsiName = '';
            if ($region) {
                if ($region->type === 'provinsi') {
                    $provinsiName = $region->nama_region;
                } else {
                    $provinsiName = $region->parent?->nama_region ?? '';
                }
            }
            
            // Check finalized status for each document
            // Buku I = Laporan Utama, Buku II = Ringkasan Eksekutif, Tabel = Tabel Utama
            $buku1Finalized = false;
            $buku2Finalized = false;
            $tabelFinalized = false;
            
            $buku1Status = null;
            $buku2Status = null;
            $tabelStatus = null;
            
            if ($submission) {
                $buku1Finalized = $submission->laporanUtama?->status === 'finalized' || $submission->laporanUtama?->status === 'approved';
                $buku2Finalized = $submission->ringkasanEksekutif?->status === 'finalized' || $submission->ringkasanEksekutif?->status === 'approved';
                $tabelFinalized = $submission->tabelUtama->count() > 0 && $submission->tabelUtama->every(fn($t) => $t->status === 'finalized' || $t->status === 'approved');
                
                $buku1Status = $submission->laporanUtama?->status;
                $buku2Status = $submission->ringkasanEksekutif?->status;
                // Untuk tabel, ambil status yang paling umum atau yang pertama
                $tabelStatus = $submission->tabelUtama->count() > 0 
                    ? ($submission->tabelUtama->every(fn($t) => $t->status === 'approved') ? 'approved' 
                        : ($submission->tabelUtama->every(fn($t) => $t->status === 'finalized') ? 'finalized' : 'draft'))
                    : null;
            }
            
            return [
                'id_dinas' => $dinas->id,
                'nama_dinas' => $dinas->nama_dinas,
                'tipe' => $region?->type ?? 'unknown',
                'provinsi' => $provinsiName,
                'submission_id' => $submission?->id,
                'buku1_finalized' => $buku1Finalized,
                'buku2_finalized' => $buku2Finalized,
                'tabel_finalized' => $tabelFinalized,
                'all_finalized' => $buku1Finalized && $buku2Finalized && $tabelFinalized,
                'buku1_status' => $buku1Status,
                'buku2_status' => $buku2Status,
                'tabel_status' => $tabelStatus,
            ];
        });
        
        return response()->json([
            'data' => $result,
            'total' => $result->count(),
            'year' => $year
        ]);
    }

    public function downloadTemplate()
    {
        $disk = Storage::disk('templates');

        if (! $disk->exists(self::SLHD_TEMPLATE_PATH)) {
            return response()->json([
                'message' => 'Template penilaian SLHD belum tersedia.',
                'path' => self::SLHD_TEMPLATE_PATH
            ], 404);
        }

        $templatePath = $disk->path(self::SLHD_TEMPLATE_PATH);

        @ob_clean();

        return Storage::disk('templates')->download(self::SLHD_TEMPLATE_PATH, 'template_penilaian_slhd.xlsx',[
             'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="laporan_penilaian.pdf"',
        ]);

    }
    public function uploadPenilaianSLHD(Request $request,$year){

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // Maksimal 10MB
        ],[
            'file.required' => 'File penilaian SLHD harus diunggah.',
            'file.file' => 'Yang diunggah harus berupa file.',
            'file.mimes' => 'Format file harus berupa xlsx atau xls.',
            'file.max' => 'Ukuran file maksimal 10MB.',
        ]);
        
        $year =$year?? now()->year();
        $file = $request->file('file');
        $path = 'penilaian/slhd/'.$year;

        $fileName = 'penilaian_slhd_'.$year.'_'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension();
        
        
        $existing= PenilaianSLHD::where('year',$year)->first();
        
        
        $filePath = $file->storeAs($path, $fileName, self::STORAGE_DISK);
        $penilaian = PenilaianSLHD::Create(
            [
                'year' => $year,
                'uploaded_by' => $request->user()->id,
                'status' => 'uploaded',
                'file_path' => $filePath,
                'uploaded_at' => now(),
                'is_finalized' => false,
                'catatan' => $request->catatan??null,
            ]
            );
        $this->logService->log([
            'year' => $year,
            'actor_id' => $request->user()->id,
            'stage' => 'penilaian_slhd',
            'activity_type' => 'upload',
            'document_type' => null,
            'catatan' => $request->catatan ?? null,
            'status' =>'success'
        ]);

        dispatch(new ParsePenilaianSLHDJob($penilaian))->onQueue('penilaian_slhd_parsing');
        return response()->json([
            'message' =>  'Penilaian SLHD berhasil diunggah.',
            'data' => $penilaian
        ], 200);
}
    public function getPenilaianSLHD($year){
        $penilaian = PenilaianSLHD::with('uploadedBy:id,email')
            ->where('year',$year)
            ->orderByDesc('created_at')
            ->get();
        if(!$penilaian){
            return response()->json([
                'message' => 'Penilaian SLHD untuk tahun '.$year.' tidak ditemukan.'
            ],404);
        }
        return response()->json([
            'data' => $penilaian
        ],200);
    }

    public function getAllParsedPenilaianSLHD(Request $request,PenilaianSLHD $penilaianSLHD){
        $parsedData = $penilaianSLHD->penilaianSLHDParsed;
        
        // Hitung statistik error
        $totalParsed = $parsedData->count();
        $totalError = $parsedData->where('status', 'error')->count();
        $totalSuccess = $parsedData->where('status', 'success')->count();

        return response()->json([
            "total_parsed" => $totalParsed,
            "total_success" => $totalSuccess,
            "total_error" => $totalError,
            "data" => $parsedData
        ]);
    }
    public function status(PenilaianSLHD $penilaianSLHD){
        return response()->json(["status"=>$penilaianSLHD->status],200);

    }

    public function finalizePenilaianSLHD(Request $request,PenilaianSLHD $penilaianSLHD){
        $penilaianSLHD->update(
            [
                'is_finalized' => true,
                'finalized_at' => now(),
                'status' => 'finalized',
                'catatan' => $request->catatan ?? null,
            ]
            );
        $this->logService->log([
            'year' => $penilaianSLHD->year,
            'actor_id' => $request->user()->id,
            'stage' => 'penilaian_slhd',
            'activity_type' => 'finalize',
            'document_type' => null,
            'catatan' => $request->catatan ?? null,
            'status' =>'success'
        ]);
        
        // Update rekap penilaian
        app(\App\Services\RekapPenilaianService::class)->updateFromSLHD($penilaianSLHD);
        
        // Update tahapan penilaian status
        $this->tahapanService->updateSetelahFinalize('penilaian_slhd', $penilaianSLHD->year);
        
        dispatch(new GenerateTemplatePenilaianPenghargaan($penilaianSLHD))->onQueue('generate_templates_penghargaan');
        return response()->json([
            'message' => 'Penilaian SLHD untuk tahun '.$penilaianSLHD->year.' berhasil difinalisasi.',
            'data' => $penilaianSLHD
        ],200);
        
    }

    public function  unfinalized(
    Request $request,$year
    ){
        $penilaianSLHD= PenilaianSLHD::where(['year'=>$year,'status'=>'finalized'])->first();
        if(!$penilaianSLHD){
            return response()->json([
                'message' => 'Penilaian SLHD untuk tahun '.$year.' tidak ditemukan.'
            ],404);
        }
        $penilaianSLHD->update(
            [
                'is_finalized' => false,
                'finalized_at' => null,
                'status' => 'parsed_ok',
                'catatan' => $request->catatan ?? null,
            ]
            );
        $this->logService->log([
            'year' => $penilaianSLHD->year,
            'actor_id' => $request->user()->id,
            'stage' => 'penilaian_slhd',
            'activity_type' => 'reopen',
            'document_type' => null,
            'catatan' => $request->catatan ?? null,
            'status' =>'success'
        ]);
        
        // Update tahapan penilaian status (kembali ke tahap sebelumnya)
        $this->tahapanService->updateSetelahUnfinalize('penilaian_slhd', $penilaianSLHD->year);
        
        return response()->json([
            'message' => 'Penilaian SLHD untuk tahun '.$penilaianSLHD->year.' berhasil dibuka kembali.',
            'data' => $penilaianSLHD
        ],200);
    }
}

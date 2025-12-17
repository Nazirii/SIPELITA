<?php

namespace App\Http\Controllers\Pusdatin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\LogService;
use App\Models\Pusdatin\PenilaianPenghargaan;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ParsePenilaianPenghargaanJob;
use App\Models\Pusdatin\PenilaianSLHD;
use App\Jobs\GenerateTemplatePenilaianPenghargaan;
use App\Services\ValidasiService;

class PenilaianPenghargaan_Controller extends Controller
{
    protected $logService;
    protected $validasiService;
    private const STORAGE_DISK = 'pusdatin';


    protected $tahapanService;

    public function __construct(LogService $logService, ValidasiService $validasiService, \App\Services\TahapanPenilaianService $tahapanService)
    {
        $this->logService = $logService;        
        $this->validasiService = $validasiService;
        $this->tahapanService = $tahapanService;
}
    public function downloadTemplate(Request $request, $year){
        $penilaianSLHD= PenilaianSLHD::where(['year'=>$year,'status'=>'finalized'])->first();
        if(!$penilaianSLHD){
            return response()->json([
                'message' => 'Penilaian SLHD untuk tahun '.$year.' belum difinalisasi. Silakan finalisasi terlebih dahulu sebelum mengunduh template penghargaan.',
            ], 400);
        }

        $templatePath = "penilaian/template_penilaian_penghargaan_{$year}.xlsx";
        $disk = Storage::disk('templates');
        
        if (!$disk->exists($templatePath)) {
            dispatch(new GenerateTemplatePenilaianPenghargaan($penilaianSLHD))->onQueue('generate_templates_penghargaan');
            return response()->json([
                'message' => 'Template penilaian Penghargaan belum tersedia.Sedang diproses',
                'path' => $templatePath
            ], 404);

        }

        
        //  ob_clean();
        @ob_clean(); 

    // flush();

    // Gunakan response()->download() dengan explicit headers
    $filePath = $disk->path($templatePath);
    
   return Storage::disk('templates')->download($templatePath, "template_penilaian_penghargaan_{$year}.xlsx", [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition' => 'attachment; filename="template_penilaian_penghargaan_' . $year . '.xlsx"',
    ]);



    }

    public function uploadPenilaianPenghargaan(Request $request,$year){
         $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // Maksimal 10MB
        ],[
            'file.required' => 'File penilaian Penghargaan harus diunggah.',
            'file.file' => 'Yang diunggah harus berupa file.',
            'file.mimes' => 'Format file harus berupa xlsx atau xls.',
            'file.max' => 'Ukuran file maksimal 10MB.',
        ]);
        
        $year =$year?? now()->year();
        $file = $request->file('file');
        $path = 'penilaian/penghargaan/'.$year;
        $batch=PenilaianSLHD::where(['status'=>'finalized','year'=>$year])->first();

        // Safety check: SLHD harus sudah finalized
        if (!$batch) {
            return response()->json([
                'message' => 'Penilaian SLHD untuk tahun '.$year.' belum difinalisasi.'
            ], 400);
        }

        $fileName = 'penilaian_penghargaan_'.$year.'_'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension();
        
                
        
        $filePath = $file->storeAs($path, $fileName, self::STORAGE_DISK);
        $penilaian = PenilaianPenghargaan::Create(

            [
                'year' => $year,
                'uploaded_by' => $request->user()->id,
                'penilaian_slhd_ref_id' => $batch->id,
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
            'stage' => 'penilaian_penghargaan',
            'activity_type' => 'upload',
            'document_type' => null,
            'catatan' => $request->catatan ?? null,
            'status' =>'success'
        ]);

        dispatch(new ParsePenilaianPenghargaanJob($penilaian))->onQueue('penilaian_penghargaan_parsing');

        return response()->json([
            'message' =>  'Penilaian Penghargaan berhasil diunggah.',
            'data' => $penilaian
        ], 200);
    }
    
    public function getPenilaianPenghargaan(Request $request,$year){
        $penilaian = PenilaianPenghargaan::with('uploadedBy:id,email')
            ->where('year',$year)
            ->orderByDesc('created_at')
            ->get();
        if(!$penilaian){
            return response()->json([
                'message' => 'Penilaian Penghargaan untuk tahun '.$year.' tidak ditemukan.'
            ],404);
        }
        return response()->json([
            'message' => 'Penilaian Penghargaan untuk tahun '.$year.' berhasil ditemukan.',
            'data' => $penilaian
        ],200);
    }
    
    public function status(Request $request,PenilaianPenghargaan $penilaianPenghargaan){
        return response()->json([
            "status"=>$penilaianPenghargaan->status
        ],200);
    }
    public function getAllPenilaianPenghargaanParsed(Request $request,PenilaianPenghargaan $penilaianPenghargaan){
        $parsedData = $penilaianPenghargaan->PenilaianPenghargaanParsed;
        
        // Hitung statistik error
        $totalParsed = $parsedData->count();
        $totalError = $parsedData->where('status', 'error')->count();
        $totalSuccess = $parsedData->where('status', 'success')->count();
        
        return response()->json([
            'message' => 'Data Penilaian Penghargaan beserta hasil parsing berhasil ditemukan.',
            'total_parsed' => $totalParsed,
            'total_success' => $totalSuccess,
            'total_error' => $totalError,
            'data' => $parsedData
        ],200);
    }
    
    public function finalizePenilaianPenghargaan(Request $request,PenilaianPenghargaan $penilaianPenghargaan){
        // Safety check: Penilaian sudah parsed
        if ($penilaianPenghargaan->status !== 'parsed_ok') {
            return response()->json([
                'message' => 'Penilaian Penghargaan belum selesai di-parsing atau terjadi error saat parsing.'
            ], 400);
        }

        $penilaianPenghargaan->update([
            'is_finalized' => true,
            'finalized_at' => now(),
            'status' => 'finalized',
            'catatan' => $request->catatan ?? null,
        ]);
        
        $this->logService->log([
            'year' => $penilaianPenghargaan->year,
            'actor_id' => $request->user()->id,
            'stage' => 'penilaian_penghargaan',
            'activity_type' => 'finalize',
            'document_type' => null,
            'catatan' => $request->catatan ?? null,
            'status' =>'success'
        ]);

        // Update rekap penilaian
        app(\App\Services\RekapPenilaianService::class)->updateFromPenghargaan($penilaianPenghargaan);

        // Update tahapan penilaian status
        $this->tahapanService->updateSetelahFinalize('penilaian_penghargaan', $penilaianPenghargaan->year);

        $this->validasiService->CreateValidasi1($penilaianPenghargaan);

        return response()->json([
            'message' => 'Penilaian Penghargaan untuk tahun '.$penilaianPenghargaan->year.' berhasil difinalisasi.',
            'data' => $penilaianPenghargaan
        ],200);
        
    }
    public function unfinalized(Request $request,$year){
        $penilaianPenghargaan=PenilaianPenghargaan::where('year',$year)->first();
        if(!$penilaianPenghargaan){
            return response()->json([
                'message' => 'Penilaian Penghargaan untuk tahun '.$year.' tidak ditemukan.'
            ],404);
        }
        $penilaianPenghargaan->update([
            'is_finalized' => false,
            'finalized_at' => null,
            'status' => 'parsed_ok',
            'catatan' => null,
        ]);
        
        $this->logService->log([
            'year' => $penilaianPenghargaan->year,
            'actor_id' => $request->user()->id,
            'stage' => 'penilaian_penghargaan',
            'activity_type' => 'reopen',
            'document_type' => null,
            'catatan' => null,
            'status' =>'success'
        ]);

        // Update tahapan penilaian status (kembali ke tahap sebelumnya)
        $this->tahapanService->updateSetelahUnfinalize('penilaian_penghargaan', $penilaianPenghargaan->year);

        return response()->json([
            'message' => 'Penilaian Penghargaan untuk tahun '.$year.' berhasil dibuka finalisasinya.',
            'data' => $penilaianPenghargaan
        ],200);

    }
}

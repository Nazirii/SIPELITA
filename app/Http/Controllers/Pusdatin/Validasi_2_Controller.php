<?php

namespace App\Http\Controllers\Pusdatin;

use App\Http\Controllers\Controller;
use App\Models\Pusdatin\Parsed\Validasi2Parsed;
use App\Models\Pusdatin\Validasi2;
use App\Services\ValidasiService;
use Illuminate\Http\Request;


class Validasi_2_Controller extends Controller
{
    protected $validasiService;
    protected $tahapanService;

    public function __construct(ValidasiService $validasiService, \App\Services\TahapanPenilaianService $tahapanService)
    {
        $this->validasiService = $validasiService;
        $this->tahapanService = $tahapanService;
    }
    
    public function index(Request $request, $year)
    {
        
        $validasi2=Validasi2::where(['year'=>$year])->first();
        if(!$validasi2){
            return response()->json([
                'message'=>'Belum ada Validasi 2 yang sudah difinalisasi untuk tahun '.$year
            ],404);
        }
        $data=$validasi2->Validasi2Parsed()->get();
        return response()->json($data,200);

    }
    public function updateCheklist(Request $request,Validasi2Parsed $validasi2Parsed){
        $validatedData=$request->validate([
            'Kriteria_WTP'=>'required|boolean',
            'Kriteria_Kasus_Hukum'=>'required|boolean',
            'catatan'=>'nullable|string'
        ]);
        $validasi2Parsed->update($validatedData);
        return response()->json([
            'message'=>'Checklist Validasi 2 berhasil diperbarui',
            'data'=>$validasi2Parsed
        ],200);
    }
    
    public function finalize(Request $request,$year){
        $validasi2=Validasi2::where('year',$year)->first();
        if(!$validasi2){
            return response()->json([
                'message'=>'Validasi 2 untuk tahun '.$year.' tidak ditemukan'
            ],404); 
        }

        // Safety check: Validasi2 sudah finalized
        if ($validasi2->is_finalized) {
            return response()->json([
                'message'=>'Validasi 2 untuk tahun '.$year.' sudah difinalisasi'
            ],400);
        }

        $validasi2->update([
            'status'=>'finalized',
            'is_finalized'=>true,
            'finalized_at'=>now(),
            'finalized_by'=>$request->user()->id
        ]);
        $this->validasiService->createLulusValidasi2($validasi2);
        
        // Update rekap penilaian
        app(\App\Services\RekapPenilaianService::class)->updateFromValidasi2($validasi2);
        
        // Update tahapan penilaian status (FINAL)
        $this->tahapanService->updateSetelahFinalize('validasi_2', $validasi2->year);
        
        return response()->json([
            'message'=>'Validasi 2 untuk tahun '.$year.' berhasil difinalisasi. Silakan gunakan endpoint ranked untuk menentukan jumlah peserta wawancara.',
            'data'=>$validasi2
        ],200); 


    }
    public function ranked(Request $request, $year){
        $validasi2=Validasi2::where(['year'=>$year,'is_finalized'=>true])->first();
        if(!$validasi2){
            return response()->json([
                'message'=>'Belum ada Validasi 2 yang sudah difinalisasi untuk tahun '.$year
            ],404);
        }

        // Parameter: jenis kategori (default: provinsi) & top N (default: 5)
        $kategori = $request->input('kategori', 'provinsi');
        $topN = $request->input('top', 5);
        
        // Validasi kategori
        $validKategori = ['provinsi', 'kabupaten_besar', 'kabupaten_sedang', 'kabupaten_kecil', 'kota_besar', 'kota_sedang', 'kota_kecil'];
        if (!in_array($kategori, $validKategori)) {
            return response()->json([
                'message' => 'Kategori tidak valid. Pilih salah satu: ' . implode(', ', $validKategori)
            ], 400);
        }
        
        // Validasi topN
        if ($topN < 1 || $topN > 50) {
            return response()->json([
                'message' => 'Parameter top harus antara 1-50'
            ], 400);
        }

        // Query langsung dengan join ke regions untuk filter kategori
        $dinasLolos = $validasi2->Validasi2Parsed()
            ->where('status_validasi', 'lolos')
            ->with('dinas.region.parent')
            ->whereHas('dinas.region', function($query) use ($kategori) {
                if ($kategori === 'provinsi') {
                    $query->where('type', 'provinsi');
                } else {
                    $query->where('kategori', $kategori);
                }
            })
            ->orderByDesc('Total_Skor')
            ->limit($topN)
            ->get()
            ->map(function($item, $index) use ($kategori) {
                return [
                    'peringkat' => $index + 1,
                    'id_dinas' => $item->id_dinas,
                    'nama_dinas' => $item->nama_dinas,
                    'kategori' => $kategori,
                    'provinsi' => $item->dinas?->region?->parent?->name ?? $item->dinas?->region?->name,
                    'Nilai_Penghargaan' => $item->Nilai_Penghargaan,
                    'Nilai_IKLH' => $item->Nilai_IKLH,
                    'Total_Skor' => $item->Total_Skor,
                    'Kriteria_WTP' => $item->Kriteria_WTP,
                    'Kriteria_Kasus_Hukum' => $item->Kriteria_Kasus_Hukum,
                ];
            });

        return response()->json([
            'year' => $year,
            'kategori' => $kategori,
            'top' => $topN,
            'total_shown' => $dinasLolos->count(),
            'data' => $dinasLolos
        ], 200);

    }
    
    /**
     * Create Wawancara records untuk top N dinas per kategori
     */
    public function createWawancara(Request $request, $year)
    {
        $validasi2 = Validasi2::where(['year' => $year, 'is_finalized' => true])->first();
        
        if (!$validasi2) {
            return response()->json([
                'message' => 'Validasi 2 untuk tahun ' . $year . ' belum difinalisasi'
            ], 404);
        }
        
        // Validasi input topN (default 5)
        $topN = $request->input('top', 5);
        if ($topN < 1 || $topN > 20) {
            return response()->json([
                'message' => 'Jumlah peserta wawancara harus antara 1-20 per kategori'
            ], 400);
        }
        
        // Cek apakah wawancara sudah dibuat
        $existingWawancara = \App\Models\Pusdatin\Wawancara::where('year', $year)->exists();
        if ($existingWawancara) {
            return response()->json([
                'message' => 'Wawancara untuk tahun ' . $year . ' sudah dibuat sebelumnya'
            ], 400);
        }
        
        $totalCreated = $this->validasiService->createWawancara($validasi2, $topN);
        
        return response()->json([
            'message' => 'Wawancara berhasil dibuat untuk top ' . $topN . ' dinas per kategori',
            'year' => $year,
            'total_created' => $totalCreated,
            'top_per_kategori' => $topN
        ], 201);
    }
    
    public function unfinalize(Request $request, $year)
    {
        $validasi2 = Validasi2::where('year', $year)->first();
        
        if (!$validasi2) {
            return response()->json([
                'message' => 'Validasi 2 untuk tahun ' . $year . ' tidak ditemukan'
            ], 404);
        }
        
        if (!$validasi2->is_finalized) {
            return response()->json([
                'message' => 'Validasi 2 untuk tahun ' . $year . ' belum difinalisasi'
            ], 400);
        }
        
        $validasi2->update([
            'status' => 'parsed_ok',
            'is_finalized' => false,
            'finalized_at' => null,
            'finalized_by' => null,
            'catatan' => $request->catatan ?? null
        ]);
        
        // Update tahapan penilaian status (kembali ke tahap sebelumnya)
        app(\App\Services\TahapanPenilaianService::class)->updateSetelahUnfinalize('validasi_2', $validasi2->year);
        
        return response()->json([
            'message' => 'Validasi 2 untuk tahun ' . $year . ' berhasil dibuka finalisasinya.'
        ], 200);
    }
}

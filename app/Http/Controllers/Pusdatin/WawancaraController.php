<?php

namespace App\Http\Controllers\Pusdatin;

use App\Http\Controllers\Controller;
use App\Models\Pusdatin\Wawancara;
use App\Models\Pusdatin\Validasi2;
use App\Models\Dinas;
use Illuminate\Http\Request;

class WawancaraController extends Controller
{
    protected $tahapanService;
    
    public function __construct(\App\Services\TahapanPenilaianService $tahapanService)
    {
        $this->tahapanService = $tahapanService;
    }
    
    /**
     * Get daftar wawancara untuk tahun tertentu dengan filter kategori optional
     */
    public function index(Request $request, $year)
    {
        $query = Wawancara::where('year', $year)
            ->with('dinas.region.parent');
        
        // Filter by kategori jika ada parameter
        $kategori = $request->input('kategori');
        if ($kategori) {
            $validKategori = ['provinsi', 'kabupaten_besar', 'kabupaten_sedang', 'kabupaten_kecil', 'kota_besar', 'kota_sedang', 'kota_kecil'];
            if (!in_array($kategori, $validKategori)) {
                return response()->json([
                    'message' => 'Kategori tidak valid. Pilih salah satu: ' . implode(', ', $validKategori)
                ], 400);
            }
            
            $query->whereHas('dinas.region', function($q) use ($kategori) {
                if ($kategori === 'provinsi') {
                    $q->where('type', 'provinsi');
                } else {
                    $q->where('kategori', $kategori);
                }
            });
        }
        
        $wawancara = $query->get();
        
        if ($wawancara->isEmpty()) {
            return response()->json([
                'message' => 'Belum ada data wawancara untuk tahun '.$year . ($kategori ? ' dengan kategori '.$kategori : '')
            ], 404);
        }
        
        // Transform response dengan info kategori
        $data = $wawancara->map(function($item) {
            $region = $item->dinas->region;
            $itemKategori = $region->type === 'provinsi' ? 'provinsi' : ($region->kategori ?? 'kabupaten_sedang');
            
            return [
                'id' => $item->id,
                'year' => $item->year,
                'id_dinas' => $item->id_dinas,
                'nama_dinas' => $item->dinas->nama_dinas,
                'kategori' => $itemKategori,
                'provinsi' => $region->parent?->name ?? $region->name,
                'nilai_wawancara' => $item->nilai_wawancara,
                'catatan' => $item->catatan,
                'status' => $item->status,
                'is_finalized' => $item->is_finalized,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });
        
        return response()->json([
            'year' => $year,
            'kategori' => $kategori ?? 'all',
            'total' => $data->count(),
            'data' => $data
        ], 200);
    }
    
    /**
     * Update nilai wawancara untuk dinas tertentu
     */
    public function updateNilai(Request $request, Wawancara $wawancara)
    {
        // Cek apakah sudah finalized
        if ($wawancara->is_finalized) {
            return response()->json([
                'message' => 'Nilai wawancara sudah difinalisasi dan tidak dapat diubah'
            ], 400);
        }
        
        $validatedData = $request->validate([
            'nilai_wawancara' => 'required|numeric|min:0|max:100',
            'catatan' => 'nullable|string'
        ]);
        
        $wawancara->update($validatedData);
        
        return response()->json([
            'message' => 'Nilai wawancara berhasil diperbarui',
            'data' => $wawancara
        ], 200);
    }
    
    /**
     * Finalize wawancara (hitung total skor final)
     */
    public function finalize(Request $request, $year)
    {
        $wawancara = Wawancara::where('year', $year)->get();
        
        if ($wawancara->isEmpty()) {
            return response()->json([
                'message' => 'Belum ada data wawancara untuk tahun '.$year
            ], 404);
        }
        
        // Safety check: Apakah sudah ada yang finalized
        $alreadyFinalized = $wawancara->where('is_finalized', true)->first();
        if ($alreadyFinalized) {
            return response()->json([
                'message' => 'Wawancara untuk tahun '.$year.' sudah difinalisasi'
            ], 400);
        }
        
        // Validasi: Semua nilai wawancara harus sudah terisi
        $belumDiisi = $wawancara->whereNull('nilai_wawancara');
        if ($belumDiisi->isNotEmpty()) {
            $namaList = $belumDiisi->pluck('dinas.nama_dinas')->take(5)->join(', ');
            return response()->json([
                'message' => 'Tidak dapat finalisasi. Masih ada '.$belumDiisi->count().' dinas yang belum diisi nilai wawancara.',
                'contoh_belum_diisi' => $namaList
            ], 400);
        }
        
        // Update semua wawancara menjadi finalized
        Wawancara::where('year', $year)->update([
            'status' => 'finalized',
            'is_finalized' => true,
            'finalized_at' => now(),
            'finalized_by' => $request->user()->id
        ]);
        
        // Update rekap penilaian (hitung total skor final)
        try {
            app(\App\Services\RekapPenilaianService::class)->updateFromWawancara($year);
        } catch (\Exception $e) {
            // Rollback finalization if rekap update fails
            Wawancara::where('year', $year)->update([
                'status' => 'draft',
                'is_finalized' => false,
                'finalized_at' => null,
                'finalized_by' => null
            ]);
            
            return response()->json([
                'message' => 'Gagal memfinalisasi wawancara: ' . $e->getMessage()
            ], 500);
        }
        
        // Update tahapan penilaian status (FINAL - SELESAI)
        $this->tahapanService->updateSetelahFinalize('wawancara', $year);
        
        return response()->json([
            'message' => 'Wawancara untuk tahun '.$year.' berhasil difinalisasi',
            'total_finalized' => $wawancara->count()
        ], 200);
    }
    
    /**
     * Unfinalize wawancara (batalkan finalisasi)
     */
    public function unfinalize(Request $request, $year)
    {
        $wawancara = Wawancara::where(['year' => $year, 'is_finalized' => true])->get();
        
        if ($wawancara->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada wawancara yang difinalisasi untuk tahun '.$year
            ], 404);
        }
        
        // Update semua wawancara menjadi draft
        Wawancara::where('year', $year)->update([
            'status' => 'draft',
            'is_finalized' => false,
            'finalized_at' => null,
            'finalized_by' => null
        ]);
        
        // Reset total_skor_final dan peringkat_final di rekap
        app(\App\Services\RekapPenilaianService::class)->resetFinalScores($year);
        
        // Revert tahapan penilaian status
        $this->tahapanService->updateSetelahUnfinalize('wawancara', $year);
        
        return response()->json([
            'message' => 'Wawancara untuk tahun '.$year.' berhasil di-unfinalize',
            'total_updated' => $wawancara->count()
        ], 200);
    }
}

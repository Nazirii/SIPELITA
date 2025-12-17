<?php

namespace App\Services;

use App\Models\Pusdatin\RekapPenilaian;
use App\Models\Pusdatin\PenilaianSLHD;
use App\Models\Pusdatin\PenilaianPenghargaan;
use App\Models\Pusdatin\Validasi1;
use App\Models\Pusdatin\Validasi2;

class RekapPenilaianService
{
    /**
     * Update rekap saat SLHD finalized
     */
    public function updateFromSLHD(PenilaianSLHD $slhd)
    {
        $parsed = $slhd->penilaianSLHDParsed()
            ->where('status', 'parsed_ok')
            ->get();
        
        foreach ($parsed as $row) {
            RekapPenilaian::updateOrCreate(
                [
                    'year' => $slhd->year,
                    'id_dinas' => $row->id_dinas,
                ],
                [
                    'nama_dinas' => $row->nama_dinas,
                    'nilai_slhd' => $row->Total_Skor,
                    'lolos_slhd' => $row->Total_Skor >= 60, // Sesuaikan threshold
                    'status_akhir' => $row->Total_Skor >= 60 ? 'tidak_masuk_penghargaan' : 'tidak_lolos_slhd',
                ]
            );
        }
    }
    
    /**
     * Update rekap saat Penghargaan finalized
     */
    public function updateFromPenghargaan(PenilaianPenghargaan $penghargaan)
    {
        $parsed = $penghargaan->PenilaianPenghargaanParsed()
            ->where('status', 'parsed_ok')
            ->get();
        
        foreach ($parsed as $row) {
            RekapPenilaian::updateOrCreate(
                [
                    'year' => $penghargaan->year,
                    'id_dinas' => $row->id_dinas,
                ],
                [
                    'nama_dinas' => $row->nama_dinas,
                    'nilai_penghargaan' => $row->Total_Skor,
                    'masuk_penghargaan' => true,
                    'status_akhir' => 'tidak_lolos_validasi1',
                ]
            );
        }
    }
    
    /**
     * Update rekap saat Validasi1 finalized
     */
    public function updateFromValidasi1(Validasi1 $validasi1)
    {
        $parsed = $validasi1->Validasi1Parsed()->get();
        
        foreach ($parsed as $row) {
            $lolos = $row->status_result === 'lulus';
            
            RekapPenilaian::updateOrCreate(
                [
                    'year' => $validasi1->year,
                    'id_dinas' => $row->id_dinas,
                ],
                [
                    'nama_dinas' => $row->nama_dinas,
                    'nilai_iklh' => $row->Nilai_IKLH,
                    'total_skor_validasi1' => $row->Total_Skor,
                    'lolos_validasi1' => $lolos,
                    'status_akhir' => $lolos ? 'tidak_lolos_validasi2' : 'tidak_lolos_validasi1',
                ]
            );
        }
    }
    
    /**
     * Update rekap saat Validasi2 finalized
     */
    public function updateFromValidasi2(Validasi2 $validasi2)
    {
        // Update yang lolos dengan peringkat
        $lolos = $validasi2->Validasi2Parsed()
            ->where('status_validasi', 'lolos')
            ->orderByDesc('Total_Skor')
            ->get();
        
        $peringkat = 1;
        foreach ($lolos as $row) {
            RekapPenilaian::updateOrCreate(
                [
                    'year' => $validasi2->year,
                    'id_dinas' => $row->id_dinas,
                ],
                [
                    'nama_dinas' => $row->nama_dinas,
                    'kriteria_wtp' => $row->Kriteria_WTP,
                    'kriteria_kasus_hukum' => $row->Kriteria_Kasus_Hukum,
                    'lolos_validasi2' => true,
                    'peringkat' => $peringkat++,
                    'status_akhir' => 'lolos_final',
                ]
            );
        }
        
        // Update yang tidak lolos (tanpa peringkat)
        $tidakLolos = $validasi2->Validasi2Parsed()
            ->where('status_validasi', 'tidak_lolos')
            ->get();
            
        foreach ($tidakLolos as $row) {
            RekapPenilaian::updateOrCreate(
                [
                    'year' => $validasi2->year,
                    'id_dinas' => $row->id_dinas,
                ],
                [
                    'nama_dinas' => $row->nama_dinas,
                    'kriteria_wtp' => $row->Kriteria_WTP,
                    'kriteria_kasus_hukum' => $row->Kriteria_Kasus_Hukum,
                    'lolos_validasi2' => false,
                    'peringkat' => null,
                    'status_akhir' => 'tidak_lolos_validasi2',
                ]
            );
        }
    }
    
    /**
     * Get peringkat akhir dari validasi 2 yang lolos
     */
    public function getPeringkatAkhir($year)
    {
        return RekapPenilaian::where('year', $year)
            ->where('status_akhir', 'lolos_final')
            ->orderBy('peringkat')
            ->with('dinas')
            ->get();
    }
    
    /**
     * Update rekap saat Wawancara finalized
     * Formula: total_skor_final = (0.9 * total_skor_validasi1) + (0.1 * nilai_wawancara)
     */
    public function updateFromWawancara($year)
    {
        // Ambil semua wawancara untuk tahun ini (tidak perlu filter is_finalized karena sudah di-update sebelum method ini dipanggil)
        $wawancaraData = \App\Models\Pusdatin\Wawancara::where('year', $year)->get();
        
        if ($wawancaraData->isEmpty()) {
            throw new \Exception('Tidak ada data wawancara untuk tahun '.$year);
        }
        
        foreach ($wawancaraData as $wawancara) {
            $rekap = RekapPenilaian::where([
                'year' => $year,
                'id_dinas' => $wawancara->id_dinas
            ])->first();
            
            if (!$rekap) {
                \Log::warning("Rekap tidak ditemukan untuk id_dinas: {$wawancara->id_dinas}, year: {$year}");
                continue;
            }
            
            // Pastikan nilai wawancara ada
            if (is_null($wawancara->nilai_wawancara)) {
                \Log::warning("Nilai wawancara null untuk id_dinas: {$wawancara->id_dinas}, year: {$year}");
                continue;
            }
            
            // Hitung total skor final: 90% dari nilai SLHD + 10% Wawancara
            $nilaiSLHD = $rekap->nilai_slhd ?? 0;
            $total_skor_final = (0.9 * $nilaiSLHD) + (0.1 * $wawancara->nilai_wawancara);
            
            $rekap->update([
                'nilai_wawancara' => $wawancara->nilai_wawancara,
                'lolos_wawancara' => true,
                'total_skor_final' => $total_skor_final
            ]);
            
            \Log::info("Updated rekap for dinas {$wawancara->id_dinas}: nilai_slhd={$nilaiSLHD}, nilai_wawancara={$wawancara->nilai_wawancara}, skor_final={$total_skor_final}");
        }
        
        // Hitung peringkat final berdasarkan skor_final (descending)
        $rekapWithScore = RekapPenilaian::where('year', $year)
            ->whereNotNull('total_skor_final')
            ->orderByDesc('total_skor_final')
            ->get();
        
        $peringkat = 1;
        foreach ($rekapWithScore as $rekap) {
            $rekap->update(['peringkat_final' => $peringkat++]);
        }
        
        \Log::info("Finalized wawancara for year {$year}, updated {$wawancaraData->count()} records");
    }
    
    /**
     * Reset final scores (untuk unfinalize wawancara)
     */
    public function resetFinalScores($year)
    {
        RekapPenilaian::where('year', $year)->update([
            'nilai_wawancara' => null,
            'lolos_wawancara' => false,
            'total_skor_final' => null,
            'peringkat_final' => null,
            'status_akhir' => null
        ]);
    }
    
    /**
     * Get history penilaian satu dinas
     */
    public function getHistoryDinas($idDinas, $year)
    {
        return RekapPenilaian::where('year', $year)
            ->where('id_dinas', $idDinas)
            ->with('dinas')
            ->first();
    }
}

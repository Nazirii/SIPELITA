<?php

namespace App\Listeners;

use App\Events\PenilaianSLHDUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Pusdatin\PenilaianPenghargaan;
use App\Models\Pusdatin\Wawancara;
use App\Models\Pusdatin\RekapPenilaian;
use Illuminate\Support\Facades\Storage;
class HandleUnfinalizedPenilaianSLHD
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PenilaianSLHDUpdated $event): void
    {
        $slhd = $event->penilaianSLHD;
         if ($slhd->getOriginal('status') === 'finalized' && $slhd->status !== 'finalized') {
            // hapus file Template terkait
            $templatePath = "penilaian/template_penilaian_penghargaan_{$slhd->year}.xlsx"; 
            Storage::disk('templates')->delete($templatePath);

            // hapus data penghargaan terkait (akan cascade delete Validasi1 & Validasi2 via DB)
            PenilaianPenghargaan::where('penilaian_slhd_ref_id', $slhd->id)->delete();
            
            // hapus data Wawancara untuk tahun terkait (tidak ada FK cascade ke Validasi2)
            Wawancara::where('year', $slhd->year)->delete();

            // Reset rekap penilaian - hapus semua data penilaian setelah SLHD
            RekapPenilaian::where('year', $slhd->year)->update([
                'nilai_slhd' => null,
                'lolos_slhd' => false,
                'nilai_penghargaan' => null,
                'masuk_penghargaan' => false,
                'nilai_iklh' => null,
                'total_skor_validasi1' => null,
                'lolos_validasi1' => false,
                'lolos_validasi2' => false,
                'kriteria_wtp' => null,
                'kriteria_kasus_hukum' => null,
                'peringkat' => null,
                'nilai_wawancara' => null,
                'lolos_wawancara' => false,
                'total_skor_final' => null,
                'peringkat_final' => null,
                'status_akhir' => null
            ]);
        }
    }
}

<?php

namespace App\Listeners;

use App\Events\PenilaianPenghargaanUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Pusdatin\RekapPenilaian;
use App\Models\Pusdatin\Validasi1;
use App\Models\Pusdatin\Validasi2;
use App\Models\Pusdatin\Wawancara;

class HandleUnfinalizedPenilaianPenghargaan
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
    public function handle(PenilaianPenghargaanUpdated $event): void
    {
        $penghargaan = $event->penilaianPenghargaan;
        
        // Jika status berubah dari finalized ke non-finalized
        if ($penghargaan->getOriginal('status') === 'finalized' && $penghargaan->status !== 'finalized') {
            // Hapus data Validasi1 & Validasi2 terkait (cascade akan handle Validasi2)
            Validasi1::where('penilaian_penghargaan_id', $penghargaan->id)->delete();
            
            // Hapus Wawancara untuk year terkait
            Wawancara::where('year', $penghargaan->year)->delete();

            // Reset rekap penilaian - hapus data setelah penghargaan
            RekapPenilaian::where('year', $penghargaan->year)->update([
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

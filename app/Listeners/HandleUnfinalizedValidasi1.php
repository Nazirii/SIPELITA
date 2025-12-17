<?php

namespace App\Listeners;

use App\Events\Validasi1Updated;
use App\Models\Pusdatin\Validasi2;
use App\Models\Pusdatin\RekapPenilaian;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleUnfinalizedValidasi1
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
    public function handle(Validasi1Updated $event): void
    {
        $validasi1 = $event->validasi1;
        
        // Jika status berubah dari finalized ke non-finalized, hapus data Validasi2
        if ($validasi1->getOriginal('is_finalized') === true && $validasi1->is_finalized === false) {
            Validasi2::where('validasi_1_id', $validasi1->id)->delete();
            
            // Hapus Wawancara untuk year terkait (tidak ada FK cascade)
            \App\Models\Pusdatin\Wawancara::where('year', $validasi1->year)->delete();

            // Reset rekap penilaian - hapus data setelah Validasi1
            RekapPenilaian::where('year', $validasi1->year)->update([
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

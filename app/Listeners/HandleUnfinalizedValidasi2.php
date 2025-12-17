<?php

namespace App\Listeners;

use App\Events\Validasi2Updated;
use App\Models\Pusdatin\Wawancara;
use App\Models\Pusdatin\RekapPenilaian;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleUnfinalizedValidasi2
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
    public function handle(Validasi2Updated $event): void
    {
        $validasi2 = $event->validasi2;
        
        // Jika status berubah dari finalized ke non-finalized, hapus data Wawancara untuk year terkait
        if ($validasi2->getOriginal('is_finalized') === true && $validasi2->is_finalized === false) {
            // Hapus semua Wawancara untuk year terkait (tidak ada FK cascade)
            Wawancara::where('year', $validasi2->year)->delete();

            // Reset rekap penilaian - hapus data setelah Validasi2
            RekapPenilaian::where('year', $validasi2->year)->update([
                'lolos_validasi2' => false,
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

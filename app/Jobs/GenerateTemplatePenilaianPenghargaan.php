<?php

namespace App\Jobs;

use App\Models\Pusdatin\PenilaianSLHD;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\ExcelService;
use App\Services\SLHDService;

class GenerateTemplatePenilaianPenghargaan implements ShouldQueue
{
    use Queueable;
    protected $penilaianSLHD;
    /**
     * Create a new job instance.
     */
    public function __construct(PenilaianSLHD $penilaianSLHD)
    {
        $this->penilaianSLHD = $penilaianSLHD;
    }

    /**
     * Execute the job.
     */
    public function handle(ExcelService $excelService, SLHDService $slhdService): void
    {
        $parsed=$this->penilaianSLHD->penilaianSLHDParsed()->get();
        $eligible=[];
        $path="penilaian/template_penilaian_penghargaan_".$this->penilaianSLHD->year.".xlsx";
        $multiplier = [
        'adiwiyata' => 4,
        'proklim'   => 100,
        'proper'    => 3,
        'kaltaparu' => 3,
        'adipura'   => 90,
    ];
    //seleksi

        foreach($parsed as $row){
            $data=$row->toArray();
            if($slhdService->passesSLHD($data)){

                $eligible[]=[
                    'id_dinas'=>$data['id_dinas'],
                    'year'=>$this->penilaianSLHD->year,
                    'nama_dinas'=>$data['nama_dinas'],
                    'skor_total'=>$slhdService->calculate($data),
                ];
            }

        }
    //generate excel
        $excelService->generateTemplatePenilaianPenghargaan($eligible,$multiplier,$path,'templates');
        

    }
}

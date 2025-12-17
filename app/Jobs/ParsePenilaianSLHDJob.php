<?php

namespace App\Jobs;

use App\Models\Pusdatin\Parsed\PenilaianSLHD_Parsed;
use App\Models\Pusdatin\PenilaianSLHD;
use App\Services\ExcelService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\SLHDService;
use Throwable;

class ParsePenilaianSLHDJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;
    protected $batch;
    
    /**
     * Create a new job instance.
     */

    public function __construct($batch)
    {
        $this->batch = $batch;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //

        $this->batch->update(['status' => 'parsing']);
        $map=[
        'id_dinas' => 'int',
    

        'Bab_1' => 'int',
        'Jumlah_Pemanfaatan_Pelayanan_Laboratorium' => 'int',
        'Daya_Dukung_dan_Daya_Tampung_Lingkungan_Hidup' => 'int',
        'Kajian_Lingkungan_Hidup_Strategis' => 'int',
        'Keanekaragaman_Hayati' => 'int',
        'Kualitas_Air' => 'int',
        'Laut_Pesisir_dan_Pantai' => 'int',
        'Kualitas_Udara' => 'int',
        'Pengelolaan_Sampah_dan_Limbah' => 'int',
        'Lahan_dan_Hutan' => 'int',
        'Perubahan_Iklim' => 'int',
        'Risiko_Bencana' => 'int',
        'Penetapan_Isu_Prioritas' => 'int',
        'Bab_3' => 'int',
        'Bab_4' => 'int',
        'Bab_5' => 'int'
    ];
        $rowToInsert=[];

        try{

            $filepath = Storage::disk('pusdatin')->path($this->batch->file_path);
            
            Log::info("Parsing file at path: ".$filepath);
            $excel=new ExcelService;
            $slhdService=new SLHDService;
            $rows=$excel->import($filepath);
            
            // Eager load semua dinas sekali untuk performance (1 query saja)
            $allDinas = \App\Models\Dinas::all()->keyBy('id');
            
        foreach($rows as $row){
            $errors=[];
            $data=[
                'penilaian_slhd_id' => $this->batch->id,
            ];
                foreach($map as $field => $type){
                    $data[$field]= safe($field, fn() => validateValue($row[$field] ?? null, $type), $errors);
                }   
                
                // Skip row kosong (id_dinas null berarti row kosong)
                if (!isset($data['id_dinas']) || $data['id_dinas'] === null) {
                    continue; // Skip row ini
                }
                
                // Validasi dan ambil nama dinas dari database (lebih konsisten)
                $dinas = $allDinas->get($data['id_dinas']);
                if ($dinas) {
                    $data['nama_dinas'] = $dinas->nama_dinas;
                } else {
                    $errors['id_dinas'] = "Dinas dengan ID {$data['id_dinas']} belum terdaftar di sistem.";
                    $data['nama_dinas'] = $row['nama_dinas'] ?? null; // Fallback ke Excel
                }
                
                $data['status']= empty($errors) ? 'parsed_ok' : 'parsed_error';
                $data['error_messages']= empty($errors) ? null : json_encode($errors);

                $data['created_at']= now();
                $data['updated_at']= now();
                $data['Total_Skor']= safe('Total_Skor', fn() => $slhdService->calculate($row), $errors); 
                // PenilaianSLHD_Parsed::create($data);
                $rowToInsert[]=$data;
                
                // Log::info("Row Data: ".json_encode($row,JSON_UNESCAPED_UNICODE));
                // Log::info($row['bab_1']);
            }
            if(!empty($rowToInsert)){
                PenilaianSLHD_Parsed::insert($rowToInsert);
            }
            $this->batch->update(['status' => 'parsed_ok']);
        }
        catch(Throwable $e){

            // TODO: Implement actual parsing here once the Excel library is installed and configured.
            Log::error("Fatal parsing error: ".$e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
            // Log::info("Parsed ".count($sheets)." sheets from the Excel file.");
            $this->batch->update(['status' => 'parsed_failed', 'error_messages' => $e->getMessage()
        ]);
        }
        

    }
}

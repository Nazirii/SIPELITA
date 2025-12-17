<?php

namespace App\Models\Files;

use Illuminate\Database\Eloquent\Model;
use App\Models\Submission;

class TabelUtama extends Model
{
    //
    protected $table = 'tabel_utama';
    protected $fillable = [
        'submission_id',
        'path',
        'kode_tabel',
        'status',
        'matra',
        'updated_at',
        'catatan_admin',
    ];
    public const MIN_COUNT=80;
    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }
    public function finalize(){
        app('App\Services\DocumentFinalizer')->finalize($this,'tabelUtama'.$this->kode_tabel);
    }
}

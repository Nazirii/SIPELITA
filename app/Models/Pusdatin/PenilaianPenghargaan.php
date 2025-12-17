<?php

namespace App\Models\Pusdatin;

use Illuminate\Database\Eloquent\Model;

class PenilaianPenghargaan extends Model
{
    protected $table = 'penilaian_penghargaan';
    protected $fillable = [
        'penilaian_slhd_ref_id',
        'year',
        'status',
        'uploaded_by',      
        'file_path',
        'uploaded_at',
        'finalized_at', 
        'is_finalized',
        'catatan',  
    ];
    public function penilaianSLHD()
    {
        return $this->bpelongsTo(PenilaianSLHD::class, 'penilaian_slhd_ref_id');
    }
    public function PenilaianPenghargaanParsed()
    {
        return $this->hasMany(Parsed\PenilaianPenghargaan_Parsed::class, 'penilaian_penghargaan_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by')->select(['id', 'email']);
    }

    public function Validasi1()
    {
        return $this->hasOne(Validasi1::class, 'penilaian_penghargaan_ref_id');
    }
    protected static function booted(){
    static::updated(function ($penilaianPenghargaan) {
        event(new \App\Events\PenilaianPenghargaanUpdated($penilaianPenghargaan));
    });
}
}

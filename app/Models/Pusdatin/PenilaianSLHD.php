<?php

namespace App\Models\Pusdatin;

use Illuminate\Database\Eloquent\Model;
use App\Models\Pusdatin\Parsed\PenilaianSLHD_Parsed;

class PenilaianSLHD extends Model
{
    protected $table = 'penilaian_slhd';
    protected $fillable = [
        // Define fillable attributes here
        'year',
        'status',
        'uploaded_by',
        'file_path',
        'uploaded_at',
        'finalized_at',
        'is_finalized',
        'catatan',  
    ];

    public function penilaianSLHDParsed()
    {
        return $this->hasMany(PenilaianSLHD_Parsed::class, 'penilaian_slhd_id');
    }
    
    public function penilaianPenghargaan()
    {
        return $this->hasMany(PenilaianPenghargaan::class, 'penilaian_slhd_ref_id');
    }
    
    public function uploadedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
    
    protected static function booted()
    {
        static::updated(function ($slhd) {
            event(new \App\Events\PenilaianSLHDUpdated($slhd));
        });
    }
}
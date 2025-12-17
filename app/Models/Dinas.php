<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dinas extends Model
{
    protected $table = 'dinas';
    protected $fillable = ['region_id', 'nama_dinas', 'kode_dinas', 'status'];  
    public function region(){
    return $this->belongsTo(Region::class, 'region_id');
    }
    public function submissions()
    {
        return $this->hasMany(Submission::class, 'id_dinas');
    }
    
}


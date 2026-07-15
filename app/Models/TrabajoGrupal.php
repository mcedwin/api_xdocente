<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrabajoGrupal extends Model
{
    use SoftDeletes;

    protected $table = 'trabajos_grupales';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'unidad_id',
        'nombre',
        'fecha',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function unidad()
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }

    public function criterios()
    {
        return $this->hasMany(CriterioTrabajoGrupal::class, 'trabajo_grupal_id');
    }

    public function grupos()
    {
        return $this->hasMany(Grupo::class, 'trabajo_grupal_id');
    }
}

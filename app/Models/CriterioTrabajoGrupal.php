<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CriterioTrabajoGrupal extends Model
{
    use SoftDeletes;

    protected $table = 'criterios_trabajo_grupal';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'trabajo_grupal_id',
        'nombre',
        'puntaje_maximo',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    protected $casts = [
        'puntaje_maximo' => 'decimal:2',
    ];

    public function trabajoGrupal()
    {
        return $this->belongsTo(TrabajoGrupal::class, 'trabajo_grupal_id');
    }

    public function puntajesGrupo()
    {
        return $this->hasMany(PuntajeCriterioGrupo::class, 'criterio_id');
    }

    public function ajustesIndividuales()
    {
        return $this->hasMany(AjusteIndividualGrupo::class, 'criterio_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PuntajeCriterioGrupo extends Model
{
    use SoftDeletes;

    protected $table = 'puntajes_criterio_grupo';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'grupo_id',
        'criterio_id',
        'puntaje',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    protected $casts = [
        'puntaje' => 'decimal:2',
    ];

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function criterio()
    {
        return $this->belongsTo(CriterioTrabajoGrupal::class, 'criterio_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grupo extends Model
{
    use SoftDeletes;

    protected $table = 'app_groups';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'trabajo_grupal_id',
        'nombre',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    public function trabajoGrupal()
    {
        return $this->belongsTo(TrabajoGrupal::class, 'trabajo_grupal_id');
    }

    public function estudiantes()
    {
        return $this->belongsToMany(Estudiante::class, 'app_group_members', 'grupo_id', 'estudiante_id');
    }

    public function puntajesCriterio()
    {
        return $this->hasMany(PuntajeCriterioGrupo::class, 'grupo_id');
    }

    public function ajustesIndividuales()
    {
        return $this->hasMany(AjusteIndividualGrupo::class, 'grupo_id');
    }
}

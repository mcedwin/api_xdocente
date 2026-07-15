<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Estudiante extends Model
{
    use SoftDeletes;

    protected $table = 'estudiantes';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'curso_id',
        'nombre',
        'notas',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    protected $casts = [
        'notas' => 'array',
    ];

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function registrosAsistencia()
    {
        return $this->hasMany(RegistroAsistencia::class, 'estudiante_id');
    }

    public function calificacionesTareas()
    {
        return $this->hasMany(CalificacionTarea::class, 'estudiante_id');
    }

    public function calificacionesPracticas()
    {
        return $this->hasMany(CalificacionPractica::class, 'estudiante_id');
    }

    public function calificacionesParticipacion()
    {
        return $this->hasMany(CalificacionParticipacion::class, 'estudiante_id');
    }

    public function grupos()
    {
        return $this->belongsToMany(Grupo::class, 'integrantes_grupo', 'estudiante_id', 'grupo_id');
    }

    public function ajustesIndividualesGrupo()
    {
        return $this->hasMany(AjusteIndividualGrupo::class, 'estudiante_id');
    }

    public function calificacionesProyecto()
    {
        return $this->hasMany(CalificacionProyecto::class, 'estudiante_id');
    }

    public function alertas()
    {
        return $this->hasMany(Alerta::class, 'estudiante_id');
    }
}

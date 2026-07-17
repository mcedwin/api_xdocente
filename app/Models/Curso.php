<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Curso extends Model
{
    use SoftDeletes;

    protected $table = 'app_courses';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'usuario_id',
        'nombre',
        'descripcion',
        'indice_unidad_seleccionada',
        'puntaje_max_tarea',
        'puntaje_max_practica',
        'puntaje_max_participacion',
        'puntaje_max_trabajo_grupal',
        'puntaje_max_proyecto',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    protected $casts = [
        'indice_unidad_seleccionada' => 'integer',
        'puntaje_max_tarea' => 'decimal:2',
        'puntaje_max_practica' => 'decimal:2',
        'puntaje_max_participacion' => 'decimal:2',
        'puntaje_max_trabajo_grupal' => 'decimal:2',
        'puntaje_max_proyecto' => 'decimal:2',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function estudiantes()
    {
        return $this->hasMany(Estudiante::class, 'curso_id');
    }

    public function unidades()
    {
        return $this->hasMany(Unidad::class, 'curso_id');
    }

    public function alertas()
    {
        return $this->hasMany(Alerta::class, 'curso_id');
    }
}

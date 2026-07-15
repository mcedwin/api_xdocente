<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unidad extends Model
{
    use SoftDeletes;

    protected $table = 'unidades';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'curso_id',
        'nombre',
        'orden',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function sesiones()
    {
        return $this->hasMany(Sesion::class, 'unidad_id');
    }

    public function tareas()
    {
        return $this->hasMany(Tarea::class, 'unidad_id');
    }

    public function practicas()
    {
        return $this->hasMany(Practica::class, 'unidad_id');
    }

    public function itemsParticipacion()
    {
        return $this->hasMany(ItemParticipacion::class, 'unidad_id');
    }

    public function trabajosGrupales()
    {
        return $this->hasMany(TrabajoGrupal::class, 'unidad_id');
    }

    public function proyectos()
    {
        return $this->hasMany(Proyecto::class, 'unidad_id');
    }
}

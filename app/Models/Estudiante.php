<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Estudiante extends Model
{
    use SoftDeletes;

    protected $table = 'app_students';
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

    public function alertas()
    {
        return $this->hasMany(Alerta::class, 'estudiante_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CriterioProyecto extends Model
{
    use SoftDeletes;

    protected $table = 'app_project_criteria';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'proyecto_id',
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

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function calificaciones()
    {
        return $this->hasMany(CalificacionProyecto::class, 'criterio_id');
    }
}

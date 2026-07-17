<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proyecto extends Model
{
    use SoftDeletes;

    protected $table = 'app_projects';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'unidad_id',
        'nombre',
        'created_at',
        'updated_at',
        'deleted_at',
        'sync_status',
        'device_id',
    ];

    public function unidad()
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }

    public function criterios()
    {
        return $this->hasMany(CriterioProyecto::class, 'proyecto_id');
    }

    public function calificaciones()
    {
        return $this->hasMany(CalificacionProyecto::class, 'proyecto_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemParticipacion extends Model
{
    use SoftDeletes;

    protected $table = 'app_participation_items';
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

    public function calificaciones()
    {
        return $this->hasMany(CalificacionParticipacion::class, 'item_participacion_id');
    }
}

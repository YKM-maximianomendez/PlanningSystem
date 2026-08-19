<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Workcenter extends Model
{
    public $connection = 'mrp';

    protected $table = 'workcenters';

    protected $attributes = [
        'workcenter_id',
        'workcenter_code',
        'workcenter_description',
    ];

    protected $primaryKey = 'workcenter_id';

    public $timestamps = false;

    public function workcenterType(): BelongsTo
    {
        return $this->belongsTo(WorkcenterType::class, 'workcenter_type_id', 'workcenter_type_id');
    }
}

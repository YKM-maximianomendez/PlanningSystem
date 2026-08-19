<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkcenterType extends Model
{
    public $connection = 'mrp';

    protected $table = 'workcenter_types';

    protected $primaryKey = 'workcenter_type_id';

    protected $attributes = [
        'workcenter_type_id',
        'workcenter_type_code',
        'workcenter_type_description',
    ];

    public $timestamps = false;

    public function workcenters()
    {
        return $this->hasMany(Workcenter::class, 'workcenter_type_id', 'workcenter_type_id');
    }
}

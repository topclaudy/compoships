<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    use Compoships;

    protected $guarded = [];

    public function links()
    {
        return $this->belongsToMany(
            Node::class,
            'node_links',
            ['left_region_code', 'left_division_id'],
            ['right_region_code', 'right_division_id'],
            ['region_code', 'division_id'],
            ['region_code', 'division_id']
        );
    }
}

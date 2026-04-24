<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Database\Eloquent\Relations\Pivot;
use Awobaz\Compoships\Tests\Enums\PivotRole;

class ProjectTeamEnumPivot extends Pivot
{
    protected $table = 'project_team';

    protected $casts = [
        'role' => PivotRole::class,
    ];
}

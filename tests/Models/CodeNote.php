<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string|null $group_code
 * @property string|null $item_code
 * @property string|null $body
 * @property-read Code|null $code
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class CodeNote extends Model
{
    use Compoships;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function code()
    {
        return $this->belongsTo(Code::class, ['group_code', 'item_code'], ['group_code', 'item_code']);
    }
}

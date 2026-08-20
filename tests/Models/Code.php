<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string|null $group_code
 * @property string|null $item_code
 * @property string|null $label
 * @property-read CodeNote[] $notes
 * @property-read CodeNote|null $latestNote
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Code extends Model
{
    use Compoships;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function notes()
    {
        return $this->hasMany(CodeNote::class, ['group_code', 'item_code'], ['group_code', 'item_code']);
    }

    public function latestNote()
    {
        return $this->notes()->one()->latestOfMany();
    }
}

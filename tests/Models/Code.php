<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string|null $group_code
 * @property string|null $item_code
 * @property string|null $parent_group_code
 * @property string|null $parent_item_code
 * @property string|null $label
 * @property-read Code|null $parentCode
 * @property-read CodeNote[] $notes
 * @property-read CodeNote|null $latestNote
 * @property-read CodeNote|null $firstNote
 * @property-read CodeNote $firstNoteOrDefault
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

    public function firstNote()
    {
        return $this->hasOne(CodeNote::class, ['group_code', 'item_code'], ['group_code', 'item_code']);
    }

    public function parentCode()
    {
        return $this->belongsTo(self::class, ['parent_group_code', 'parent_item_code'], ['group_code', 'item_code']);
    }

    public function firstNoteOrDefault()
    {
        return $this->firstNote()->withDefault(function (CodeNote $note, Code $code) {
            $note->body = 'default for '.$code->label;
        });
    }
}

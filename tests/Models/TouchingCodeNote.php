<?php

namespace Awobaz\Compoships\Tests\Models;

/**
 * CodeNote variant that touches its composite parent on save.
 */
class TouchingCodeNote extends CodeNote
{
    protected $table = 'code_notes';

    protected $touches = ['code'];
}

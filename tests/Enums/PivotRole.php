<?php

namespace Awobaz\Compoships\Tests\Enums;

enum PivotRole: string
{
    case Lead = 'lead';
    case Member = 'member';
    case Reviewer = 'reviewer';
}

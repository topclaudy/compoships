<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Drivers That Do NOT Support Tuple WHERE IN
    |--------------------------------------------------------------------------
    |
    | These drivers will use expanded OR/AND logic instead of tuple syntax:
    | (col1, col2) IN ((?, ?), (?, ?))
    |
    */

    'non_tuple_drivers' => [
        'sqlsrv',
    ],

];
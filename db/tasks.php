<?php

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\logstore_elasticsearch\task\emit_task',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour'      => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];

<?php

defined('MOODLE_INTERNAL') || die;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/task/emit_task.php';

use \logstore_elasticsearch\task\emit_task;
use \logstore_elasticsearch\log\store;
use \Pimple\Container;

class emit_task_test extends advanced_testcase {

    /**
     * setUp
     * @return void
     */
    protected function setUp() {
        $this->resetAfterTest();
    }

    /**
     * tearDown
     * @return void
     */
    protected function tearDown() {
        Mockery::close();
    }

    /**
     * tests execute() called with no events in the database
     * @return void
     */
    public function test_execute_with_no_events() {
        $container = new Container();
        $container['max_batch_size'] = 0;
        $container['moodle_controller'] = function () {
            $m = Mockery::mock('\LogExpander\Controller');
            $m->shouldReceive('createEvents')->once()->with([])->andReturn([]);
            return $m;
        };
        $container['translator_controller'] = function () {
            $m = Mockery::mock('\MXTranslator\Controller');
            $m->shouldReceive('createEvents')->once()->with([])->andReturn([]);
            return $m;
        };
        $container['elasticsearch_controller'] = function () {
            $m = Mockery::mock('\logstore_elasticsearch\log\elasticsearch_controller');
            return $m;
        };

        $emit_task = new emit_task();
        $emit_task->execute($container);
    }

    /**
     * tests execute() called with some events in the database
     * @return void
     */
    public function test_execute_with_some_events() {
        global $DB;

        $this->loadDataSet($this->createArrayDataSet([
            'logstore_elasticsearch_log' => [
                [
                    'id', 'eventname', 'component', 'action', 'target', 'objecttable', 'objectid', 'crud', 'edulevel', 'contextid',
                    'contextlevel', 'contextinstanceid', 'userid', 'courseid', 'relateduserid', 'anonymous', 'other', 'timecreated',
                    'origin', 'ip', 'realuserid'
                ],
                [
                    1, '\core\event\course_viewed', 'core', 'viewed', 'course', '', 0, 'r', 2, 21,
                    50, 2, 2, 2, 0, 0, 'N;', 1496925675, 'web', 'localhost', 0
                ],
                [
                    2, '\core\event\course_viewed', 'core', 'viewed', 'course', '', 0, 'r', 2, 21,
                    50, 2, 2, 2, 0, 0, 'N;', 1496925676, 'web', 'localhost', 0
                ],
            ]
        ]));

        $events = $DB->get_records('logstore_elasticsearch_log');
        foreach ($events as $index => $event) {
            $events[$index] = (array)$event;
        }

        $moodle_events = [[
            'event'    => $events[1],
            'sendmbox' => 0,
        ], [
            'event'    => $events[2],
            'sendmbox' => 0,
        ]];
        $translator_events = [[
            'user_id'     => 2,
            'user_name'   => 'Admin User',
            'source_url'  => 'http://moodle.org',
            'recipe'      => 'course_viewed',
            'course_url'  => 'http://localhost/course/view.php?id=2',
            'course_name' => '001',
        ], [
            'user_id'     => 2,
            'user_name'   => 'Admin User',
            'source_url'  => 'http://moodle.org',
            'recipe'      => 'course_viewed',
            'course_url'  => 'http://localhost/course/view.php?id=2',
            'course_name' => '001',
        ]];

        $statements = [
            [
                'context' => [
                    'extensions' => [
                        store::EXT_KEY => $events[1],
                    ],
                ],
            ],
            [
                'context' => [
                    'extensions' => [
                        store::EXT_KEY => $events[2],
                    ],
                ],
            ],
        ];
        $responses = [
            [
                'statement' => $statements[0],
                'response'  => 'wobble',
            ],
            [
                'statement' => $statements[1],
                'response'  => 'wobble',
            ],
        ];

        $container = new Container();
        $container['max_batch_size'] = 0;
        $container['moodle_controller'] = function () use ($events, $moodle_events) {
            $m = Mockery::mock('\LogExpander\Controller');
            $m->shouldReceive('createEvents')->once()->with((array)$events)->andReturn($moodle_events);
            return $m;
        };
        $container['translator_controller'] = function () use ($moodle_events, $translator_events) {
            $m = Mockery::mock('\MXTranslator\Controller');
            $m->shouldReceive('createEvents')->once()->with($moodle_events)->andReturn($translator_events);
            return $m;
        };
        $container['elasticsearch_controller'] = function () use ($translator_events, $statements, $responses) {
            $m = Mockery::mock('\logstore_elasticsearch\log\elasticsearch_controller');
            $m->shouldReceive('create_statements')->once()->with($translator_events)->andReturn($statements);
            $m->shouldReceive('send_statements')->once()->with($statements)->andReturn($responses);
            return $m;
        };

        $emit_task = new emit_task();
        $emit_task->execute($container);
        $this->assertCount(0, $DB->get_records('logstore_elasticsearch_log'));
    }

    /**
     * tests execute() only deletes successfully sent statements from the database (in batch mode)
     * @return void
     */
    public function test_execute_when_some_statements_fail_to_send() {
        global $DB;

        $this->loadDataSet($this->createArrayDataSet([
            'logstore_elasticsearch_log' => [
                [
                    'id', 'eventname', 'component', 'action', 'target', 'objecttable', 'objectid', 'crud', 'edulevel', 'contextid',
                    'contextlevel', 'contextinstanceid', 'userid', 'courseid', 'relateduserid', 'anonymous', 'other', 'timecreated',
                    'origin', 'ip', 'realuserid'
                ],
                [
                    1, '\core\event\course_viewed', 'core', 'viewed', 'course', '', 0, 'r', 2, 21,
                    50, 2, 2, 2, 0, 0, 'N;', 1496925675, 'web', 'localhost', 0
                ],
                [
                    2, '\core\event\course_viewed', 'core', 'viewed', 'course', '', 0, 'r', 2, 21,
                    50, 2, 2, 2, 0, 0, 'N;', 1496925676, 'web', 'localhost', 0
                ],
            ]
        ]));

        $events = $DB->get_records('logstore_elasticsearch_log');
        foreach ($events as $index => $event) {
            $events[$index] = (array)$event;
        }

        $moodle_events = [[
            'event'    => $events[1],
            'sendmbox' => 0,
        ], [
            'event'    => $events[2],
            'sendmbox' => 0,
        ]];

        $translator_events = [[
            'user_id'     => 2,
            'user_name'   => 'Admin User',
            'source_url'  => 'http://moodle.org',
            'recipe'      => 'course_viewed',
            'course_url'  => 'http://localhost/course/view.php?id=2',
            'course_name' => '001'
        ], [
            'user_id'     => 2,
            'user_name'   => 'Admin User',
            'source_url'  => 'http://moodle.org',
            'recipe'      => 'course_viewed',
            'course_url'  => 'http://localhost/course/view.php?id=2',
            'course_name' => '001'
        ]];

        $statements = [
            [
                'context' => [
                    'extensions' => [
                        store::EXT_KEY => $events[1],
                    ],
                ],
            ],
            [
                'context' => [
                    'extensions' => [
                        store::EXT_KEY => $events[2],
                    ],
                ],
            ],
        ];
        $responses = [
            [
                'statement' => $statements[0],
                'response'  => 'wobble',
            ],
            null,
        ];

        $container = new Container();
        $container['max_batch_size'] = 0;
        $container['moodle_controller'] = function () use ($events, $moodle_events) {
            $m = Mockery::mock('\LogExpander\Controller');
            $m->shouldReceive('createEvents')->once()->with((array)$events)->andReturn($moodle_events);
            return $m;
        };
        $container['translator_controller'] = function () use ($moodle_events, $translator_events) {
            $m = Mockery::mock('\MXTranslator\Controller');
            $m->shouldReceive('createEvents')->once()->with($moodle_events)->andReturn($translator_events);
            return $m;
        };
        $container['elasticsearch_controller'] = function () use ($translator_events, $statements, $responses) {
            $m = Mockery::mock('\logstore_elasticsearch\log\elasticsearch_controller');
            $m->shouldReceive('create_statements')->once()->with($translator_events)->andReturn($statements);
            $m->shouldReceive('send_statements')->once()->with($statements)->andReturn($responses);
            return $m;
        };

        $emit_task = new emit_task();
        $emit_task->execute($container);
        $this->assertCount(1, $DB->get_records('logstore_elasticsearch_log'));
    }

    /**
     * tests get_name()
     * @return void
     */
    public function test_get_name() {
        $emit_task = new emit_task();
        $this->assertEquals('Emit records to Elasticsearch', $emit_task->get_name());
    }

}

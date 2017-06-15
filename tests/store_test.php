<?php

defined('MOODLE_INTERNAL') || die;

require_once __DIR__ . '/../../../../../../../vendor/autoload.php';
require_once __DIR__ . '/../classes/log/store.php';

use \logstore_elasticsearch\log\store;
use \MXTranslator\Events\Event;
use \Pimple\Container;

class store_test extends advanced_testcase {

    /**
     * setUp
     */
    protected function setUp() {
        $this->resetAfterTest();
    }

    /**
     * tearDown
     */
    protected function tearDown() {
        Mockery::close();
    }

    /**
     * tests process_events() called with no events
     */
    public function test_process_events_called_with_no_events() {
        $log_manager = Mockery::mock('\tool_log\log\manager');
        $store = new store($log_manager);

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

        $store->process_events([], $container);
    }

    /**
     * tests process_events() called with one event that fails to 'translate'
     */
    public function test_process_events_called_with_one_event_failing_to_translate() {
        $log_manager = Mockery::mock('\tool_log\log\manager');
        $store = new store($log_manager);
        $event = Mockery::mock('\core\event\course_viewed');
        $events = [$event];

        $moodle_events = [[
            'event'    => $event,
            'sendmbox' => 0,
            // etc
        ]];

        $container = new Container();
        $container['max_batch_size'] = 0;
        $container['moodle_controller'] = function () use ($event, $moodle_events) {
            $m = Mockery::mock('\LogExpander\Controller');
            $m->shouldReceive('createEvents')->once()->with([(array)$event])->andReturn($moodle_events);
            return $m;
        };
        $container['translator_controller'] = function () use ($moodle_events) {
            $m = Mockery::mock('\MXTranslator\Controller');
            $m->shouldReceive('createEvents')->once()->with($moodle_events)->andReturn([]);
            return $m;
        };
        $container['elasticsearch_controller'] = function () {
            $m = Mockery::mock('\logstore_elasticsearch\log\elasticsearch_controller');
            return $m;
        };

        $store->process_events($events, $container);
    }

    /**
     * tests process_events() called with one event
     */
    public function test_process_events_with_one_event() {
        $log_manager = Mockery::mock('\tool_log\log\manager');
        $store = new store($log_manager);
        $event = Mockery::mock('\core\event\course_viewed');
        $events = [$event];

        $moodle_events = [[
            'event'    => $event,
            'sendmbox' => 0,
            // etc
        ]];

        $translator_events = [[
            'user_id'     => 2,
            'user_name'   => 'Admin User',
            'source_url'  => 'http://moodle.org',
            'recipe'      => 'course_viewed',
            'course_url'  => 'http://localhost/course/view.php?id=2',
            'course_name' => '001'
            // etc
        ]];

        $statements = [[], ];
        $responses = [[], ];

        $container = new Container();
        $container['max_batch_size'] = 0;
        $container['moodle_controller'] = function () use ($event, $moodle_events) {
            $m = Mockery::mock('\LogExpander\Controller');
            $m->shouldReceive('createEvents')->once()->with([(array)$event])->andReturn($moodle_events);
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
            $m->shouldReceive('send_statements')->once()->with($statements)->andReturn([[
                'statement' => $statements[0],
                'response'  => $responses[0],
            ]]);
            return $m;
        };

        $result = $store->process_events($events, $container);
        $this->assertEquals([[
            'statement' => $statements[0],
            'response'  => $responses[0],
        ]], $result);
    }

    /**
     * tests process_events() correctly batches statements
     */
    public function test_process_events_batching() {
        $log_manager = Mockery::mock('\tool_log\log\manager');
        $store = new store($log_manager);
        $event = Mockery::mock('\core\event\course_viewed');
        $events = [$event];

        $moodle_events = [[
            'event'    => $event,
            'sendmbox' => 0,
            // etc
        ], [
            'event'    => $event,
            'sendmbox' => 0,
            // etc
        ]];

        $translator_events = [[
            'user_id'     => 2,
            'user_name'   => 'Admin User',
            'source_url'  => 'http://moodle.org',
            'recipe'      => 'course_viewed',
            'course_url'  => 'http://localhost/course/view.php?id=2',
            'course_name' => '001'
            // etc
        ], [
            'user_id'     => 2,
            'user_name'   => 'Admin User',
            'source_url'  => 'http://moodle.org',
            'recipe'      => 'course_viewed',
            'course_url'  => 'http://localhost/course/view.php?id=2',
            'course_name' => '001'
            // etc
        ]];

        $statements = [['foo' => 'bar'], ['foo' => 'bar'], ];
        $responses = [['wibble' => 'wobble'], ['wibble' => 'wobble'], ];

        $container = new Container();
        $container['max_batch_size'] = 1;
        $container['moodle_controller'] = function () use ($event, $moodle_events) {
            $m = Mockery::mock('\LogExpander\Controller');
            $m->shouldReceive('createEvents')->once()->with([(array)$event])->andReturn($moodle_events);
            return $m;
        };
        $container['translator_controller'] = function () use ($moodle_events, $translator_events) {
            $m = Mockery::mock('\MXTranslator\Controller');
            $m->shouldReceive('createEvents')->once()->with($moodle_events)->andReturn($translator_events);
            return $m;
        };
        $container['elasticsearch_controller'] = function () use ($translator_events, $statements, $responses) {
            $m = Mockery::mock('\logstore_elasticsearch\log\elasticsearch_controller');
            $m->shouldReceive('create_statements')->once()->with([$translator_events[0]])->andReturn([$statements[0]]);
            $m->shouldReceive('send_statements')->once()->with([$statements[0]])->andReturn([$responses[0]]);
            $m->shouldReceive('create_statements')->once()->with([$translator_events[1]])->andReturn([$statements[1]]);
            $m->shouldReceive('send_statements')->once()->with([$statements[1]])->andReturn([$responses[1]]);
            return $m;
        };

        $store->process_events($events, $container);
    }

    /**
     * test save_unsuccessfully_indexed_events()
     */
    public function test_save_unsuccessfully_indexed_events() {
        global $DB;

        $log_manager = Mockery::mock('\tool_log\log\manager');
        $store = new store($log_manager);

        $this->loadDataSet($this->createArrayDataSet([
            'logstore_elasticsearch_log' => [
                [
                    'id', 'eventname', 'component', 'action', 'target', 'objecttable', 'objectid', 'crud', 'edulevel', 'contextid',
                    'contextlevel', 'contextinstanceid', 'userid', 'courseid', 'relateduserid', 'anonymous', 'other', 'timecreated',
                    'origin', 'ip', 'realuserid'
                ],
                [
                    1, '\core\event\course_viewed', 'core', 'viewed', 'course', '', '', 'r', 2, 21,
                    50, 2, 2, 2, '', 0, 'N;', 1496925675, 'web', 'localhost', ''
                ],
                [
                    2, '\core\event\course_viewed', 'core', 'viewed', 'course', '', '', 'r', 2, 21,
                    50, 2, 2, 2, '', 0, 'N;', 1496925676, 'web', 'localhost', ''
                ],
                [
                    3, '\core\event\course_viewed', 'core', 'viewed', 'course', '', '', 'r', 2, 21,
                    50, 2, 2, 2, '', 0, 'N;', 1496925677, 'web', 'localhost', ''
                ],
            ]
        ]));
        $events = $DB->get_records('logstore_elasticsearch_log');
        $original_count = count($events);

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
            [
                'context' => [
                    'extensions' => [
                        store::EXT_KEY => $events[3],
                    ],
                ],
            ],
        ];
        $responses = [
            [
                'statement' => $statements[0],
                'response'  => null,
            ],
            [
                'statement' => $statements[1],
                'response'  => 'wibble',
            ],
            [
                'statement' => $statements[2],
                'response'  => null,
            ],
        ];

        $store->save_unsuccessfully_indexed_events($responses);
        $this->assertEquals($original_count + 2, $DB->count_records('logstore_elasticsearch_log'));
    }

    /**
     * tests is_logging()
     */
    public function test_is_logging() {
        $log_manager = Mockery::mock('\tool_log\log\manager');
        $store = new store($log_manager);
        $result = $store->is_logging();
        $this->assertTrue($result);
    }

    /**
     * tests the 'ext key' assumed by the store is what it's supposed to be
     */
    public function test_ext_key() {
        $event = new Event();
        $read = $event->read([
            'info'            => (object)[],
            'app'             => (object)[
                'fullname'    => 'Fred',
                'url'         => 'http://localhost',
                'summary'     => 'A hard course',
            ],
            'user'            => (object)[
                'id'          => 1,
                'url'         => '',
                'email'       => 'foo@example.com',
                'fullname'    => 'Foo',
            ],
            'course'          => (object)[
                'lang'        => 'en',
            ],
            'event'           => [
                'timecreated' => 1,
            ],
            'sendmbox'        => 0,
        ]);
        $this->assertEquals(store::EXT_KEY, $read[0]['context_ext_key']);
    }

    /**
     * tests is_event_ignored()
     */
    public function test_is_event_ignored_returns_true_when_event_unsupported() {
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group([
            'courseid' => $course->id,
        ]);
        $log_manager = Mockery::mock('\tool_log\log\manager');
        $store = new store($log_manager);
        $event = \core\event\group_created::create([
            'context'  => context_course::instance($course->id),
            'objectid' => $group->id,
        ]);
        $this->assertTrue($store->test_is_event_ignored($event));
    }

    /**
     * tests is_event_ignored()
     */
    public function test_is_event_ignored_returns_false_when_event_supported() {
        $course = $this->getDataGenerator()->create_course();
        $log_manager = Mockery::mock('\tool_log\log\manager');
        $store = new store($log_manager);
        $event = \core\event\course_viewed::create([
            'context' => context_course::instance($course->id),
        ]);
        $this->assertFalse($store->test_is_event_ignored($event));
    }

    /**
     * tests insert_event_entries() in background mode
     */
    public function test_insert_event_entries_in_background_mode() {
        global $DB;

        $log_manager = Mockery::mock('\tool_log\log\manager');
        $store = new store($log_manager);
        $course = $this->getDataGenerator()->create_course();
        $event = \core\event\course_viewed::create([
            'context' => context_course::instance($course->id),
        ]);
        $events = [$event->get_data()];

        $container = new Container();
        $container['background_mode'] = true;

        $store->test_insert_event_entries($events, $container);
        $this->assertCount(1, $DB->get_records('logstore_elasticsearch_log'));
    }

    /**
     * tests insert_event_entries() not in background mode
     */
    public function test_insert_event_entries_not_in_background_mode() {
        global $DB;

        $log_manager = Mockery::mock('\tool_log\log\manager');
        $store = new store($log_manager);
        $course = $this->getDataGenerator()->create_course();
        $event = Mockery::mock('\core\event\course_viewed');
        $events = [$event];

        $moodle_events = [[
            'event'    => $event,
            'sendmbox' => 0,
            // etc
        ]];

        $translator_events = [[
            'user_id'     => 2,
            'user_name'   => 'Admin User',
            'source_url'  => 'http://moodle.org',
            'recipe'      => 'course_viewed',
            'course_url'  => 'http://localhost/course/view.php?id=2',
            'course_name' => '001'
            // etc
        ]];

        $statements = [[], ];
        $responses = [[], ];

        $container = new Container();
        $container['max_batch_size'] = 1;
        $container['background_mode'] = false;

        $container['moodle_controller'] = function () use ($event, $moodle_events) {
            $m = Mockery::mock('\LogExpander\Controller');
            $m->shouldReceive('createEvents')->once()->with([(array)$event])->andReturn($moodle_events);
            return $m;
        };
        $container['translator_controller'] = function () use ($moodle_events, $translator_events) {
            $m = Mockery::mock('\MXTranslator\Controller');
            $m->shouldReceive('createEvents')->once()->with($moodle_events)->andReturn($translator_events);
            return $m;
        };
        $container['elasticsearch_controller'] = function () use ($translator_events, $statements) {
            $m = Mockery::mock('\logstore_elasticsearch\log\elasticsearch_controller');
            $m->shouldReceive('create_statements')->once()->with([$translator_events[0]])->andReturn([$statements[0]]);
            $m->shouldReceive('send_statements')->once()->with([$statements[0]])->andReturn([]);
            return $m;
        };

        $store->test_insert_event_entries($events, $container);
        $this->assertCount(0, $DB->get_records('logstore_elasticsearch_log'));
    }

    /**
     * tests get_dependencies()
     */
    public function test_get_dependencies() {
        $log_manager = Mockery::mock('\tool_log\log\manager');
        $store = new store($log_manager);
        $deps = $store->get_dependencies();
        $this->assertInstanceOf('\Pimple\Container', $deps);
        $this->assertEquals(30, $deps['max_batch_size']); // 30 is the default defined in settings.php
        $this->assertEquals(0, $deps['background_mode']); // 0 (false) is the default defined in settings.php
        $this->assertInstanceOf('\LogExpander\Controller', $deps['moodle_controller']);
        $this->assertInstanceOf('\MXTranslator\Controller', $deps['translator_controller']);
        $this->assertInstanceOf('\logstore_elasticsearch\log\elasticsearch_controller', $deps['elasticsearch_controller']);
    }

}

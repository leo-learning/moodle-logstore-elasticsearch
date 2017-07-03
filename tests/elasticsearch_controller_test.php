<?php

defined('MOODLE_INTERNAL') || die;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/log/elasticsearch_controller.php';

use \logstore_elasticsearch\log\elasticsearch_controller;

class elasticsearch_controller_test extends advanced_testcase {

    /**
     * tearDown
     */
    protected function tearDown() {
        Mockery::close();
    }

    /**
     * tests create_statements() creates a statement per event
     */
    public function test_create_statements_creates_statement_per_event() {
        $client = Mockery::mock('\Elasticsearch\Client');
        $event = [
            'recipe'             => 'course_viewed',
            'context_lang'       => 'en',
            'context_platform'   => 'Moodle',
            'context_info'       => new stdClass(),
            'context_ext'        => '',
            'context_ext_key'    => '',
            'app_url'            => 'http://localhost',
            'app_type'           => 'LMS',
            'app_name'           => 'Moodle',
            'app_description'    => 'LMS',
            'source_url'         => 'http://localhost',
            'source_type'        => '',
            'source_name'        => '',
            'source_description' => '',
            'user_name'          => 'Fred',
            'user_url'           => '',
            'user_id'            => 1,
            'time'               => 0,
            'course_url'         => 'http://localhost/course/view.php?id=2',
            'course_type'        => '',
            'course_name'        => '',
            'course_description' => '',
        ];
        $events = [$event, $event, $event];
        $controller = new elasticsearch_controller($client, [
            'course_viewed' => 'CourseViewed',
        ]);
        $statements = $controller->create_statements($events);
        $this->assertCount(count($events), $statements);
    }

    /**
     * tests send_statements() invoked once per statement
     */
    public function test_send_statements_indexes_every_statement() {
        $client = Mockery::mock('\Elasticsearch\Client');
        $statements = ['statement1', 'statement2', 'statement3'];
        $client->shouldReceive('index')->times(count($statements))->with(Mockery::subset([
            'index' => 'xapi',
            'type'  => 'statement',
        ]));
        $controller = new elasticsearch_controller($client, []);
        $responses = $controller->send_statements($statements);
        $this->assertCount(count($statements), $responses);
    }

    /**
     * tests send_statements() returns an empty array if client has no 'index' method
     */
    public function test_send_statements_returns_empty_array_if_client_has_no_index_method() {
        $statements = ['statement1', 'statement2', 'statement3'];
        $controller = new elasticsearch_controller(new \stdClass(), []);
        $responses = $controller->send_statements($statements);
        $this->assertEquals([], $responses);
    }

    /**
     * tests send_statements() returns statement along with null response on exception
     */
    public function test_send_statements_returns_null_response_on_exception() {
        $client = Mockery::mock('\Elasticsearch\Client');
        $statements = [
            [
                'context' => [
                    'extensions' => [],
                ],
            ],
        ];
        $client->shouldReceive('index')->once()->with(Mockery::subset([
            'index' => 'xapi',
            'type'  => 'statement',
        ]))->andThrow('Exception');
        $controller = new elasticsearch_controller($client, []);
        $responses = $controller->send_statements($statements);
        $this->assertEquals([[
            'statement' => $statements[0],
            'response'  => null,
        ]], $responses);
    }

}

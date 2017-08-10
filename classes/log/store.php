<?php

namespace logstore_elasticsearch\log;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../../vendor/autoload.php';

use \core\event\base as event_base;
use \tool_log\log\manager as log_manager;
use \tool_log\log\writer as log_writer;
use \tool_log\helper\store as helper_store;
use \tool_log\helper\reader as helper_reader;
use \tool_log\helper\buffered_writer as helper_writer;

use \LogExpander\Repository as moodle_repository;
use \LogExpander\Controller as moodle_controller;
use \MXTranslator\Controller as translator_controller;
use \XREmitter\Controller as xapi_controller;

use \Pimple\Container;
use \Elasticsearch\ClientBuilder;

class store implements log_writer {

    /**
     * @see \MXTranslator\Events\Event
     * @var string
     */
    const EXT_KEY = 'http://lrs.learninglocker.net/define/extensions/moodle_logstore_standard_log';

    use helper_store;
    use helper_reader;
    use helper_writer;

    /**
     * @see \tool_log\helper\store
     * @param log_manager $manager manager
     */
    public function __construct(log_manager $manager) {
        $this->helper_setup($manager);
    }

    /**
     * @see \tool_log\helper\buffered_writer
     * @param event_base $event event
     * @return boolean
     */
    protected function is_event_ignored(event_base $event) {
        return !array_key_exists(
            $event->eventname,
            moodle_controller::$routes
        );
    }

    /**
     * @see \tool_log\helper\buffered_writer
     * @param array     $events raw event data
     * @param Container $deps   dependencies
     * @return void
     */
    protected function insert_event_entries(array $events, Container $deps = null) {
        global $DB;

        if (empty($deps)) {
            $deps = $this->get_dependencies();
        }

        // if in background mode, don't process the events right now
        $background_mode = $deps['background_mode'];
        if ($background_mode) {
            $DB->insert_records('logstore_elasticsearch_log', $events);
            return;
        }

        // if not in background mode, process events immediately
        $results = $this->process_events($events, $deps);
        $this->save_unsuccessfully_indexed_events($results);
    }

    /**
     * @param array     $events events
     * @param Container $deps   dependencies
     * @return array
     */
    public function process_events(array $events, Container $deps) {
        foreach ($events as $index => $event) {
            $events[$index] = (array)$event;
        }

        /** @var moodle_controller $moodle_controller */
        $moodle_controller = $deps['moodle_controller'];
        $moodle_events = $moodle_controller->createEvents($events);
        if (empty($moodle_events)) {
            return [];
        }

        /** @var translator_controller $translator_ctrl */
        $translator_ctrl = $deps['translator_controller'];
        $translator_events = $translator_ctrl->createEvents($moodle_events);
        if (empty($translator_events)) {
            return [];
        }

        // send in batches (if so configured)
        $event_batches = array($translator_events);
        $max_batch_size = $deps['max_batch_size'];
        if (!empty($max_batch_size) && $max_batch_size < count($translator_events)) {
            $event_batches = array_chunk($translator_events, $max_batch_size);
        }

        /** @var elasticsearch_controller $elasticsearch_ctrl */
        $elasticsearch_ctrl = $deps['elasticsearch_controller'];
        $elasticsearch_res = [];
        foreach ($event_batches as $events_batch) {
            $statements = $elasticsearch_ctrl->create_statements($events_batch);
            $results = $elasticsearch_ctrl->send_statements($statements);
            $elasticsearch_res = array_merge($elasticsearch_res, $results);
        }

        return $elasticsearch_res;
    }

    /**
     * @param array $results results
     * @return void
     */
    public function save_unsuccessfully_indexed_events(array $results) {
        global $DB;

        if (empty($results)) {
            return;
        }

        foreach ($results as $result) {
            if (empty($result['response'])) {
                $event = $this->event_from_statement($result['statement']);
                $DB->insert_record('logstore_elasticsearch_log', $event);
            }
        }
    }

    /**
     * @param array $results results
     * @return void
     */
    public function delete_successfully_indexed_events(array $results) {
        global $DB;

        if (empty($results)) {
            return;
        }

        $to_delete = [];
        foreach ($results as $result) {
            if (!empty($result['response'])) {
                $event = $this->event_from_statement($result['statement']);
                if (array_key_exists('id', $event)) {
                    $to_delete[] = (integer)$event['id'];
                }
            }
        }

        if (!empty($to_delete)) {
            $DB->delete_records_list('logstore_elasticsearch_log', 'id', $to_delete);
        }
    }

    /**
     * @return boolean
     */
    public function is_logging() {
        return true;
    }

    /**
     * @param array $statement statement
     * @return array
     */
    public function event_from_statement(array $statement) {
        return $statement['context']['extensions'][self::EXT_KEY];
    }

    /**
     * @return Container
     */
    public function get_dependencies() {
        $container = new Container();
        $container['max_batch_size'] = $this->get_config('maxbatchsize', 0);
        $container['background_mode'] = $this->get_config('backgroundmode', false);

        $container['moodle_controller'] = function () {
            global $DB;
            global $CFG;
            $moodle_repository = new moodle_repository($DB, $CFG);
            return new moodle_controller($moodle_repository);
        };

        $container['translator_controller'] = function () {
            return new translator_controller();
        };

        $container['elasticsearch_controller'] = function () {
            global $CFG;
            $elasticsearch_client = ClientBuilder::create()->setHosts([
                $this->get_config('endpoint', 'elasticsearch'),
            ])->build();
            return new elasticsearch_controller(
                $elasticsearch_client,
                xapi_controller::$routes
            );
        };

        return $container;
    }

    /**
     * allows protected method to which this delegates to be tested
     * @param event_base $event event
     * @throws \coding_exception
     * @return boolean
     */
    public function test_is_event_ignored(event_base $event) {
        if (!PHPUNIT_TEST) {
            throw new \coding_exception('Public test method can only be invoked by PHPUnit');
        }
        return $this->is_event_ignored($event);
    }

    /**
     * allows protected method to which this delegates to be tested
     * @param array     $events events
     * @param Container $deps   dependencies
     * @throws \coding_exception
     * @return boolean
     */
    public function test_insert_event_entries(array $events, Container $deps) {
        if (!PHPUNIT_TEST) {
            throw new \coding_exception('Public test method can only be invoked by PHPUnit');
        }
        return $this->insert_event_entries($events, $deps);
    }

}

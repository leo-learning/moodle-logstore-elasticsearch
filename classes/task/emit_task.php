<?php

namespace logstore_elasticsearch\task;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../../vendor/autoload.php';

use \core\task\scheduled_task;
use \logstore_elasticsearch\log\store;
use \Pimple\Container;

class emit_task extends scheduled_task {

    /**
     * @return string
     */
    public function get_name() {
        return get_string('taskemit', 'logstore_elasticsearch');
    }

    /**
     * @param Container $deps
     */
    public function execute(Container $deps = null) {
        global $DB;

        $manager = get_log_manager();
        $store = new store($manager);

        if (empty($deps)) {
            $deps = $store->get_dependencies();
        }

        $events = $DB->get_records('logstore_elasticsearch_log');
        $results = $store->process_events($events, $deps);
        $store->delete_successfully_indexed_events($results);
    }

}

<?php

namespace logstore_elasticsearch\log;

defined('MOODLE_INTERNAL') || die;

class elasticsearch_controller {

    /**
     * @var \Elasticsearch\Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $routes;

    /**
     * @param \Elasticsearch\Client $client
     * @parma array $routes
     */
    public function __construct($client, array $routes) {
        $this->client = $client;
        $this->routes = $routes;
    }

    /**
     * @param array $events
     * @return array
     */
    public function create_statements(array $events) {
        $statements = [];
        foreach ($events as $index => $opts) {
            $route = isset($opts['recipe']) ? $opts['recipe'] : '';
            if (isset($this->routes[$route])) {
                $event = '\XREmitter\Events\\' . $this->routes[$route];
                $service = new $event();
                $opts['context_lang'] = $opts['context_lang'] ?: 'en';
                array_push($statements, $service->read($opts));
            }
        }
        return $statements;
    }

    /**
     * @param array $statements
     * @return array
     */
    public function send_statements(array $statements) {
        if (!method_exists($this->client, 'index')) {
            return [];
        }
        $responses = [];
        foreach ($statements as $statement) {
            $params = [
                'index' => 'xapi',
                'type'  => 'statement',
                'body'  => $statement,
            ];
            try {
                $responses[] = [
                    'statement' => $statement,
                    'response'  => $this->client->index($params),
                ];
            } catch (\Exception $e) {
                $responses[] = [
                    'statement' => $statement,
                    'response'  => null,
                ];
            }
        }
        return $responses;
    }

}

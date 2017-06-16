# Moodle logstore Elasticsearch

Sends Moodle events as [xAPI](http://tincanapi.com/overview/) statements to [Elasticsearch](https://www.elastic.co/products/elasticsearch) using library classes from the [xAPI logstore plugin](https://github.com/xAPI-vle/moodle-logstore_xapi).

Originally based on `v1.4.0` of the [xAPI logstore plugin](https://github.com/xAPI-vle/moodle-logstore_xapi).

## Requirements

`v2.0.0` of the [xAPI logstore plugin](https://github.com/xAPI-vle/moodle-logstore_xapi).

The [xAPI logstore plugin](https://github.com/xAPI-vle/moodle-logstore_xapi) needs to be installed, but it's not necessary to actually activate it; it can remain disabled. Only the classes in its `lib/` directory - which are autoloaded by way of `composer.json` - are required by this plugin.

## Installation

In plugin's `admin/tool/log/store/elasticsearch` directory:

```
composer install
```

## Tests

Initialize the Moodle test environment as described [here](https://docs.moodle.org/dev/PHPUnit).

Then run:

```
vendor/bin/phpunit --colors=always --testsuite logstore_elasticsearch_testsuite
```

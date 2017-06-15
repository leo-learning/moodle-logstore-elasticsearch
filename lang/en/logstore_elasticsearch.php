<?php

defined('MOODLE_INTERNAL') || die();

$string['endpoint'] = 'Endpoint';
$string['settings'] = 'General Settings';
$string['elasticsearchfieldset'] = 'Custom example fieldset';
$string['elasticsearch'] = 'Elasticsearch';
$string['password'] = 'Password';
$string['pluginadministration'] = 'Logstore Elasticsearch administration';
$string['pluginname'] = 'Logstore Elasticsearch';
$string['submit'] = 'Submit';
$string['username'] = 'Username';
$string['elasticsearchsettingstitle'] = 'Logstore Elasticsearch Settings';
$string['backgroundmode'] = 'Send statements by scheduled task?';
$string['backgroundmode_desc'] = 'This will force Moodle to send the statements to Elasticsearch in the background,
        via a cron task. This will make the process less close to real time, but will help to prevent unpredictable
        Moodle performance linked to the performance of Elasticsearch.';
$string['maxbatchsize'] = 'Maximum batch size';
$string['maxbatchsize_desc'] = 'Statements are sent to Elasticsearch in batches. This setting controls the maximum number of
        statements that will be sent in a single operation. Setting this to zero will cause all available statements to
        be sent at once, although this is not recommended.';
$string['taskemit'] = 'Emit records to Elasticsearch';

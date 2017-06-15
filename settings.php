<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(
        new admin_setting_configtext(
            'logstore_elasticsearch/endpoint',
            get_string('endpoint', 'logstore_elasticsearch'),
            '',
            'elasticsearch',
            PARAM_URL
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'logstore_elasticsearch/username',
            get_string('username', 'logstore_elasticsearch'),
            '',
            '',
            PARAM_TEXT
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'logstore_elasticsearch/password',
            get_string('password', 'logstore_elasticsearch'),
            '',
            '',
            PARAM_TEXT
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'logstore_elasticsearch/backgroundmode',
            get_string('backgroundmode', 'logstore_elasticsearch'),
            get_string('backgroundmode_desc', 'logstore_elasticsearch'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'logstore_elasticsearch/maxbatchsize',
            get_string('maxbatchsize', 'logstore_elasticsearch'),
            get_string('maxbatchsize_desc', 'logstore_elasticsearch'),
            30,
            PARAM_INT
        )
    );
}

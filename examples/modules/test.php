<?php

app()->module('/test', array(
    'get' => array(
        '' => function ($req, $res) {
            $res->send('Test Page');
        },
        '/phpinfo' => function ($req, $res) {
            phpinfo();
        },
    ),
));

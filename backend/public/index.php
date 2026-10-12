<?php

declare(strict_types=1);

/** @var Jassnet\Api\Http\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';
$app->run();

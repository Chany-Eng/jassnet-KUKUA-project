<?php

declare(strict_types=1);

use Jassnet\Api\Http\App;
use Jassnet\Api\Http\Request;

require_once __DIR__ . '/Env.php';
\Jassnet\Api\Env::load(dirname(__DIR__) . '/.env');
require_once __DIR__ . '/http/Request.php';
require_once __DIR__ . '/http/Response.php';
require_once __DIR__ . '/http/Router.php';
require_once __DIR__ . '/security/RateLimiter.php';
require_once __DIR__ . '/security/Crypto.php';
require_once __DIR__ . '/storage/Database.php';
require_once __DIR__ . '/services/OtpService.php';
require_once __DIR__ . '/http/App.php';

return new App(Request::fromGlobals());

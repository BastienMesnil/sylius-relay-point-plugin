<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// Load .env files from the TestApplication directory so that
// SYLIUS_TEST_APP_* env vars are available when the kernel boots.
$dotenv = new Dotenv();
$dotenv->loadEnv(dirname(__DIR__) . '/tests/TestApplication/.env');

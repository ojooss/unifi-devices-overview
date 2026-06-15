<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// Load .env then .env.test (APP_ENV=test overrides DATABASE_URL etc.)
(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

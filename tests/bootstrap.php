<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Load .env then .env.test (APP_ENV=test overrides DATABASE_URL etc.)
new Dotenv()->bootEnv(__DIR__ . '/../.env');

<?php

// Set isolation before Laravel or Dotenv can load database settings or config cache.
$isolatedEnvironment = [
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('m', 32)),
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/prime-mcp-no-config-'.bin2hex(random_bytes(12)).'.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DATABASE_URL' => '',
    'CACHE_STORE' => 'array',
    'CACHE_DRIVER' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'LOG_CHANNEL' => 'null',
    'BCRYPT_ROUNDS' => '4',
];

foreach ($isolatedEnvironment as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';

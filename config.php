<?php
declare(strict_types=1);

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'YOUR_DATABASE_NAME');
define('DB_USER', getenv('DB_USER') ?: 'YOUR_DATABASE_USER');
define('DB_PASS', getenv('DB_PASS') ?: 'YOUR_DATABASE_PASSWORD');

define('SITE_NAME', 'Earn On Venture');
define('SITE_URL', rtrim(getenv('SITE_URL') ?: 'https://earnonventure.com', '/'));
define('ROOT_PATH', __DIR__);
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_MAX_BYTES', 3 * 1024 * 1024);

date_default_timezone_set('UTC');

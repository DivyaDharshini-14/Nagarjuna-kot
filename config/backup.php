<?php

return [
    'enabled' => env('DB_BACKUP_ENABLED', false),
    'time' => env('DB_BACKUP_TIME', '03:00'),
    'directory' => env('DB_BACKUP_DIRECTORY', storage_path('app/private/backups')),
    'mysql_binary' => env('DB_BACKUP_MYSQL_BINARY', 'mysqldump'),
    'timeout' => (int) env('DB_BACKUP_TIMEOUT', 600),
];

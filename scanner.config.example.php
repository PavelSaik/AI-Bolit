<?php

/**
 * Скопируйте этот файл в scanner.config.php и задайте свои значения.
 * Файл scanner.config.php не должен попадать в git.
 */
return [
    /** Абсолютный или относительный путь к корню сканируемого каталога */
    'root_dir' => __DIR__ . '/public_html',

    'db_name'     => 'scanner',
    'db_user'     => 'scanner',
    'db_password' => 'ЗАМЕНИТЕ_НА_СЛОЖНЫЙ_ПАРОЛЬ',
    'db_host'     => '127.0.0.1',

    /** Список подкаталогов относительно root_dir через запятую */
    'excluded_dirs' => 'tmp,cache,logs',

    /** 0 — без ограничения глубины */
    'depth_level' => 0,

    'files_extension' => 'php,js,html',

    'dirs_table_name'  => 'scanner_dirs',
    'files_table_name' => 'scanner_files',

    /** Пустая строка — не слать отчёт по почте */
    'email' => '',

    'smtp_username' => '',
    'smtp_port'     => 25,
    'smtp_host'     => '',
    'smtp_password' => '',
    'smtp_debug'    => false,
    'smtp_charset'  => 'utf-8',
    'smtp_from'     => 'Сканер файлов',

    'email_subject' => 'Изменения на сайте',
];

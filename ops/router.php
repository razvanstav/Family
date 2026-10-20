<?php

// Development server: only compiled assets may bypass the application.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$public = realpath(__DIR__.'/../backend/public');
$file = realpath($public.$path);
if (str_starts_with($path, '/app/assets/') && $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $public.'/index.php';
require $public.'/index.php';

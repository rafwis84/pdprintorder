<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'PdPrintOrder\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

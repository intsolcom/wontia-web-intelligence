<?php
/**
 * Autoloader de recuperación (PSR-4 App\ -> src/).
 * No hay dependencias externas en composer.json, por lo que basta con esto.
 */
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) === 0) {
        $file = '/app/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

<?php

// No application bootstrap or database access: this only checks CLI capabilities.
if (PHP_VERSION_ID < 80300
    || ! extension_loaded('mbstring')
    || ! extension_loaded('pdo_sqlite')
    || ! function_exists('imagejpeg')
    || ! function_exists('imagewebp')) {
    exit(1);
}

echo PHP_BINARY;

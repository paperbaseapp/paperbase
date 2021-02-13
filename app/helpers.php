<?php

if (!function_exists('join_path')) {
    function join_path(string $a, string $b): string
    {
        return join('/', [rtrim($a, '/'), rtrim($b, '/')]);
    }
}

<?php
/**
 * Shared helper: Wenn eine Datei per CLI als root geschrieben wird, muss der
 * Owner auf www-data gesetzt werden, damit Apache sie lesen/schreiben kann.
 * Aufruf ist idempotent & ein No-Op, wenn der laufende Prozess nicht root
 * ist oder die posix-Extension fehlt.
 */

function chownToApacheIfRoot(string $path): void
{
    if (!file_exists($path)) return;
    if (!function_exists('posix_getuid') || posix_getuid() !== 0) return;
    if (!function_exists('posix_getpwnam') || !posix_getpwnam('www-data')) return;
    @chown($path, 'www-data');
    @chgrp($path, 'www-data');
}

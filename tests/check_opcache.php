<?php
echo 'opcache_enabled=' . var_export(function_exists('opcache_get_status') && opcache_get_status() !== false, true)."\n";
echo 'validate_timestamps=' . var_export(ini_get('opcache.validate_timestamps'), true)."\n;";
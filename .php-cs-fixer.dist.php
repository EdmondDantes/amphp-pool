<?php
declare(strict_types=1);

$config = new Amp\CodeStyle\Config;
$config->getFinder()
       ->in(__DIR__ . '/examples')
       ->in(__DIR__ . '/src')
       ->in(__DIR__ . '/tests');

return $config;
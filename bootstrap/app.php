<?php

declare(strict_types=1);

use Vortex\Application;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\View\View;

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

return Application::boot($basePath, static function (Container $container, string $basePath): void {


})->container();

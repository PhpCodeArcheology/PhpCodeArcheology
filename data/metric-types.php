<?php

declare(strict_types=1);

return array_merge(
    require __DIR__ . '/metrics/project.php',
    require __DIR__ . '/metrics/file.php',
    require __DIR__ . '/metrics/class.php',
    require __DIR__ . '/metrics/method.php',
    require __DIR__ . '/metrics/function.php',
    require __DIR__ . '/metrics/package.php',
);

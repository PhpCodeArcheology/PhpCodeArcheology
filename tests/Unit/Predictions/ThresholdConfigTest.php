<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\PredictionTrait;
use PhpCodeArch\Predictions\TooLongPrediction;
use PhpCodeArch\Predictions\TooManyParametersPrediction;

class ThresholdTestClass
{
    use PredictionTrait;

    public function __construct(?Config $config = null)
    {
        $this->config = $config;
    }

    public function getThreshold(string $key, mixed $default): mixed
    {
        return $this->threshold($key, $default);
    }
}

it('threshold returns default when no config set', function () {
    $obj = new ThresholdTestClass(null);

    expect($obj->getThreshold('tooLong.file', 400))->toBe(400);
});

it('threshold returns default when key not found in config', function () {
    $config = new Config();
    $config->set('thresholds', ['other' => 100]);

    $obj = new ThresholdTestClass($config);

    expect($obj->getThreshold('missing.key', 42))->toBe(42);
});

it('threshold returns configured value when set', function () {
    $config = new Config();
    $config->set('thresholds', ['maxLines' => 500]);

    $obj = new ThresholdTestClass($config);

    expect($obj->getThreshold('maxLines', 100))->toBe(500);
});

it('threshold navigates nested keys', function () {
    $config = new Config();
    $config->set('thresholds', [
        'tooLong' => [
            'file' => 600,
            'class' => 400,
            'function' => 50,
        ],
    ]);

    $obj = new ThresholdTestClass($config);

    expect($obj->getThreshold('tooLong.file', 400))->toBe(600)
        ->and($obj->getThreshold('tooLong.class', 300))->toBe(400)
        ->and($obj->getThreshold('tooLong.function', 40))->toBe(50);
});

it('threshold returns default when thresholds is not an array', function () {
    $config = new Config();
    $config->set('thresholds', 'invalid');

    $obj = new ThresholdTestClass($config);

    expect($obj->getThreshold('anything', 99))->toBe(99);
});

it('threshold returns default for partially matching nested key', function () {
    $config = new Config();
    $config->set('thresholds', [
        'tooLong' => [
            'file' => 600,
        ],
    ]);

    $obj = new ThresholdTestClass($config);

    expect($obj->getThreshold('tooLong.method', 30))->toBe(30);
});

it('TooLongPrediction uses configured thresholds', function () {
    $config = new Config();
    $config->set('thresholds', [
        'tooLong' => [
            'file' => 800,
            'class' => 500,
            'function' => 60,
            'method' => 50,
        ],
    ]);

    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $prediction = new TooLongPrediction($controller, $controller, $controller, $config);

    // Use reflection to access the protected threshold() method
    $reflection = new ReflectionMethod($prediction, 'threshold');

    expect($reflection->invoke($prediction, 'tooLong.file', 400))->toBe(800)
        ->and($reflection->invoke($prediction, 'tooLong.class', 300))->toBe(500)
        ->and($reflection->invoke($prediction, 'tooLong.function', 40))->toBe(60)
        ->and($reflection->invoke($prediction, 'tooLong.method', 30))->toBe(50);
});

it('TooManyParametersPrediction uses configured thresholds', function () {
    $config = new Config();
    $config->set('thresholds', [
        'tooManyParameters' => [
            'warning' => 5,
            'error' => 10,
        ],
    ]);

    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $prediction = new TooManyParametersPrediction($controller, $controller, $controller, $config);

    $reflection = new ReflectionMethod($prediction, 'threshold');

    expect($reflection->invoke($prediction, 'tooManyParameters.warning', 4))->toBe(5)
        ->and($reflection->invoke($prediction, 'tooManyParameters.error', 7))->toBe(10);
});

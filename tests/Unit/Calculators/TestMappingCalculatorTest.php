<?php

declare(strict_types=1);

use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Application\Service\PhpunitConfigResult;
use PhpCodeArch\Application\Service\TestCoversParseResult;
use PhpCodeArch\Application\Service\TestScanResult;
use PhpCodeArch\Calculators\TestMappingCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsContainer;

beforeEach(function () {
    $this->container = new MetricsContainer();
    $this->controller = new MetricsController($this->container);
    $this->controller->registerMetricTypes();
    $this->controller->createProjectMetricsCollection(['/src']);
});

function createProdClass(
    MetricsController $controller,
    string $name,
    string $path = '',
    array $flags = [],
): string {
    if ('' === $path) {
        $path = '/src/'.str_replace('\\', '/', $name).'.php';
    }

    $controller->createMetricCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['name' => $name, 'path' => $path]
    );

    $classId = $controller->getMetricCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['name' => $name, 'path' => $path]
    )->getIdentifier()->__toString();

    $defaults = [
        'filePath' => $path,
        'interface' => false,
        'trait' => false,
        'enum' => false,
        'abstract' => false,
    ];

    $controller->setMetricValuesByIdentifierString($classId, array_merge($defaults, $flags));

    return $classId;
}

function getMappingProjectMetric(MetricsController $controller, string $key): mixed
{
    return $controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    )->get($key)?->getValue();
}

function getClassHasTest(MetricsController $controller, string $classId): mixed
{
    return $controller->getMetricCollectionByIdentifierString($classId)->get('hasTest')?->getValue();
}

function getClassTestFileCount(MetricsController $controller, string $classId): mixed
{
    return $controller->getMetricCollectionByIdentifierString($classId)->get('testFileCount')?->getValue();
}

it('sets zero project metrics when no test files exist', function () {
    $calculator = new TestMappingCalculator($this->controller);
    $calculator->afterTraverse();

    expect(getMappingProjectMetric($this->controller, 'overallTestFileCount'))->toBe(0)
        ->and(getMappingProjectMetric($this->controller, 'overallTestRatio'))->toBe(0.0)
        ->and(getMappingProjectMetric($this->controller, 'overallTestedClassCount'))->toBe(0)
        ->and(getMappingProjectMetric($this->controller, 'overallUntestedClassCount'))->toBe(0);
});

it('maps test file to production class by naming convention', function () {
    $userServiceId = createProdClass($this->controller, 'UserService');

    $testScan = new TestScanResult(
        testDirectories: ['/tests'],
        classBasedTestFiles: ['/tests/UserServiceTest.php'],
        testFileToType: ['/tests/UserServiceTest.php' => 'unit'],
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
    );
    $calculator->afterTraverse();

    expect(getClassHasTest($this->controller, $userServiceId))->toBeTrue();
});

it('maps test file to production class via PSR-4 namespace', function () {
    $classId = createProdClass(
        $this->controller,
        'App\\Services\\UserService',
        '/src/Services/UserService.php',
    );

    $frameworkDetection = new FrameworkDetectionResult(
        pestDetected: true,
        psr4Autoload: ['App\\' => 'src'],
        psr4AutoloadDev: ['Tests\\' => 'tests'],
    );

    $testScan = new TestScanResult(
        testDirectories: ['/project/tests'],
        classBasedTestFiles: ['/project/tests/Unit/Services/UserServiceTest.php'],
        testFileToType: ['/project/tests/Unit/Services/UserServiceTest.php' => 'unit'],
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        frameworkDetection: $frameworkDetection,
        testScanResult: $testScan,
    );
    $calculator->afterTraverse();

    expect(getClassHasTest($this->controller, $classId))->toBeTrue();
});

it('sets hasTest=true on matched class and hasTest=false on unmatched class', function () {
    $matchedId = createProdClass($this->controller, 'MatchedService');
    $unmatchedId = createProdClass($this->controller, 'UnmatchedService');

    $testScan = new TestScanResult(
        testDirectories: ['/tests'],
        classBasedTestFiles: ['/tests/MatchedServiceTest.php'],
        testFileToType: ['/tests/MatchedServiceTest.php' => 'unit'],
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
    );
    $calculator->afterTraverse();

    expect(getClassHasTest($this->controller, $matchedId))->toBeTrue()
        ->and(getClassHasTest($this->controller, $unmatchedId))->toBeFalse();
});

it('counts testFileCount correctly when multiple tests cover one class via @covers', function () {
    $classId = createProdClass($this->controller, 'App\\Services\\UserService');

    $testScan = new TestScanResult(
        testDirectories: ['/tests'],
        classBasedTestFiles: [
            '/tests/UserServiceUnitTest.php',
            '/tests/UserServiceIntegrationTest.php',
        ],
        testFileToType: [
            '/tests/UserServiceUnitTest.php' => 'unit',
            '/tests/UserServiceIntegrationTest.php' => 'integration',
        ],
    );

    $covers = new TestCoversParseResult(coversMap: [
        '/tests/UserServiceUnitTest.php' => ['App\\Services\\UserService'],
        '/tests/UserServiceIntegrationTest.php' => ['App\\Services\\UserService'],
    ]);

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
        coversParseResult: $covers,
    );
    $calculator->afterTraverse();

    expect(getClassTestFileCount($this->controller, $classId))->toBe(2);
});

it('resolves @covers annotations to production classes', function () {
    $classId = createProdClass($this->controller, 'App\\Services\\UserService');

    $testScan = new TestScanResult(
        testDirectories: ['/tests'],
        classBasedTestFiles: ['/tests/SomeArbitraryTest.php'],
        testFileToType: ['/tests/SomeArbitraryTest.php' => 'unit'],
    );

    $covers = new TestCoversParseResult(coversMap: [
        '/tests/SomeArbitraryTest.php' => ['App\\Services\\UserService'],
    ]);

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
        coversParseResult: $covers,
    );
    $calculator->afterTraverse();

    expect(getClassHasTest($this->controller, $classId))->toBeTrue();
});

it('resolves multiple classes from @covers', function () {
    $userServiceId = createProdClass($this->controller, 'App\\Services\\UserService');
    $orderServiceId = createProdClass($this->controller, 'App\\Services\\OrderService');

    $testScan = new TestScanResult(
        testDirectories: ['/tests'],
        classBasedTestFiles: ['/tests/CheckoutFlowTest.php'],
        testFileToType: ['/tests/CheckoutFlowTest.php' => 'unit'],
    );

    $covers = new TestCoversParseResult(coversMap: [
        '/tests/CheckoutFlowTest.php' => [
            'App\\Services\\UserService',
            'App\\Services\\OrderService',
        ],
    ]);

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
        coversParseResult: $covers,
    );
    $calculator->afterTraverse();

    expect(getClassHasTest($this->controller, $userServiceId))->toBeTrue()
        ->and(getClassHasTest($this->controller, $orderServiceId))->toBeTrue();
});

it('prefers @covers over naming convention', function () {
    $userServiceId = createProdClass($this->controller, 'UserService');
    $otherServiceId = createProdClass($this->controller, 'OtherService');

    // Test file name matches UserService by convention, but @covers points to OtherService
    $testScan = new TestScanResult(
        testDirectories: ['/tests'],
        classBasedTestFiles: ['/tests/UserServiceTest.php'],
        testFileToType: ['/tests/UserServiceTest.php' => 'unit'],
    );

    $covers = new TestCoversParseResult(coversMap: [
        '/tests/UserServiceTest.php' => ['OtherService'],
    ]);

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
        coversParseResult: $covers,
    );
    $calculator->afterTraverse();

    expect(getClassHasTest($this->controller, $otherServiceId))->toBeTrue()
        ->and(getClassHasTest($this->controller, $userServiceId))->toBeFalse();
});

it('uses use-statements for integration tests without @covers', function () {
    $classId = createProdClass($this->controller, 'App\\Services\\UserService');

    $testScan = new TestScanResult(
        testDirectories: ['/tests'],
        classBasedTestFiles: ['/tests/UserFeatureTest.php'],
        testFileToType: ['/tests/UserFeatureTest.php' => 'integration'],
    );

    $covers = new TestCoversParseResult(
        coversMap: [],
        useStatementsMap: [
            '/tests/UserFeatureTest.php' => ['App\\Services\\UserService'],
        ],
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
        coversParseResult: $covers,
    );
    $calculator->afterTraverse();

    expect(getClassHasTest($this->controller, $classId))->toBeTrue();
});

it('excludes test classes from production index', function () {
    // Class whose filePath matches a path in classBasedTestFiles
    createProdClass(
        $this->controller,
        'UserServiceTest',
        '/tests/UserServiceTest.php',
    );

    $testScan = new TestScanResult(
        testDirectories: ['/tests'],
        classBasedTestFiles: ['/tests/UserServiceTest.php'],
        testFileToType: ['/tests/UserServiceTest.php' => 'unit'],
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
    );
    $calculator->afterTraverse();

    expect(getMappingProjectMetric($this->controller, 'overallProductionFileCount'))->toBe(0);
});

it('does not count abstract classes as untested', function () {
    createProdClass($this->controller, 'AbstractBaseService', flags: ['abstract' => true]);
    createProdClass($this->controller, 'ConcreteService');

    $testScan = new TestScanResult(
        testDirectories: ['/tests'],
        classBasedTestFiles: ['/tests/ConcreteServiceTest.php'],
        testFileToType: ['/tests/ConcreteServiceTest.php' => 'unit'],
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
    );
    $calculator->afterTraverse();

    // ConcreteService is tested; AbstractBaseService is NOT counted as untested
    expect(getMappingProjectMetric($this->controller, 'overallUntestedClassCount'))->toBe(0)
        ->and(getMappingProjectMetric($this->controller, 'overallTestedClassCount'))->toBe(1);
});

it('calculates project-level aggregates correctly', function () {
    createProdClass($this->controller, 'UserService');    // will be tested
    createProdClass($this->controller, 'OrderService');   // untested concrete
    createProdClass($this->controller, 'AbstractBase', flags: ['abstract' => true]); // abstract: not untested

    $testScan = new TestScanResult(
        testDirectories: ['/tests'],
        classBasedTestFiles: ['/tests/UserServiceTest.php'],
        testFileToType: ['/tests/UserServiceTest.php' => 'unit'],
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
    );
    $calculator->afterTraverse();

    expect(getMappingProjectMetric($this->controller, 'overallProductionFileCount'))->toBe(3)
        ->and(getMappingProjectMetric($this->controller, 'overallTestFileCount'))->toBe(1)
        ->and(getMappingProjectMetric($this->controller, 'overallTestedClassCount'))->toBe(1)
        ->and(getMappingProjectMetric($this->controller, 'overallUntestedClassCount'))->toBe(1)
        ->and(getMappingProjectMetric($this->controller, 'overallTestedClassRatio'))->toBe(33.33);
});

// ---------------------------------------------------------------------------
// phpunit.xml <source> scope exclusion
// ---------------------------------------------------------------------------

it('tags classes outside <source> scope with EXCLUDED_BY_PHPUNIT_SOURCE', function () {
    $fixturePath = '/project/src/DataFixtures/UserFixtures.php';
    $fixtureId = createProdClass($this->controller, 'App\\DataFixtures\\UserFixtures', $fixturePath);

    $phpunitConfig = new PhpunitConfigResult(
        found: true,
        configFilePath: '/project/phpunit.xml',
        sourceIncludeDirectories: ['/project/src'],
        sourceExcludeDirectories: ['/project/src/DataFixtures'],
    );

    $testScan = new TestScanResult(
        testDirectories: ['/project/tests'],
        classBasedTestFiles: ['/project/tests/SomeTest.php'],
        testFileToType: ['/project/tests/SomeTest.php' => 'unit'],
        phpunitConfig: $phpunitConfig,
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
    );
    $calculator->afterTraverse();

    $excluded = $this->controller
        ->getMetricCollectionByIdentifierString($fixtureId)
        ->get('excludedByPhpunitSource')
        ?->getValue();

    expect($excluded)->toBeTrue();
});

it('does not count source-excluded classes as production or untested', function () {
    createProdClass($this->controller, 'App\\Services\\UserService', '/project/src/Services/UserService.php');
    createProdClass($this->controller, 'App\\DataFixtures\\UserFixtures', '/project/src/DataFixtures/UserFixtures.php');
    createProdClass($this->controller, 'App\\Kernel', '/project/src/Kernel.php');

    $phpunitConfig = new PhpunitConfigResult(
        found: true,
        configFilePath: '/project/phpunit.xml',
        sourceIncludeDirectories: ['/project/src'],
        sourceExcludeDirectories: ['/project/src/DataFixtures'],
        sourceExcludeFiles: ['/project/src/Kernel.php'],
    );

    $testScan = new TestScanResult(
        testDirectories: ['/project/tests'],
        classBasedTestFiles: ['/project/tests/SomeTest.php'],
        testFileToType: ['/project/tests/SomeTest.php' => 'unit'],
        phpunitConfig: $phpunitConfig,
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
    );
    $calculator->afterTraverse();

    // Only UserService remains in the production set
    expect(getMappingProjectMetric($this->controller, 'overallProductionFileCount'))->toBe(1)
        ->and(getMappingProjectMetric($this->controller, 'overallTestedClassCount'))->toBe(0)
        ->and(getMappingProjectMetric($this->controller, 'overallUntestedClassCount'))->toBe(1)
        ->and(getMappingProjectMetric($this->controller, 'overallSourceExcludedClassCount'))->toBe(2);
});

it('behaves unchanged when testScanResult->phpunitConfig is null', function () {
    createProdClass($this->controller, 'App\\Services\\UserService', '/project/src/Services/UserService.php');
    createProdClass($this->controller, 'App\\DataFixtures\\UserFixtures', '/project/src/DataFixtures/UserFixtures.php');

    $testScan = new TestScanResult(
        testDirectories: ['/project/tests'],
        classBasedTestFiles: ['/project/tests/SomeTest.php'],
        testFileToType: ['/project/tests/SomeTest.php' => 'unit'],
        phpunitConfig: null,
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
    );
    $calculator->afterTraverse();

    // Both classes still counted as production (nothing excluded)
    expect(getMappingProjectMetric($this->controller, 'overallProductionFileCount'))->toBe(2)
        ->and(getMappingProjectMetric($this->controller, 'overallSourceExcludedClassCount'))->toBe(0);
});

it('behaves unchanged when phpunitConfig has no <source> scope', function () {
    createProdClass($this->controller, 'App\\Services\\UserService', '/project/src/Services/UserService.php');
    createProdClass($this->controller, 'App\\DataFixtures\\UserFixtures', '/project/src/DataFixtures/UserFixtures.php');

    $phpunitConfig = new PhpunitConfigResult(
        found: true,
        configFilePath: '/project/phpunit.xml',
    );

    $testScan = new TestScanResult(
        testDirectories: ['/project/tests'],
        classBasedTestFiles: ['/project/tests/SomeTest.php'],
        testFileToType: ['/project/tests/SomeTest.php' => 'unit'],
        phpunitConfig: $phpunitConfig,
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
    );
    $calculator->afterTraverse();

    expect(getMappingProjectMetric($this->controller, 'overallProductionFileCount'))->toBe(2)
        ->and(getMappingProjectMetric($this->controller, 'overallSourceExcludedClassCount'))->toBe(0);
});

it('uses the absolute class path, not the common-path-stripped FILE_PATH metric', function () {
    // Simulate the post-FileCalculator state: FILE_PATH metric has been stripped to
    // a relative path, while the collection's getPath() still returns the absolute one.
    // Regression guard: isInSourceScope() must be matched against getPath(), not FILE_PATH.
    $serviceId = createProdClass(
        $this->controller,
        'App\\Services\\UserService',
        '/project/src/Services/UserService.php',
    );
    $fixtureId = createProdClass(
        $this->controller,
        'App\\DataFixtures\\UserFixtures',
        '/project/src/DataFixtures/UserFixtures.php',
    );

    // Overwrite the FILE_PATH metric with the common-path-stripped form FileCalculator
    // would produce. The collection's path (used internally) stays absolute.
    $this->controller->setMetricValuesByIdentifierString($serviceId, [
        'filePath' => 'Services/UserService.php',
    ]);
    $this->controller->setMetricValuesByIdentifierString($fixtureId, [
        'filePath' => 'DataFixtures/UserFixtures.php',
    ]);

    $phpunitConfig = new PhpunitConfigResult(
        found: true,
        configFilePath: '/project/phpunit.xml',
        sourceIncludeDirectories: ['/project/src'],
        sourceExcludeDirectories: ['/project/src/DataFixtures'],
    );

    $testScan = new TestScanResult(
        testDirectories: ['/project/tests'],
        classBasedTestFiles: ['/project/tests/SomeTest.php'],
        testFileToType: ['/project/tests/SomeTest.php' => 'unit'],
        phpunitConfig: $phpunitConfig,
    );

    $calculator = new TestMappingCalculator(
        metricsController: $this->controller,
        testScanResult: $testScan,
    );
    $calculator->afterTraverse();

    // UserService stays in production (under /project/src, not excluded).
    // UserFixtures is excluded (under /project/src/DataFixtures).
    expect(getMappingProjectMetric($this->controller, 'overallProductionFileCount'))->toBe(1)
        ->and(getMappingProjectMetric($this->controller, 'overallSourceExcludedClassCount'))->toBe(1);
});

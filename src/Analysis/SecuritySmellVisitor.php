<?php

declare(strict_types=1);

namespace PhpCodeArch\Analysis;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpParser\Node;
use PhpParser\NodeVisitor;

class SecuritySmellVisitor implements NodeVisitor, VisitorInterface
{
    use VisitorTrait;

    private const DANGEROUS_FUNCTIONS = [
        'eval' => 'error',
        'exec' => 'error',
        'system' => 'error',
        'shell_exec' => 'error',
        'passthru' => 'error',
        'proc_open' => 'error',
        'popen' => 'error',
        'pcntl_exec' => 'error',
    ];

    private const WEAK_HASH_FUNCTIONS = ['md5', 'sha1'];

    private const SQL_KEYWORDS = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE'];

    private array $currentClassName = [];
    private array $currentFunctionName = [];

    private array $fileSmells = [];
    private array $classSmells = [];
    private array $functionSmells = [];

    public function beforeTraverse(array $nodes): void
    {
        $this->currentClassName = [];
        $this->currentFunctionName = [];
        $this->fileSmells = [];
        $this->classSmells = [];
        $this->functionSmells = [];
    }

    public function enterNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = ClassName::ofNode($node)->__toString();
                $this->currentClassName[] = $className;
                $this->classSmells[$className] = [];
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $className = end($this->currentClassName);
                $key = $className . '::' . (string) $node->name;
                $this->currentFunctionName[] = $key;
                $this->functionSmells[$key] = [];
                break;

            case $node instanceof Node\Stmt\Function_:
                $key = (string) $node->namespacedName;
                $this->currentFunctionName[] = $key;
                $this->functionSmells[$key] = [];
                break;

            case $node instanceof Node\Expr\FuncCall:
                $this->checkFunctionCall($node);
                break;

            case $node instanceof Node\Expr\BinaryOp\Concat:
                $this->checkSqlConcat($node);
                break;
        }
    }

    public function leaveNode(Node $node): void
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
            case $node instanceof Node\Stmt\Interface_:
            case $node instanceof Node\Stmt\Trait_:
            case $node instanceof Node\Stmt\Enum_:
                $className = array_pop($this->currentClassName);
                $this->metricsController->setMetricValues(
                    MetricCollectionTypeEnum::ClassCollection,
                    ['path' => $this->path, 'name' => $className],
                    [
                        'securitySmellCount' => count($this->classSmells[$className]),
                        'securitySmells' => $this->classSmells[$className],
                    ]
                );
                break;

            case $node instanceof Node\Stmt\ClassMethod:
                $key = array_pop($this->currentFunctionName);
                $parts = explode('::', $key, 2);
                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::MethodCollection,
                    ['path' => $parts[0], 'name' => $parts[1]],
                    count($this->functionSmells[$key]),
                    'securitySmellCount'
                );
                break;

            case $node instanceof Node\Stmt\Function_:
                $key = array_pop($this->currentFunctionName);
                $this->metricsController->setMetricValue(
                    MetricCollectionTypeEnum::FunctionCollection,
                    ['path' => $this->path, 'name' => $key],
                    count($this->functionSmells[$key]),
                    'securitySmellCount'
                );
                break;
        }
    }

    public function afterTraverse(array $nodes): void
    {
        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => $this->path],
            [
                'securitySmellCount' => count($this->fileSmells),
                'securitySmells' => $this->fileSmells,
            ]
        );
    }

    private function addSmell(string $description): void
    {
        $this->fileSmells[] = $description;

        if (count($this->currentClassName) > 0) {
            $className = end($this->currentClassName);
            $this->classSmells[$className][] = $description;
        }

        if (count($this->currentFunctionName) > 0) {
            $funcKey = end($this->currentFunctionName);
            $this->functionSmells[$funcKey][] = $description;
        }
    }

    private function checkFunctionCall(Node\Expr\FuncCall $node): void
    {
        if (!$node->name instanceof Node\Name) {
            return;
        }

        $funcName = strtolower($node->name->toString());
        $line = $node->getLine();

        // Dangerous functions (eval, exec, system, etc.)
        if (isset(self::DANGEROUS_FUNCTIONS[$funcName])) {
            $this->addSmell("{$funcName}() call on line {$line}");
            return;
        }

        // Weak hashing
        if (in_array($funcName, self::WEAK_HASH_FUNCTIONS)) {
            $this->addSmell("Weak hash {$funcName}() on line {$line}");
            return;
        }

        // unserialize without allowed_classes
        if ($funcName === 'unserialize') {
            $hasAllowedClasses = false;
            if (count($node->args) >= 2) {
                $secondArg = $node->args[1]->value ?? null;
                if ($secondArg instanceof Node\Expr\Array_) {
                    foreach ($secondArg->items as $item) {
                        if ($item?->key instanceof Node\Scalar\String_ && $item->key->value === 'allowed_classes') {
                            $hasAllowedClasses = true;
                        }
                    }
                }
            }
            if (!$hasAllowedClasses) {
                $this->addSmell("unserialize() without allowed_classes on line {$line}");
            }
        }
    }

    private function checkSqlConcat(Node\Expr\BinaryOp\Concat $node): void
    {
        $leftStr = $this->extractStringValue($node->left);
        if ($leftStr === null) {
            return;
        }

        $upper = strtoupper(trim($leftStr));
        foreach (self::SQL_KEYWORDS as $keyword) {
            if (str_starts_with($upper, $keyword)) {
                $line = $node->getLine();
                $this->addSmell("SQL string concatenation on line {$line}");
                return;
            }
        }
    }

    private function extractStringValue(Node $node): ?string
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }
        if ($node instanceof Node\Scalar\InterpolatedString) {
            return null; // Can't reliably extract
        }
        // Check left side of nested concat
        if ($node instanceof Node\Expr\BinaryOp\Concat) {
            return $this->extractStringValue($node->left);
        }
        return null;
    }
}

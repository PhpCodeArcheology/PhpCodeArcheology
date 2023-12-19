<?php

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\FileIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionAndClassIdentifier;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\PrettyPrinter;

class LocVisitor implements NodeVisitor
{
    private array $functionNodes = [];

    private bool $inFunction = false;

    private string $path;

    private FileMetrics $fileMetrics;

    private int $insideLloc = 0;

    public function __construct(private Metrics $metrics)
    {
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes): void
    {
        $fileId = FileIdentifier::ofPath($this->path);
        $this->fileMetrics = $this->metrics->get((string) $fileId);

        $this->insideLloc = 0;

        $lastNode = $nodes[array_key_last($nodes)];
        $loc = $lastNode->getEndLine();

        $prettyPrinter = new PrettyPrinter\Standard();
        $code = $prettyPrinter->prettyPrint($nodes);

        [$cloc, $lloc] = $this->getClocAndLloc($code);

        $this->fileMetrics->set('loc', $loc);
        $this->fileMetrics->set('cloc', $cloc);
        $this->fileMetrics->set('lloc', $lloc);
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Function_) {
            $this->inFunction = true;
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Function_) {
            $this->inFunction = false;

            $this->getLinesOfCodeFunction($node);

            $this->functionNodes = [];
        }
        elseif ($node instanceof Node\Stmt\Class_) {
            $classId = (string) FunctionAndClassIdentifier::ofNameAndPath($this->path, $node->namespacedName);
            $classMetrics = $this->metrics->get($classId);

            $loc = $node->getEndLine() - $node->getStartLine() + 1;

            $prettyPrinter = new PrettyPrinter\Standard();
            $classCode = $prettyPrinter->prettyPrint([$node]);
            [$cloc, $lloc] = $this->getClocAndLloc($classCode);

            $methods = $classMetrics->get('methods');

            foreach ($node->stmts as $stmt) {
                if (! $stmt instanceof Node\Stmt\ClassMethod) {
                    continue;
                }

                $methodId = (string) FunctionAndClassIdentifier::ofNameAndPath($stmt->name, '');
                $methodMetrics = $methods[$methodId];

                $methodLoc = 0;
                $methodCloc = 0;
                $methodLloc = 0;
                if ($stmt->stmts && count($stmt->stmts) > 0) {
                    $firstMethodStmt = $stmt->stmts[0];
                    $lastMethodStmt = $stmt->stmts[array_key_last($stmt->stmts)];

                    $methodLoc = $lastMethodStmt->getEndLine() - $firstMethodStmt->getStartLine() + 1;

                    $methodBodyCode = $prettyPrinter->prettyPrint($stmt->stmts);
                    [$methodCloc, $methodLloc] = $this->getClocAndLloc($methodBodyCode);
                }

                $methodMetrics->set('loc', $methodLoc);
                $methodMetrics->set('cloc', $methodCloc);
                $methodMetrics->set('lloc', $methodLloc);
                $methods[$methodId] = $methodMetrics;
            }

            $classMetrics->set('loc', $loc);
            $classMetrics->set('cloc', $cloc);
            $classMetrics->set('lloc', $lloc);
            $classMetrics->set('methods', $methods);
            $this->metrics->push($classMetrics);

            $this->insideLloc += $lloc;
        }
        elseif ($this->inFunction && str_starts_with($node->getType(), 'Stmt_')) {
            $this->functionNodes[] = $node;
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes): void
    {
        $fileId = (string) $this->fileMetrics->getIdentifier();

        $llocFile = $this->fileMetrics->get('lloc');
        $llocFileOutside = $llocFile - $this->insideLloc;
        $this->fileMetrics->set('llocOutside', $llocFileOutside);

        $this->metrics->set($fileId, $this->fileMetrics);
    }

    private function getLinesOfCodeFunction(Node $node): void
    {
        $loc = $node->getEndLine() - $node->getStartLine() + 1;

        // Get cloc and lloc from function body
        $prettyPrinter = new PrettyPrinter\Standard();
        $functionBodyCode = $prettyPrinter->prettyPrint($this->functionNodes);
        [$cloc, $lloc] = $this->getClocAndLloc($functionBodyCode);

        $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath($this->path, $node->namespacedName);

        $functionMetrics = $this->metrics->get($functionId);
        $functionMetrics->set('loc', $loc);
        $functionMetrics->set('cloc', $cloc);
        $functionMetrics->set('lloc', $lloc);
        $this->metrics->push($functionMetrics);

        // Get lloc of whole function (with function declaration and ending curly brackets
        $wholeFunction = $prettyPrinter->prettyPrint([$node]);
        [$wCloc, $wLloc] = $this->getClocAndLloc($wholeFunction);
        $this->insideLloc += $wLloc;
    }

    private function getClocAndLloc(string $code): array
    {
        $cloc = 0;

        // count and remove multi lines comments
        if (preg_match_all('!/\*.*?\*/!s', $code, $matches)) {
            foreach ($matches[0] as $match) {
                $cloc += max(1, count(preg_split('/\r\n|\r|\n/', $match)));
            }
        }
        $code = preg_replace('!/\*.*?\*/!s', '', $code);

        // count and remove single line comments
        $code = preg_replace_callback('!(\'[^\']*\'|"[^"]*")|((?:#|\/\/).*$)!m', function (array $matches) use (&$cloc) {
            if (isset($matches[2])) {
                $cloc += 1;
            }
            return $matches[1];
        }, $code, -1);

        $code = trim(preg_replace('!(^\s*[\r\n])!sm', '', $code));

        $lloc = count(preg_split('/\r\n|\r|\n/', $code));

        return [$cloc, $lloc];
    }
}
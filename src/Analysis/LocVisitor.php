<?php

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\PrettyPrinter;

class LocVisitor implements NodeVisitor
{
    private array $functionNodes = [];

    private bool $inFunction = false;

    private string $path;

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
    public function beforeTraverse(array $nodes)
    {
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

            $this->getLinesOfCode($node);

            $this->functionNodes = [];
        }
        elseif ($this->inFunction && str_starts_with($node->getType(), 'Stmt_')) {
            $this->functionNodes[] = $node;
        }
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes)
    {
    }

    private function getLinesOfCode(Node $node): void
    {
        $loc = $node->getEndLine() - $node->getStartLine() + 1;

        $prettyPrinter = new PrettyPrinter\Standard();
        $functionBodyCode = $prettyPrinter->prettyPrint($this->functionNodes);

        // count and remove multi lines comments
        $cloc = 0;
        if (preg_match_all('!/\*.*?\*/!s', $functionBodyCode, $matches)) {
            foreach ($matches[0] as $match) {
                $cloc += max(1, count(preg_split('/\r\n|\r|\n/', $match)));
            }
        }
        $functionBodyCode = preg_replace('!/\*.*?\*/!s', '', $functionBodyCode);

        // count and remove single line comments
        $functionBodyCode = preg_replace_callback('!(\'[^\']*\'|"[^"]*")|((?:#|\/\/).*$)!m', function (array $matches) use (&$cloc) {
            if (isset($matches[2])) {
                $cloc += 1;
            }
            return $matches[1];
        }, $functionBodyCode, -1);

        $functionBodyCode = trim(preg_replace('!(^\s*[\r\n])!sm', '', $functionBodyCode));

        $functionMetrics = new FunctionMetrics($this->path, $node->name);
        $functionMetrics->set('loc', $loc);
        $functionMetrics->set('cloc', $cloc);
        $functionMetrics->set('lloc', count(preg_split('/\r\n|\r|\n/', $functionBodyCode)));
        $this->metrics->push($functionMetrics);
    }
}
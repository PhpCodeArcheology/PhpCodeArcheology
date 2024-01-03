<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Analysis;

use Marcus\PhpLegacyAnalyzer\Metrics\FunctionAndClassIdentifier;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\PrettyPrinter;

class LocVisitor implements NodeVisitor
{
    use VisitorTrait;

    private array $functionNodes = [];

    private bool $inFunction = false;

    private bool $inClass = false;

    private int $insideLloc = 0;

    private int $insideFunctionLloc = 0;

    private int $insideMethodLloc = 0;

    private int $fileHtmlLoc = 0;

    private int $classHtmlLoc = 0;

    private int $functionHtmlLoc = 0;

    private int $methodHtmlLoc = 0;

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes): void
    {
        $this->projectMetrics = $this->metrics->get('project');

        $this->getFileMetrics();

        $this->insideLloc = 0;
        $this->fileHtmlLoc = 0;

        $lastNode = $nodes[array_key_last($nodes)];
        $loc = $lastNode->getEndLine();

        $nodesWithoutHtml = array_filter($nodes, function($node) {
            return !$node instanceof Node\Stmt\InlineHTML;
        });

        $prettyPrinter = new PrettyPrinter\Standard();
        $code = $prettyPrinter->prettyPrint($nodesWithoutHtml);

        [$cloc, $lloc] = $this->getClocAndLloc($code);

        $this->fileMetrics->set('loc', $loc);
        $this->fileMetrics->set('cloc', $cloc);
        $this->fileMetrics->set('lloc', $lloc);

        $projectLoc = $this->projectMetrics->get('OverallLoc') + $loc;
        $projectCloc = $this->projectMetrics->get('OverallCloc') + $cloc;
        $projectLloc = $this->projectMetrics->get('OverallLloc') + $lloc;

        $this->projectMetrics->set('OverallLoc', $projectLoc);
        $this->projectMetrics->set('OverallCloc', $projectCloc);
        $this->projectMetrics->set('OverallLloc', $projectLloc);
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\ClassMethod) {
            $this->inFunction = true;
            $this->functionHtmlLoc = 0;
            $this->methodHtmlLoc = 0;
        }
        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Enum_) {
            $this->inClass = true;
            $this->classHtmlLoc = 0;
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

            $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);
            $functionMetrics = $this->metrics->get($functionId);
            $functionMetrics->set('htmlLoc', $this->functionHtmlLoc);
            $this->metrics->set($functionId, $functionMetrics);
        }
        elseif ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_) {
            $this->inClass = false;

            $className = (string) ClassName::ofNode($node);
            $classId = (string) FunctionAndClassIdentifier::ofNameAndPath($className, $this->path);
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

                $methodId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $stmt->name, (string) $classMetrics->getIdentifier());
                $methodMetrics = $methods[$methodId];

                $methodLoc = $stmt->getEndLine() - $stmt->getStartLine() + 1;

                $methodBodyNodes = $stmt->stmts ? $stmt->stmts : [];
                $methodBodyCode = $prettyPrinter->prettyPrint($methodBodyNodes);
                [$methodCloc, $methodLloc] = $this->getClocAndLloc($methodBodyCode);

                if (count($methodBodyNodes) === 0) {
                    $methodLloc = 0;
                    $methodCloc = 0;
                }

                $methodMetrics->set('loc', $methodLoc);
                $methodMetrics->set('cloc', $methodCloc);
                $methodMetrics->set('lloc', $methodLloc);
                $methods[$methodId] = $methodMetrics;
            }

            $classMetrics->set('loc', $loc);
            $classMetrics->set('cloc', $cloc);
            $classMetrics->set('lloc', $lloc);
            $classMetrics->set('htmlLoc', $this->classHtmlLoc);
            $classMetrics->set('methods', $methods);

            $this->metrics->push($classMetrics);

            $this->insideLloc += $lloc;
            $this->insideMethodLloc += $lloc;
        }
        elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->inFunction = false;
        }
        elseif ($node instanceof Node\Stmt\InlineHTML) {
            $htmlLoc = $node->getEndLine() - $node->getStartLine();
            $this->fileHtmlLoc += $htmlLoc;

            if ($this->inClass) {
                $this->classHtmlLoc += $htmlLoc;

                if ($this->inFunction) {
                    $this->methodHtmlLoc += $htmlLoc;
                }
            }
            elseif ($this->inFunction) {
                $this->functionHtmlLoc += $htmlLoc;
            }
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
        $this->fileMetrics->set('htmlLoc', $this->fileHtmlLoc);

        $this->metrics->set($fileId, $this->fileMetrics);
        $this->metrics->set('project', $this->projectMetrics);

        $OverallLlocOutside = $this->projectMetrics->get('OverallLlocOutside');
        $OverallInsideMethodLloc = $this->projectMetrics->get('OverallInsideMethodLloc');
        $OverallInsideFuntionLloc = $this->projectMetrics->get('OverallInsideFuntionLloc');
        $OverallHtmlLoc = $this->projectMetrics->get('OverallHtmlLoc') ?? 0;

        $OverallLlocOutside += $llocFileOutside;
        $OverallInsideMethodLloc += $this->insideMethodLloc;
        $OverallInsideFuntionLloc += $this->insideFunctionLloc;
        $OverallHtmlLoc += $this->fileHtmlLoc;

        $this->projectMetrics->set('OverallLlocOutside', $OverallLlocOutside);
        $this->projectMetrics->set('OverallInsideMethodLloc', $OverallInsideMethodLloc);
        $this->projectMetrics->set('OverallInsideFuntionLloc', $OverallInsideFuntionLloc);
        $this->projectMetrics->set('OverallHtmlLoc', $OverallHtmlLoc);
    }

    private function getLinesOfCodeFunction(Node $node): void
    {
        $loc = $node->getEndLine() - $node->getStartLine() + 1;

        // Get cloc and lloc from function body
        $prettyPrinter = new PrettyPrinter\Standard();
        $functionBodyCode = $prettyPrinter->prettyPrint($this->functionNodes);
        [$cloc, $lloc] = $this->getClocAndLloc($functionBodyCode);

        $functionId = (string) FunctionAndClassIdentifier::ofNameAndPath((string) $node->namespacedName, $this->path);

        $functionMetrics = $this->metrics->get($functionId);
        $functionMetrics->set('loc', $loc);
        $functionMetrics->set('cloc', $cloc);
        $functionMetrics->set('lloc', $lloc);
        $this->metrics->push($functionMetrics);

        // Get lloc of whole function (with function declaration and ending curly brackets
        $wholeFunction = $prettyPrinter->prettyPrint([$node]);
        [$wCloc, $wLloc] = $this->getClocAndLloc($wholeFunction);

        $this->insideLloc += $wLloc;
        $this->insideFunctionLloc += $wLloc;
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

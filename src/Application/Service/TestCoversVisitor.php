<?php

declare(strict_types=1);

namespace PhpCodeArch\Application\Service;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class TestCoversVisitor extends NodeVisitorAbstract
{
    /** @var array<string, string> alias/shortName → FQCN */
    private array $useMap = [];
    /** @var string[] collected covered FQCNs */
    private array $covers = [];
    /** @var string[] all imported FQCNs */
    private array $useStatements = [];

    public function enterNode(Node $node): ?int
    {
        // Collect use-statements for name resolution
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $fqcn = $use->name->toString();
                $alias = $use->alias ? $use->alias->toString() : $use->name->getLast();
                $this->useMap[$alias] = $fqcn;
                $this->useStatements[] = $fqcn;
            }

            return null;
        }

        // Extract @covers from class and method docblocks + attributes
        if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\ClassMethod) {
            $docComment = $node->getDocComment();
            if ($docComment instanceof \PhpParser\Comment\Doc) {
                $this->extractFromDocblock($docComment->getText());
            }

            // PHP 8 #[CoversClass(Foo::class)] attribute (class-level only)
            if ($node instanceof Node\Stmt\Class_) {
                foreach ($node->attrGroups as $attrGroup) {
                    foreach ($attrGroup->attrs as $attr) {
                        $attrName = $attr->name->toString();
                        if (('CoversClass' === $attrName || str_ends_with($attrName, '\\CoversClass')) && [] !== $attr->args) {
                            $arg = $attr->args[0]->value;
                            if ($arg instanceof Node\Expr\ClassConstFetch
                                && $arg->class instanceof Node\Name) {
                                $this->covers[] = $this->resolveName($arg->class->toString());
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    private function extractFromDocblock(string $docblock): void
    {
        // Match @covers ClassName, @covers \Full\Name, @covers ClassName::method
        // Also matches @coversClass
        if (preg_match_all('/@covers(?:Class)?\s+(\\\\?[\w\\\\]+)(?:::[\w]+)?/m', $docblock, $matches)) {
            foreach ($matches[1] as $name) {
                $name = ltrim($name, '\\');
                $this->covers[] = $this->resolveName($name);
            }
        }
    }

    private function resolveName(string $name): string
    {
        // Short name with matching use-statement → resolve to FQCN
        if (!str_contains($name, '\\') && isset($this->useMap[$name])) {
            return $this->useMap[$name];
        }

        return $name;
    }

    /** @return list<string> Unique covered FQCNs */
    public function getCovers(): array
    {
        return array_values(array_unique($this->covers));
    }

    /** @return list<string> Unique imported FQCNs */
    public function getUseStatements(): array
    {
        return array_values(array_unique($this->useStatements));
    }
}

<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

/**
 * Verifies that the PHP extensions declared in composer.json are actually loaded.
 *
 * Composer enforces these requirements on installation, but the PHAR build ships
 * without any such check: a missing extension surfaces as a fatal error deep inside
 * the analysis (see issue #34). This check turns that into an actionable message.
 */
final class RequirementsChecker
{
    /**
     * Extension name => [human readable label, typical distribution package].
     *
     * Must stay in sync with the "ext-*" entries in composer.json.
     *
     * @var array<string, array{label: string, package: string}>
     */
    private const REQUIRED_EXTENSIONS = [
        'mbstring' => ['label' => 'Multibyte String', 'package' => 'mbstring'],
        'simplexml' => ['label' => 'SimpleXML', 'package' => 'xml'],
    ];

    /**
     * @return array<int, string> names of the missing extensions, empty when all are present
     */
    public function getMissingExtensions(): array
    {
        $missing = [];

        foreach (array_keys(self::REQUIRED_EXTENSIONS) as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }

        return $missing;
    }

    /**
     * Build an error message naming the missing extensions and how to install them.
     *
     * @param array<int, string> $missing
     */
    public function getErrorMessage(array $missing): string
    {
        $phpVersion = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

        $lines = [];
        foreach ($missing as $extension) {
            $meta = self::REQUIRED_EXTENSIONS[$extension] ?? null;
            if (null === $meta) {
                $lines[] = sprintf('  - %s', $extension);
                continue;
            }

            $lines[] = sprintf(
                '  - %s (%s), usually shipped as php%s-%s or php-%s',
                $extension,
                $meta['label'],
                $phpVersion,
                $meta['package'],
                $meta['package'],
            );
        }

        return sprintf(
            "Error: PhpCodeArcheology requires PHP extensions that are not loaded:\n%s\n\n"
            ."Install them via your package manager (apt, dnf, brew) and run the command again.\n",
            implode("\n", $lines),
        );
    }
}

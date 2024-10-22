<?php

declare(strict_types=1);

namespace PhpCodeArch\Report;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

interface ReportInterface
{
    public function __construct(
        Config              $config,
        DataProviderFactory  $reportDataFactory,
        false|\DateTimeImmutable $historyDate,
        FilesystemLoader    $twigLoader,
        Environment         $twig,
        CliOutput           $output);

    public function generate(): void;
}

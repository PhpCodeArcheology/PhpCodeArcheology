<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\ProjectMetrics;

enum OverallMetricsEnum: string{
    case OverallFiles = 'Files';
    case OverallFileErrors = 'File errors';
    case OverallFunctions = 'Functions';
    case OverallClasses = 'Classes';
    case OverallAbstractClasses = 'Abstract classes';
    case OverallInterfaces = 'Interfaces';
    case OverallMethods = 'Methods';
    case OverallPrivateMethods = 'Private methods';
    case OverallPublicMethods = 'Public methods';
    case OverallStaticMethods = 'Static methods';
    case OverallLoc = 'Lines of code';
    case OverallCloc = 'Comment lines';
    case OverallLloc = 'Logical lines of code';
    case OverallHtmlLoc = 'HTML lines of code';
    case OverallOutputStatements = 'Output statements';
    case OverallMaxCC = 'Max. cyclomatic complexity';
    case OverallMostComplexFile = 'Most complex file';
    case OverallMostComplexClass = 'Most complex class';
    case OverallMostComplexMethod = 'Most complex method';
    case OverallMostComplexFunction = 'Most complex function';
    case OverallAvgCC = 'Average complexity';
    case OverallAvgCCFile = 'Average file complexity';
    case OverallAvgCCClass = 'Average class complexity';
    case OverallAvgCCMethod = 'Average method complexity';
    case OverallAvgCCFunction = 'Average function complexity';
    case OverallCommentWeight = 'Average comment weight';
    case OverallAvgLcom = 'Average LCOM';
    case OverallAvgMI = 'Average Maintainability Index';
    case OverallAvgUsesCount = 'Average class dependencies count';
    case OverallAvgUsedByCount = 'Average class usage count';
    case OverallAvgInstability = 'Average class instability';
    case OverallAbstractness = 'Project abstractness';
    case OverallDistanceFromMainline = 'Distance from Mainline';
    case OverallInformationCount = 'Informations';
    case OverallWarningCount = 'Warnings';
    case OverallErrorCount = 'Errors';

    public static function keys(): array
    {
        return array_map(fn($case) => $case->name, self::cases());
    }

    public static function asArray(): array {
        return array_combine(
            array_map(fn($case) => $case->name, self::cases()),
            array_map(fn($case) => $case->value, self::cases())
        );
    }
}

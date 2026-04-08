<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics;

/**
 * Constants for all metric keys used throughout the system.
 *
 * Generated from data/metrics/*.php definitions.
 */
final class MetricKey
{
    // --- Size ---
    public const LOC = 'loc';
    public const CLOC = 'cloc';
    public const LLOC = 'lloc';
    public const LLOC_OUTSIDE = 'llocOutside';
    public const HTML_LOC = 'htmlLoc';
    public const HTML_PERCENTAGE = 'htmlPercentage';
    public const OUTPUT_COUNT = 'outputCount';

    // --- Halstead ---
    public const VOCABULARY = 'vocabulary';
    public const LENGTH = 'length';
    public const CALC_LENGTH = 'calcLength';
    public const VOLUME = 'volume';
    public const DIFFICULTY = 'difficulty';
    public const EFFORT = 'effort';
    public const OPERATORS = 'operators';
    public const OPERANDS = 'operands';
    public const UNIQUE_OPERATORS = 'uniqueOperators';
    public const UNIQUE_OPERANDS = 'uniqueOperands';
    public const COMPLEXITY_DENSITY = 'complexityDensity';

    // --- Complexity ---
    public const CC = 'cc';
    public const COGNITIVE_COMPLEXITY = 'cognitiveComplexity';
    public const AVG_METHOD_CC = 'avgMethodCc';
    public const AVG_METHOD_COG_C = 'avgMethodCogC';
    public const ESTIMATED_RUNTIME_COMPLEXITY = 'estimatedRuntimeComplexity';

    // --- Maintainability ---
    public const MAINTAINABILITY_INDEX = 'maintainabilityIndex';
    public const MAINTAINABILITY_INDEX_WITHOUT_COMMENTS = 'maintainabilityIndexWithoutComments';
    public const COMMENT_WEIGHT = 'commentWeight';

    // --- Coupling & Cohesion ---
    public const INSTABILITY = 'instability';
    public const LCOM = 'lcom';
    public const DIT = 'dit';
    public const NOC = 'noc';
    public const DIP_SCORE = 'dipScore';
    public const ABSTRACTNESS = 'abstractness';
    public const DISTANCE_FROM_MAINLINE = 'distanceFromMainline';
    public const PACKAGE_COHESION = 'packageCohesion';
    public const USES = 'uses';
    public const USES_COUNT = 'usesCount';
    public const USES_IN_PROJECT = 'usesInProject';
    public const USES_IN_PROJECT_COUNT = 'usesInProjectCount';
    public const USES_FOR_INSTABILITY = 'usesForInstability';
    public const USES_FOR_INSTABILITY_COUNT = 'usesForInstabilityCount';
    public const USED_BY = 'usedBy';
    public const USED_BY_COUNT = 'usedByCount';
    public const USED_BY_FUNCTION = 'usedByFunction';
    public const USED_BY_FUNCTION_COUNT = 'usedByFunctionCount';
    public const USED_FROM_OUTSIDE = 'usedFromOutside';
    public const USED_FROM_OUTSIDE_COUNT = 'usedFromOutsideCount';

    // --- Identity & Metadata ---
    public const SINGLE_NAME = 'singleName';
    public const FULL_NAME = 'fullName';
    public const FILE_NAME = 'fileName';
    public const FILE_PATH = 'filePath';
    public const DIR_NAME = 'dirName';
    public const NAMESPACE = 'namespace';
    public const PACKAGE = 'package';
    public const COMMON_PATH = 'commonPath';
    public const PROJECT_PATH = 'projectPath';
    public const ORIGINAL_ENCODING = 'originalEncoding';
    public const CLASS_INFO = 'classInfo';
    public const FUNCTION_TYPE = 'functionType';
    public const RETURN_TYPE = 'returnType';

    // --- Class Flags ---
    public const INTERFACE = 'interface';
    public const TRAIT = 'trait';
    public const ABSTRACT = 'abstract';
    public const ENUM = 'enum';
    public const FINAL = 'final';
    public const REAL_CLASS = 'realClass';
    public const ANONYMOUS = 'anonymous';

    // --- Visibility & Structure ---
    public const PUBLIC = 'public';
    public const PROTECTED = 'protected';
    public const PRIVATE = 'private';
    public const STATIC = 'static';
    public const CONSTANT_COUNT = 'constantCount';
    public const CONSTANTS = 'constants';
    public const PROPERTY_COUNT = 'propertyCount';
    public const METHOD_COUNT = 'methodCount';
    public const PUBLIC_COUNT = 'publicCount';
    public const PRIVATE_COUNT = 'privateCount';
    public const STATIC_COUNT = 'staticCount';
    public const PARAMETER_COUNT = 'parameterCount';
    public const NULLABLE_PARAMETER_COUNT = 'nullableParameterCount';
    public const OPTIONAL_PARAMETER_COUNT = 'optionalParameterCount';

    // --- Variables ---
    public const VARIABLES = 'variables';
    public const VARIABLES_USED = 'variablesUsed';
    public const DISTINCT_VARIABLES_USED = 'distinctVariablesUsed';
    public const SUPERGLOBALS = 'superglobals';
    public const SUPERGLOBALS_USED = 'superglobalsUsed';
    public const DISTINCT_SUPERGLOBALS_USED = 'distinctSuperglobalsUsed';
    public const CONSTANTS_USED = 'constantsUsed';
    public const DISTINCT_CONSTANTS_USED = 'distinctConstantsUsed';
    public const SUPERGLOBAL_METRIC = 'superglobalMetric';

    // --- Dependencies & Cycles ---
    public const IN_DEPENDENCY_CYCLE = 'inDependencyCycle';
    public const DEPENDENCY_CYCLE_LENGTH = 'dependencyCycleLength';
    public const DEPENDENCY_CYCLE_CLASSES = 'dependencyCycleClasses';
    public const LAYER_VIOLATION_COUNT = 'layerViolationCount';
    public const LAYER_VIOLATIONS = 'layerViolations';

    // --- SOLID ---
    public const SOLID_VIOLATION_COUNT = 'solidViolationCount';
    public const SOLID_VIOLATIONS = 'solidViolations';
    public const SRP_VIOLATION = 'srpViolation';
    public const ISP_VIOLATION = 'ispViolation';

    // --- Dead Code ---
    public const UNUSED_PRIVATE_METHOD_COUNT = 'unusedPrivateMethodCount';
    public const UNUSED_PRIVATE_METHODS = 'unusedPrivateMethods';

    // --- Documentation & Type Coverage ---
    public const HAS_DOC_BLOCK = 'hasDocBlock';
    public const DOC_COVERAGE = 'docCoverage';
    public const DOC_PARAM_COVERAGE = 'docParamCoverage';
    public const TYPE_COVERAGE = 'typeCoverage';
    public const TYPED_PARAM_COUNT = 'typedParamCount';
    public const TOTAL_PARAM_COUNT = 'totalParamCount';
    public const TYPED_PROPERTY_COUNT = 'typedPropertyCount';
    public const TOTAL_PROPERTY_COUNT = 'totalPropertyCount';
    public const TYPED_RETURN_COUNT = 'typedReturnCount';
    public const TOTAL_RETURN_COUNT = 'totalReturnCount';

    // --- Security ---
    public const SECURITY_SMELL_COUNT = 'securitySmellCount';
    public const SECURITY_SMELLS = 'securitySmells';

    // --- Duplication ---
    public const DUPLICATED_LINES = 'duplicatedLines';
    public const DUPLICATION_RATE = 'duplicationRate';

    // --- Predictions ---
    public const PREDICTION_GOD_OBJECT = 'predictionGodObject';
    public const PREDICTION_TOO_COMPLEX = 'predictionTooComplex';
    public const PREDICTION_TOO_DEPENDENT = 'predictionTooDependent';
    public const PREDICTION_TOO_LONG = 'predictionTooLong';
    public const PREDICTION_TOO_MUCH_HTML = 'predictionTooMuchHtml';
    public const PREDICTION_TOO_MUCH_OUTPUT = 'predictionTooMuchOutput';
    public const PREDICTION_VIEW_OR_DEFECT = 'predictionViewOrDefect';
    public const GOD_OBJECT_SUSPECT_INDEX = 'godObjectSuspectIndex';

    // --- Refactoring ---
    public const REFACTORING_PRIORITY = 'refactoringPriority';
    public const REFACTORING_PRIORITY_RECOMMENDATION = 'refactoringPriorityRecommendation';
    public const REFACTORING_PRIORITY_DRIVERS = 'refactoringPriorityDrivers';
    public const TECHNICAL_DEBT_SCORE = 'technicalDebtScore';

    // --- Testing ---
    public const HAS_TEST = 'hasTest';
    public const TEST_FILE_COUNT = 'testFileCount';
    public const TEST_TYPE = 'testType';
    public const LINE_COVERAGE = 'lineCoverage';
    public const IS_TEST_FILE = 'isTestFile';
    public const EXCLUDED_BY_PHPUNIT_SOURCE = 'excludedByPhpunitSource';

    // --- Git ---
    public const GIT_CHURN_COUNT = 'gitChurnCount';
    public const GIT_TOTAL_COMMITS = 'gitTotalCommits';
    public const GIT_AUTHOR_COUNT = 'gitAuthorCount';
    public const GIT_AUTHORS = 'gitAuthors';
    public const GIT_ACTIVE_AUTHORS = 'gitActiveAuthors';
    public const GIT_LAST_MODIFIED = 'gitLastModified';
    public const GIT_CODE_AGE_DAYS = 'gitCodeAgeDays';
    public const GIT_ANALYSIS_PERIOD = 'gitAnalysisPeriod';

    // --- Framework Detection ---
    public const DETECTED_FRAMEWORKS = 'detectedFrameworks';
    public const DETECTED_TEST_FRAMEWORKS = 'detectedTestFrameworks';

    // --- Health Score ---
    public const HEALTH_SCORE = 'healthScore';
    public const HEALTH_SCORE_GRADE = 'healthScoreGrade';
    public const HEALTH_SCORE_VERSION = 'healthScoreVersion';

    // --- Project Aggregates: Counts ---
    public const OVERALL_FILES = 'overallFiles';
    public const OVERALL_FILE_ERRORS = 'overallFileErrors';
    public const OVERALL_CLASSES = 'overallClasses';
    public const OVERALL_ABSTRACT_CLASSES = 'overallAbstractClasses';
    public const OVERALL_INTERFACES = 'overallInterfaces';
    public const OVERALL_FUNCTION_COUNT = 'overallFunctionCount';
    public const OVERALL_METHODS_COUNT = 'overallMethodsCount';
    public const OVERALL_PUBLIC_METHODS_COUNT = 'overallPublicMethodsCount';
    public const OVERALL_PRIVATE_METHODS_COUNT = 'overallPrivateMethodsCount';
    public const OVERALL_STATIC_METHODS_COUNT = 'overallStaticMethodsCount';
    public const OVERALL_OUTPUT_STATEMENTS = 'overallOutputStatements';

    // --- Project Aggregates: Size ---
    public const OVERALL_LOC = 'overallLoc';
    public const OVERALL_CLOC = 'overallCloc';
    public const OVERALL_LLOC = 'overallLloc';
    public const OVERALL_LLOC_OUTSIDE = 'overallLlocOutside';
    public const OVERALL_HTML_LOC = 'overallHtmlLoc';
    public const OVERALL_INSIDE_METHOD_LLOC = 'overallInsideMethodLloc';
    public const OVERALL_INSIDE_FUNCTION_LLOC = 'overallInsideFuntionLloc';

    // --- Project Aggregates: Complexity ---
    public const OVERALL_AVG_CC = 'overallAvgCC';
    public const OVERALL_AVG_MI = 'overallAvgMI';
    public const OVERALL_AVG_LCOM = 'overallAvgLcom';
    public const OVERALL_MAX_CC = 'overallMaxCC';
    public const OVERALL_COMMENT_WEIGHT = 'overallCommentWeight';
    public const OVERALL_AVG_CC_CLASS = 'overallAvgCCClass';
    public const OVERALL_AVG_CC_FILE = 'overallAvgCCFile';
    public const OVERALL_AVG_CC_FUNCTION = 'overallAvgCCFunction';
    public const OVERALL_AVG_CC_METHOD = 'overallAvgCCMethod';
    public const OVERALL_MAX_CC_CLASS = 'overallMaxCCClass';
    public const OVERALL_MAX_CC_FILE = 'overallMaxCCFile';
    public const OVERALL_MAX_CC_FUNCTION = 'overallMaxCCFunction';
    public const OVERALL_MAX_CC_METHOD = 'overallMaxCCMethod';

    // --- Project Aggregates: Coupling ---
    public const OVERALL_AVG_USES_COUNT = 'overallAvgUsesCount';
    public const OVERALL_AVG_USED_BY_COUNT = 'overallAvgUsedByCount';
    public const OVERALL_AVG_INSTABILITY = 'overallAvgInstability';
    public const OVERALL_ABSTRACTNESS = 'overallAbstractness';
    public const OVERALL_DISTANCE_FROM_MAINLINE = 'overallDistanceFromMainline';
    public const OVERALL_CLASSES_IN_CYCLES = 'overallClassesInCycles';
    public const OVERALL_DEPENDENCY_CYCLES = 'overallDependencyCycles';

    // --- Project Aggregates: Quality ---
    public const OVERALL_ERROR_COUNT = 'overallErrorCount';
    public const OVERALL_WARNING_COUNT = 'overallWarningCount';
    public const OVERALL_INFORMATION_COUNT = 'overallInformationCount';
    public const OVERALL_HTML_RATIO = 'overallHtmlRatio';
    public const OVERALL_PUBLIC_METHOD_RATIO = 'overallPublicMethodRatio';
    public const OVERALL_STATIC_METHOD_RATIO = 'overallStaticMethodRatio';
    public const OVERALL_ENCAPSULATION_SCORE = 'overallEncapsulationScore';
    public const OVERALL_TECHNICAL_DEBT_SCORE = 'overallTechnicalDebtScore';
    public const OVERALL_DUPLICATED_LINES = 'overallDuplicatedLines';
    public const OVERALL_DUPLICATION_RATE = 'overallDuplicationRate';

    // --- Project Aggregates: Refactoring ---
    public const OVERALL_AVG_REFACTORING_PRIORITY = 'overallAvgRefactoringPriority';
    public const OVERALL_MAX_REFACTORING_PRIORITY = 'overallMaxRefactoringPriority';
    public const OVERALL_CLASSES_NEEDING_REFACTORING = 'overallClassesNeedingRefactoring';

    // --- Project Aggregates: Testing ---
    public const OVERALL_TEST_FILE_COUNT = 'overallTestFileCount';
    public const OVERALL_PRODUCTION_FILE_COUNT = 'overallProductionFileCount';
    public const OVERALL_TEST_RATIO = 'overallTestRatio';
    public const OVERALL_TESTED_CLASS_COUNT = 'overallTestedClassCount';
    public const OVERALL_UNTESTED_CLASS_COUNT = 'overallUntestedClassCount';
    public const OVERALL_TESTED_CLASS_RATIO = 'overallTestedClassRatio';
    public const OVERALL_COVERAGE_PERCENT = 'overallCoveragePercent';
    public const OVERALL_FUNCTION_BASED_TEST_FILE_COUNT = 'overallFunctionBasedTestFileCount';
    public const OVERALL_SOURCE_EXCLUDED_CLASS_COUNT = 'overallSourceExcludedClassCount';

    // --- Project Aggregates: Per-Collection Min/Avg/Max ---
    public const OVERALL_CLASS_METRICS_COLLECTION_AVG_CC = 'overallClassMetricsCollectionAvgCc';
    public const OVERALL_CLASS_METRICS_COLLECTION_MAX_CC = 'overallClassMetricsCollectionMaxCc';
    public const OVERALL_CLASS_METRICS_COLLECTION_MIN_CC = 'overallClassMetricsCollectionMinCc';
    public const OVERALL_CLASS_METRICS_COLLECTION_AVG_DIFFICULTY = 'overallClassMetricsCollectionAvgDifficulty';
    public const OVERALL_CLASS_METRICS_COLLECTION_MAX_DIFFICULTY = 'overallClassMetricsCollectionMaxDifficulty';
    public const OVERALL_CLASS_METRICS_COLLECTION_MIN_DIFFICULTY = 'overallClassMetricsCollectionMinDifficulty';
    public const OVERALL_CLASS_METRICS_COLLECTION_AVG_EFFORT = 'overallClassMetricsCollectionAvgEffort';
    public const OVERALL_CLASS_METRICS_COLLECTION_MAX_EFFORT = 'overallClassMetricsCollectionMaxEffort';
    public const OVERALL_CLASS_METRICS_COLLECTION_MIN_EFFORT = 'overallClassMetricsCollectionMinEffort';
    public const OVERALL_CLASS_METRICS_COLLECTION_AVG_INSTABILITY = 'overallClassMetricsCollectionAvgInstability';
    public const OVERALL_CLASS_METRICS_COLLECTION_MAX_INSTABILITY = 'overallClassMetricsCollectionMaxInstability';
    public const OVERALL_CLASS_METRICS_COLLECTION_MIN_INSTABILITY = 'overallClassMetricsCollectionMinInstability';
    public const OVERALL_CLASS_METRICS_COLLECTION_AVG_LCOM = 'overallClassMetricsCollectionAvgLcom';
    public const OVERALL_CLASS_METRICS_COLLECTION_MAX_LCOM = 'overallClassMetricsCollectionMaxLcom';
    public const OVERALL_CLASS_METRICS_COLLECTION_MIN_LCOM = 'overallClassMetricsCollectionMinLcom';
    public const OVERALL_CLASS_METRICS_COLLECTION_AVG_MAINTAINABILITY_INDEX = 'overallClassMetricsCollectionAvgMaintainabilityIndex';
    public const OVERALL_CLASS_METRICS_COLLECTION_MAX_MAINTAINABILITY_INDEX = 'overallClassMetricsCollectionMaxMaintainabilityIndex';
    public const OVERALL_CLASS_METRICS_COLLECTION_MIN_MAINTAINABILITY_INDEX = 'overallClassMetricsCollectionMinMaintainabilityIndex';
    public const OVERALL_FILE_METRICS_COLLECTION_AVG_CC = 'overallFileMetricsCollectionAvgCc';
    public const OVERALL_FILE_METRICS_COLLECTION_MAX_CC = 'overallFileMetricsCollectionMaxCc';
    public const OVERALL_FILE_METRICS_COLLECTION_MIN_CC = 'overallFileMetricsCollectionMinCc';
    public const OVERALL_FILE_METRICS_COLLECTION_AVG_DIFFICULTY = 'overallFileMetricsCollectionAvgDifficulty';
    public const OVERALL_FILE_METRICS_COLLECTION_MAX_DIFFICULTY = 'overallFileMetricsCollectionMaxDifficulty';
    public const OVERALL_FILE_METRICS_COLLECTION_MIN_DIFFICULTY = 'overallFileMetricsCollectionMinDifficulty';
    public const OVERALL_FILE_METRICS_COLLECTION_AVG_EFFORT = 'overallFileMetricsCollectionAvgEffort';
    public const OVERALL_FILE_METRICS_COLLECTION_MAX_EFFORT = 'overallFileMetricsCollectionMaxEffort';
    public const OVERALL_FILE_METRICS_COLLECTION_MIN_EFFORT = 'overallFileMetricsCollectionMinEffort';
    public const OVERALL_FILE_METRICS_COLLECTION_AVG_MAINTAINABILITY_INDEX = 'overallFileMetricsCollectionAvgMaintainabilityIndex';
    public const OVERALL_FILE_METRICS_COLLECTION_MAX_MAINTAINABILITY_INDEX = 'overallFileMetricsCollectionMaxMaintainabilityIndex';
    public const OVERALL_FILE_METRICS_COLLECTION_MIN_MAINTAINABILITY_INDEX = 'overallFileMetricsCollectionMinMaintainabilityIndex';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_AVG_CC = 'overallFunctionMetricsCollectionAvgCc';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_MAX_CC = 'overallFunctionMetricsCollectionMaxCc';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_MIN_CC = 'overallFunctionMetricsCollectionMinCc';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_AVG_DIFFICULTY = 'overallFunctionMetricsCollectionAvgDifficulty';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_MAX_DIFFICULTY = 'overallFunctionMetricsCollectionMaxDifficulty';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_MIN_DIFFICULTY = 'overallFunctionMetricsCollectionMinDifficulty';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_AVG_EFFORT = 'overallFunctionMetricsCollectionAvgEffort';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_MAX_EFFORT = 'overallFunctionMetricsCollectionMaxEffort';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_MIN_EFFORT = 'overallFunctionMetricsCollectionMinEffort';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_AVG_MAINTAINABILITY_INDEX = 'overallFunctionMetricsCollectionAvgMaintainabilityIndex';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_MAX_MAINTAINABILITY_INDEX = 'overallFunctionMetricsCollectionMaxMaintainabilityIndex';
    public const OVERALL_FUNCTION_METRICS_COLLECTION_MIN_MAINTAINABILITY_INDEX = 'overallFunctionMetricsCollectionMinMaintainabilityIndex';
    public const OVERALL_MOST_COMPLEX_FILE = 'overallMostComplexFile';
    public const OVERALL_MOST_COMPLEX_CLASS = 'overallMostComplexClass';
    public const OVERALL_MOST_COMPLEX_METHOD = 'overallMostComplexMethod';
    public const OVERALL_MOST_COMPLEX_FUNCTION = 'overallMostComplexFunction';
}

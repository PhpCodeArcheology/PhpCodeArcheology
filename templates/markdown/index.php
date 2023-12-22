# Report for Project

<?php
$metrics = [
    'overallFiles' => 'Files',
    'overallFileErrors' => 'File errors',
    'overallFunctions' => 'Functions',
    'overallClasses' => 'Classes',
    'overallAbstractClasses' => 'Abstract classes',
    'overallInterfaces' => 'Interfaces',
    'overallMethods' => 'Methods',
    'overallPrivateMethods' => 'Private methods',
    'overallPublicMethods' => 'Public methods',
    'overallStaticMethods' => 'Static methods',
    'overallLoc' => 'Lines of code',
    'overallCloc' => 'Comment lines',
    'overallLloc' => 'Logical lines of code',
    'overallMaxCC' => 'Max. cyclomatic complexity',
    'overallMostComplexFile' => 'Most complex file',
    'overallMostComplexClass' => 'Most complex class',
    'overallMostComplexMethod' => 'Most complex method',
    'overallMostComplexFunction' => 'Most complex function',
    'overallAvgCC' => 'Average complexity',
    'overallAvgCCFile' => 'Average file complexity',
    'overallAvgCCClass' => 'Average class complexity',
    'overallAvgCCMethod' => 'Average method complexity',
    'overallAvgCCFunction' => 'Average function complexity',
];

$data = [];

$head = ['Element', 'Count'];

$overallData = $this->reportData->getOverallData();

foreach ($metrics as $key => $label) {
    $value = is_numeric($overallData[$key]) ? number_format($overallData[$key]) : $overallData[$key];

    $data[] = [$label, $value];
}

echo $this->renderTable($head, $data);
?>

## Deep dive

- [Class dependencies](class-dependencies.md)

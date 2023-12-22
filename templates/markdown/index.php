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
    'overallMostComplexFile' => 'Most complex file',
    'overallMostComplexClass' => 'Most complex class',
    'overallMostComplexMethod' => 'Most complex method',
    'overallMostComplexFunction' => 'Most complex function',
];

$data = [];

$head = ['Element', 'Count'];

$overallData = $this->reportData->getOverallData();

foreach ($metrics as $key => $label) {
    $value = is_numeric($overallData[$key]) ? number_format($overallData[$key]) : $overallData[$key];

    $data[] = [$label, $value];
}

echo $this->renderTable($head, $data);
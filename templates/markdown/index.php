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
];

$projectMetrics = $this->metrics->get('project');

$data = [];

$head = ['Element', 'Count'];

foreach ($metrics as $key => $label) {
    $data[] = [$label, number_format($projectMetrics->get($key))];
}

echo $this->renderTable($head, $data);
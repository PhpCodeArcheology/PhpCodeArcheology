# Class halstead metrics

<?php
$classes = $this->reportData->getClasses();

$classes = array_filter($classes, function($item) {
   return isset($item['effort']);
});

uasort($classes, function($a, $b) {
    if ($a['effort'] === $b['effort']) {
        return 0;
    }

    return ($a['effort'] > $b['effort']) ? -1 : 1;
});

?>
Class | Vocabulary | Length | calcLength | Volume | Difficulty | Effort | Complexity density | MI | Lcom
----- | ---------- | ------ | ---------- | ------ | ---------- | ------ | ------------------ | -- | ----
<?php
foreach ($classes as $className => $classData) {
    if ($classData['internal'] === false) {
        continue;
    }

    printf('%s | %s | %s | %s | %s | %s | %s | %s | %s | %s',
        sprintf('`%s`', $className),
        $classData['vocabulary'],
        $classData['length'],
        number_format($classData['calcLength'], 2),
        number_format($classData['volume'], 2),
        number_format($classData['difficulty'], 2),
        number_format($classData['effort'], 2),
        number_format($classData['complexityDensity'], 2),
        number_format($classData['maintainabilityIndex'], 2),
        $classData['lcom']
    );

    echo PHP_EOL;
}


# Class dependencies

<?php
$classes = $this->reportData->getClasses();

uasort($classes, function($a, $b) {
   if ($a['usedByCount'] === $b['usedByCount']) {
       return 0;
   }

   return ($a['usedByCount'] > $b['usedByCount']) ? -1 : 1;
});

?>
Class | Used by classes | Uses classes | Used by functions | Used from outside | Instability | Superglobales used | Variables used | Constants used | Superglobals Metric
----- | --------------- | ------------ | ----------------- | ----------------- | ----------- | ------------------ | -------------- | -------------- | -------------------
<?php
foreach ($classes as $className => $classData) {
    if ($classData['internal'] === false) {
        continue;
    }

    printf('%s | %s | %s | %s | %s | %s | %s | %s | %s | %s',
        sprintf('`%s`', $className),
        $classData['usedByCount'],
        $classData['usesCount'],
        $classData['usedByFunctionCount'],
        $classData['usedFromOutsideCount'],
        number_format($classData['instability'], 2),
        $classData['superglobalsUsed'],
        $classData['variablesUsed'],
        $classData['constantsUsed'],
        $classData['superglobalMetric']
    );

    echo PHP_EOL;
}

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
Class | Used by classes | Uses classes | Used by functions | Used from outside
----- | --------------- | ------------ | ----------------- | -----------------
<?php
foreach ($classes as $className => $classData) {
    if ($classData['internal'] === false) {
        continue;
    }

    printf('%s | %s | %s | %s | %s',
        sprintf('`%s`', $className),
        $classData['usedByCount'],
        $classData['usesCount'],
        $classData['usedByFunctionCount'],
        $classData['usedFromOutsideCount']
    );

    echo PHP_EOL;
}

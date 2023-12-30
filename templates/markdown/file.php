# <?php echo $data['name']; ?>


## Classes

<?php
foreach ($data['classes'] as $name => $classData) {
    echo "- " . $name.PHP_EOL;
}
?>

## Functions

<?php
foreach ($data['functions'] as $name => $fileData) {
    echo "- " . $name.PHP_EOL;
}

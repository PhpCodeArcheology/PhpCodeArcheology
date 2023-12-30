# Files

<?php
$files = $this->reportData->getFiles();
?>
File | Loc | Lloc | Cloc
---- | --- | ---- | ----
<?php
foreach ($files as $fileName => $fileData) {
    $mdFile = 'files/' . $fileData['id'] . '.md';

    $fileData['name'] = $fileName;

    $this->renderTemplate('file.php', $mdFile, $fileData);

    printf('[%s](%s) | %s | %s | %s',
        $fileName,
        $mdFile,
        $fileData['loc'],
        $fileData['lloc'],
        $fileData['cloc']
    );
    echo PHP_EOL;
}

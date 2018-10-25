#!/usr/bin/php
<?php

function help()
{
    $scriptName = $_SERVER['argv'][0];
    echo <<< EOF
$scriptName -s <source-dir> -d <destination-dir> [options]
    -s source directory
    -d destination directory
    -h help
    -m move files instead copy
    --dry-run execute script without move or copy files
EOF;

}

// default values
$SOURCE             = '.';
$DEST               = '.';
$command            = 'copy';
$commandDescription = 'copy';
$dryRun             = false;

$shortopts  = 'd:s:';   // Required value
$shortopts .= 'm';      // These options do not accept values
//$shortopts .= "v::";  // Optional value

//$longopts  = array(
//    "required:",     // Required value
//    "optional::",    // Optional value
//    "option",        // No value
//    "opt",           // No value
//);
$longopts = ['dry-run'];
$options = getopt($shortopts, $longopts);

if (isset($options['s'])) {
    $SOURCE = rtrim($options['s'], ' /');
    if (!is_dir($SOURCE)) {
        echo "invalid source directory\n";
        exit(1);
    }
}
if (isset($options['d'])) {
    $DEST = rtrim($options['d'], ' /');
    if (!is_dir($SOURCE)) {
        echo "invalid destination directory\n";
        exit(1);
    }
}
if (isset($options['m'])) {
    $command = 'rename';
    $commandDescription = 'move';
}
if (isset($options['dry-run'])) {
    $dryRun = true;
}

echo "Looking for supported files...\n";
$findFile = tempnam(sys_get_temp_dir(), 'photomove-');
$path = escapeshellarg($SOURCE);
$out = exec("find $path -iregex '^.+\.\(jpg\|jpeg\|crw\|thm\|rw2\|arw\|avi\|mov\|mp4\|mts\|png\)' > $findFile && wc -l $findFile");

$n          = str_replace(" $findFile", '', $out);
$count      = 0;
$percentage = 0;
$success    = 0;
$errors     = 0;

$handle = fopen($findFile, 'r');
while (($sourceFile = fgets($handle)) !== false) {
    $count++;
    $sourceFile = trim($sourceFile);
    $percentage = intdiv($count * 100, $n);
    echo str_pad("$percentage%", 4, ' ', STR_PAD_LEFT) . " $sourceFile";

    $exifData = @exif_read_data($sourceFile);
//    "DateTimeOriginal": "2016:09:25 14:57:36",
//    "DateTimeDigitized": "2016:09:25 14:57:36",

    $date = empty($exifData['DateTimeOriginal']) ? null : DateTime::createFromFormat('Y:m:d H:i:s', $exifData['DateTimeOriginal']);
    if ($date) {
        $success++;

        $destDir = "$DEST/" . $date->format('Y') . '/' . $date->format('m') . '/' . $date->format('d');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $i = 0;
        $pathInfo  = pathinfo($sourceFile);
        $extension = strtolower($pathInfo['extension']);
        do {
            $suffix = $i ? "_$i" : '';
            $destFile = "$destDir/" . $date->format('Y-m-d_H.i.s') . "$suffix.$extension";
            $i++;
        } while (file_exists($destFile));

        echo " $commandDescription to $destFile";

        if (!$dryRun) {
            if (!$command($sourceFile, $destFile)) {
                echo "Can not $commandDescription $sourceFile to $destFile";
                exit(1);
            }
        }
    } else {
        $errors++;
        echo ' ERROR';
//        echo json_encode($exifData, JSON_PRETTY_PRINT);
    }

    echo PHP_EOL;
}
fclose($handle);

echo "success: $success\n";
echo "errors: $errors\n";
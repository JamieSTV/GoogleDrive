<?php 
require 'googleDrive.php';

$drive = new GoogleDrive();

// get images list if none exists. 
if(!file_exists('images.json')){
    $images = $drive->getFiles();
    $file = fopen('images.json','w');
    fputs($file,json_encode($drive->files));
    fclose($file);
}

$images = json_decode(file_get_contents('images.json'),true);
$drive->downloadImages($images);

die('done');
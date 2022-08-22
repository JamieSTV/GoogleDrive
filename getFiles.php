<?php 
require 'client.php';

use Google\Service\Drive;

$client = getClient();
$service = new Drive($client);


// get files list
$optParams = array(
    'pageSize' => 10,
    'fields' => 'nextPageToken, files(id, name)'
);
$results = $service->files->listFiles($optParams);

if (count($results->getFiles()) == 0) {
    die("No files found.\n");
} 

foreach ($results->getFiles() as $file) {
    printf("%s (%s)\n", $file->getName(), $file->getId());
}



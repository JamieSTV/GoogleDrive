<?php
require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Drive;

class GoogleDrive{

    protected $client;
    protected $service;
    protected $downloadLocation = __DIR__ .'/files/';

    public $files = [
        'files' => [],
        'fileCount' => 0
    ];

    public $topFolder = '1QChWbjJhEFL4B3aWvhwgcFGqljpIVd99';

    public function __construct()
    {
        $this->client = $this->getClient();
        $this->service = new Drive($this->client);
    }

    public function getClient()
    {
        $client = new Client();
        $client->setApplicationName('Google Drive API PHP Quickstart');
        $client->setScopes('https://www.googleapis.com/auth/drive.readonly');
        $client->setAuthConfig('credentials.json');
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        $tokenPath = 'token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        try{
            if ($client->isAccessTokenExpired()) {
                // Refresh the token if possible, else fetch a new one.
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                } else {
                    // Request authorization from the user.
                    $authUrl = $client->createAuthUrl();
                    printf("Open the following link in your browser:\n%s\n", $authUrl);
                    print 'Enter verification code: ';
                    $authCode = trim(fgets(STDIN));

                    // Exchange authorization code for an access token.
                    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                    $client->setAccessToken($accessToken);

                    // Check to see if there was an error.
                    if (array_key_exists('error', $accessToken)) {
                        throw new Exception(join(', ', $accessToken));
                    }
                }
                // Save the token to a file.
                if (!file_exists(dirname($tokenPath))) {
                    mkdir(dirname($tokenPath), 0700, true);
                }
                file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            }
        }
        catch(Exception $e) {
            // TODO(developer) - handle error appropriately
            echo 'Some error occured: '.$e->getMessage();
        }
        return $client;
    }

    public function getFoldersList($parentId){
        $programmes = [];
        do {
            $pageToken = null;
            $files = $this->listFiles("'{$parentId}' in parents and mimeType = 'application/vnd.google-apps.folder'", $pageToken);
            foreach($files['files'] as $file){
                if($file->mimeType == 'application/vnd.google-apps.folder'){
                    $programmes[] = [
                        'name' => $file->getName(),
                        'id' => $file->getId(),
                        'parentId' => $file->getParents()[0]
                    ];
                    echo 'added '.$file->getName().PHP_EOL;
                    $programmes = array_merge($programmes, $this->getFoldersList($file->id));
                } else {
                    $this->files['fileCount']++;
                    $this->files['files'][] = [
                        'id' => $file->getId(),
                        'name' => $file->getName(),
                        'type' => $file->getMimeType(),
                        'parents' => $file->getParents(),
                    ];
                }
            }

            $pageToken = $files->pageToken;
        } while ($pageToken != null);
        return $programmes;
    }

    public function getFiles($parentId = null){
        $parentId = $parentId ?? $this->topFolder;
        do {
            $pageToken = null;
            $files = $this->listFiles("'{$parentId}' in parents", $pageToken);
            foreach($files['files'] as $file){
                if($file->mimeType == 'application/vnd.google-apps.folder'){
                    echo "\e[0;31mDirectory\e[0m: ".$this->getPath($file->getId()).PHP_EOL;
                    $this->getFiles($file->id);
                } else {
                    echo 'File: '.$file->getName().PHP_EOL;
                    $this->files['fileCount']++;
                    $this->files['files'][] = [
                        'id' => $file->getId(),
                        'name' => $file->getName(),
                        'type' => $file->getMimeType(),
                        'parent' => $file->getParents()[0],
                        'path' => $this->GetPath($file->getParents()[0])
                    ];
                }
            }
            $pageToken = $files->pageToken;
        } while ($pageToken != null);
        return;
    }

    public function getPath($fileId){
        if(file_exists('folders.json')){
            $folders = json_decode(file_get_contents('folders.json'),true);
        } else {
            echo 'No directory structure file exists, creating one' .PHP_EOL;
            $folders = $this->getFoldersList($this->topFolder);
            $json = fopen('folders.json', 'w');
            fwrite($json, json_encode($folders));
            fclose($json);
        }

        $file = $folders[array_search($fileId, array_column($folders, 'id'))];
        $path[] = $file['name'];

        while($file['parentId'] !== $this->topFolder){
            $file = $folders[array_search($file['parentId'], array_column($folders, 'id'))];
            array_unshift($path,$file['name']);
        }
        array_unshift($path,'files');

        return implode('/',$path).'/';
    }

    public function downloadImages($images){
        foreach($images['files'] as $image){     
            $filepath = $image['path'].$image['name'];   
            if(file_exists($filepath)){
                echo $image['name'].' has already been downloaded'.PHP_EOL;
                continue;
            }

            if(!file_exists($image['path'])){
                mkdir($image['path'], 0777, true);
            }

            $response = $this->service->files->get($image['id'],['alt' => 'media']);
            $content = $response->getBody()->getContents();
            
            $imageFile = fopen($filepath, 'w');
            fwrite($imageFile, $content);
            fclose($imageFile);


            echo $image['name'].' Downloaded'.PHP_EOL;
        }
    }

    protected function listFiles($query, $pageToken){
        return $this->service->files->listFiles([
            'q' => $query,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
            'spaces' => 'drive',
            'pageToken' => $pageToken,
            'fields' => 'nextPageToken, files(*)'
        ]);
    }
}
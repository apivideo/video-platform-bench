<?php

namespace App\Service;

use Google\Exception;
use Google_Client;
use Google_Service_YouTube;
use Google_Service_YouTube_Video;
use Google_Service_YouTube_VideoSnippet;
use Google_Service_YouTube_VideoStatus;
use JetBrains\PhpStorm\ArrayShape;

class BenchYoutubeService
{

    private array $access_token;

    public function __construct(private Google_Client $googleClient)
    {
    }

    /**
     * @param string $videoUriPath
     * @return array
     */
    #[ArrayShape(['TimeToEncodeFirstQuality' => "", 'FullTimeToEncode' => "", 'TimeToPlayback' => "null"])]
    public function performVod(string $videoUriPath): array
    {
        // YouTube doesn't support upload from URL so the app have to download it first and then upload the file
        $filePath = sys_get_temp_dir().'/'.basename($videoUriPath);
        file_put_contents(
            $filePath,
            file_get_contents($videoUriPath)
        );

        $this->googleClient->setAccessToken($this->access_token);

        // Init service and video objects
        $service = new Google_Service_YouTube($this->googleClient);
        $video = new Google_Service_YouTube_Video();

        $videoSnippet = new Google_Service_YouTube_VideoSnippet();
        $videoSnippet->setTitle('Test video upload.');
        $video->setSnippet($videoSnippet);

        $videoStatus = new Google_Service_YouTube_VideoStatus();
        $videoStatus->setPrivacyStatus('private');
        $video->setStatus($videoStatus);

        // Start EncodingTime measure
        $startEncodingTime = microtime(true);

        // Upload/Download video file
        $videoYT = $service->videos->insert(
            'snippet,status',
            $video,
            array(
                'data' => file_get_contents($filePath),
                'mimeType' => 'application/octet-stream',
                'uploadType' => 'multipart'
            )
        );

        // Waiting for succeeded status
        do {
            $videoYTStatus = $service->videos->listVideos(
                'processingDetails',
                [
                    'id' => $videoYT->getId()
                ]

            );
            sleep(1);
        } while ($videoYTStatus->getItems()[0]->getProcessingDetails()->getProcessingStatus() !== 'succeeded');

        $encodingTime = microtime(true) - $startEncodingTime;

        $service->videos->delete($videoYT->getId());

        // Delete tmp file
        unlink($filePath);

        return [
            'TimeToEncodeFirstQuality' => null,
            'FullTimeToEncode' => $encodingTime,
            'TimeToPlayback' => null,
        ];
    }

    #[ArrayShape(['TimeToPlayback' => ""])]
    public function performLive(string $videoUriPath): array
    {
        return [
            'TimeToPlayback' => null,
        ];
    }

    public function setAccessToken(array $token)
    {
        $this->access_token = $token;
    }
}

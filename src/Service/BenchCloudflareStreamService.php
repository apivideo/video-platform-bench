<?php

namespace App\Service;

use GuzzleHttp\Client;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class BenchCloudflareStreamService
{
    public function __construct(private string $accountId, private string $apiToken, private Client $client){}

    #[ArrayShape(['TimeToEncodeFirstQuality' => "", 'FullTimeToEncode' => "", 'TimeToPlayback' => ""])]
    public function performVod(string $videoUriPath): array
    {
        // Start TimeToPlayback measure
        $startTimeToPlayback = microtime(true);

        // Start EncodingTime measure
        $startEncodingTime = microtime(true);

        // Upload/Download video file
        $response = $this->client->post(
            "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/stream/copy",
            [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiToken}"
                ],
                'json' => [
                    'url' => $videoUriPath,
                    'meta' => ['name' => 'Stream Video']
                ]
            ]
        );

        $video = json_decode($response->getBody()->getContents(), true);
        $videoId = $video['result']['uid'];

        // Waiting for Ready status
        do {
            $response = $this->client->get(
                "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/stream/$videoId",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$this->apiToken}"
                    ]
                ]
            );
            $video = json_decode($response->getBody()->getContents(), true);
            sleep(1);
        } while ($video['result']['readyToStream'] === false);

        // Compute EncodingTime measure
        $encodingTime = microtime(true) - $startEncodingTime;
        // Download master hls manifest
        $masterManifestHls = file_get_contents($video['result']['playback']['hls']);
        // Build base stream url
        $baseUri = "https://videodelivery.net/$videoId/manifest/";
        // Extract url of the first playlist manifest
        preg_match('/stream_.*\.m3u8/', $masterManifestHls, $matchMasterHls);
        $firstQualityHlsPlaylistUrl = $matchMasterHls[0];

        // Download first playlist hls manifest
        $firstQualityHls = file_get_contents($baseUri.$firstQualityHlsPlaylistUrl);

        // Extract url of the first fragment
        preg_match('/..\/..\/.*\.ts.*/', $firstQualityHls, $matchPlaylistHls);

        // Download first video fragment
        file_get_contents($baseUri.$matchPlaylistHls[0]);

        // Compute TimeToPlayback measure
        $timeToPlayback = microtime(true) - $startTimeToPlayback;

        $this->client->delete(
            "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/stream/$videoId",
            [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiToken}"
                ]
            ]
        );

        return [
            'TimeToEncodeFirstQuality' => null,
            'FullTimeToEncode' => $encodingTime,
            'TimeToPlayback' => $timeToPlayback,
        ];
    }

    #[ArrayShape(['TimeToPlayback' => ""])]
    public function performLive(string $videoUriPath): array
    {
        return [
            'TimeToPlayback' => null,
        ];
    }

}

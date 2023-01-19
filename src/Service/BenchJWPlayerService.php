<?php


namespace App\Service;


use ApiVideo\Client\Model\LiveStreamCreationPayload;
use JetBrains\PhpStorm\ArrayShape;
use Jwplayer\JwplatformAPI;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class BenchJWPlayerService
{

    public function __construct(private JwplatformAPI $jw)
    {
    }

    #[ArrayShape(['TimeToEncodeFirstQuality' => "", 'FullTimeToEncode' => "", 'TimeToPlayback' => ""])]
    public function performVod(string $videoUriPath): array
    {
        // Start TimeToPlayback measure
        $startTimeToPlayback = microtime(true);

        // Start EncodingTime measure
        $startEncodingTime = microtime(true);

        // Upload/Download video file
        $video = $this->jw->call('/videos/create', [
            'title' => 'Video platform bench test',
            'download_url' => $videoUriPath
        ]);

        $videoId = $video['video']['key'];

        // Waiting for first HLS rendition processed
        do {
            $jwVideoStatus = $this->jw->call('/videos/show', [
                'video_key' => $videoId
            ]);
            sleep(1);
        } while ($jwVideoStatus['video']['status'] !== 'ready');

        // Compute EncodingTime measure
        $encodingTime = microtime(true) - $startEncodingTime;

        // Download master hls manifest
        do {
            $file_headers = get_headers("https://cdn.jwplayer.com/manifests/$videoId.m3u8");
            sleep(1);
        } while (strpos($file_headers[0], '404') !== false);
        $masterManifestHls = file_get_contents("https://cdn.jwplayer.com/manifests/$videoId.m3u8");

        // Extract url of the first playlist manifest
        preg_match('/https:\/\/.*/', $masterManifestHls, $matchMasterHls);

        // Download first playlist hls manifest
        $firstQualityHls = file_get_contents($matchMasterHls[0]);

        // Extract url of the first fragment
        preg_match("/$videoId.*/", $firstQualityHls, $matchPlaylistHls);

        // Build fragment url
        $fragmentShortUrl = $matchPlaylistHls[0];
        $fragmentBaseUrl = explode('/', $matchMasterHls[0]);
        array_pop($fragmentBaseUrl);
        $fragmentUrl = implode('/', $fragmentBaseUrl) . '/' . $fragmentShortUrl;

        // Download first video fragment
        file_get_contents($fragmentUrl);

        // Compute TimeToPlayback measure
        $timeToPlayback = microtime(true) - $startTimeToPlayback;

        // Checking all encoding are done
        $encodingDone = false;
        do {
            $jwVideoRenditionsStatus = $this->jw->call('/videos/conversions/list', [
                'video_key' => $videoId
            ]);
            $videoRenditionsInProgress = array_filter($jwVideoRenditionsStatus['conversions'], function ($rendition) {
                return $rendition['status'] === 'Queued';
            });

            if(empty($videoRenditionsInProgress)){
                $encodingDone = true;
            }
            sleep(1);
        } while ($encodingDone === false);

        // Compute FullEncodingTime measure
        $fullEncodingTime = microtime(true) - $startEncodingTime;

        $this->jw->call('/videos/delete', [
            'video_key' => $videoId
        ]);

        return [
            'TimeToEncodeFirstQuality' => $encodingTime,
            'FullTimeToEncode' => $fullEncodingTime,
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

<?php

namespace App\Service;

use ApiVideo\Client\Client;
use ApiVideo\Client\Model\LiveStreamCreationPayload;
use ApiVideo\Client\Model\VideoCreationPayload;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class BenchApiVideoService
{

    public function __construct(private Client $apivideo)
    {
    }

    /**
     * @throws Exception
     */
    #[ArrayShape(['TimeToEncodeFirstQuality' => "", 'FullTimeToEncode' => "", 'TimeToPlayback' => ""])]
    public function performVod(string $videoUriPath): array
    {
        // Start TimeToPlayback measure
        $startTimeToPlayback = microtime(true);

        // Start EncodingTime measure
        $startEncodingTime = microtime(true);

        // Upload/Download video file
        $video = $this->apivideo->videos()->create(
            new VideoCreationPayload(
                [
                    'title' => 'benchmark video platform',
                    'source' => $videoUriPath
                ]
            )
        );

        // Waiting for Ready status
        do {
            $videoStatus = $this->apivideo->videos()->getStatus($video->getVideoId());
            usleep(500);
        } while ($videoStatus->getEncoding()->getPlayable() !== true);

        // Compute EncodingTime measure
        $encodingTime = microtime(true) - $startEncodingTime;

        // Build base stream url
        $baseUri = "https://cdn.api.video/vod/{$video->getVideoId()}/hls/";

        // Download master hls manifest
        do {
            $file_headers = get_headers($video->getAssets()->getHls());
            usleep(500);
        } while (strpos($file_headers[0], '202') !== false || strpos($file_headers[0], '404') !== false);

        do {
            $masterManifestHls = file_get_contents($video->getAssets()->getHls());
            usleep(500);
        } while (preg_match('/.*\/processing_video\/.*/', $masterManifestHls));

        // Extract uri of the first playlist manifest
        preg_match('/.*\/manifest.m3u8/', $masterManifestHls, $matchMasterHls);
        $firstQualityHlsPlaylistUrl = $matchMasterHls[0];

        // Download first playlist hls manifest & Extract uri of the first fragment
        do {
            $firstQualityHls = file_get_contents($baseUri . $firstQualityHlsPlaylistUrl);
            usleep(500);
        } while (preg_match('/video-\d+-\d+.ts/', $firstQualityHls, $matchPlaylistHls) === 0);

        // Build fragment url
        $firstQuality = (explode('/', $firstQualityHlsPlaylistUrl))[0];
        $fragmentUrl = $baseUri . "$firstQuality/" . $matchPlaylistHls[0];

        // Download first video fragment
        file_get_contents($fragmentUrl);

        // Compute TimeToPlayback measure
        $timeToPlayback = microtime(true) - $startTimeToPlayback;

        // Checking all encoding are done
        do {
            $encodingDone = true;
            $videoStatus = $this->apivideo->videos()->getStatus($video->getVideoId());
            foreach ($videoStatus->getEncoding()->getQualities() as $qualityProcessing) {
                if ($qualityProcessing['status'] !== 'encoded') {
                    $encodingDone = false;
                }
            }
            usleep(500);
        } while ($encodingDone === false);

        // Compute FullEncodingTime measure
        $fullEncodingTime = microtime(true) - $startEncodingTime;

        // Delete video
        $this->apivideo->videos()->delete($video->getVideoId());

        return [
            'TimeToEncodeFirstQuality' => $encodingTime,
            'FullTimeToEncode' => $fullEncodingTime,
            'TimeToPlayback' => $timeToPlayback,
        ];
    }

    #[ArrayShape(['TimeToPlayback' => ""])]
    public function performLive(string $videoUriPath): array
    {

        // Create Live Stream
        $live = $this->apivideo->liveStreams()->create(
            new LiveStreamCreationPayload(
                [
                    'name' => 'benchmark live platform',
                    'record' => false
                ]
            )
        );
        $executableFinder = new ExecutableFinder();
        $ffmpegPath = $executableFinder->find('ffmpeg');
        $process = new Process([
            $ffmpegPath,
            '-re',
            '-stream_loop',
            '-1',
            '-i',
            $videoUriPath,
            '-profile:v',
            'main',
            '-pix_fmt',
            'yuv420p',
            '-c:v',
            'libx264',
            '-r',
            '30',
            '-preset',
            'ultrafast',
            '-tune',
            'zerolatency',
            '-c:a',
            'aac',
            '-ar',
            '48000',
            '-ac',
            '2',
            '-f',
            'flv',
            "rtmp://broadcast.api.video/s/{$live->getStreamKey()}"
        ]);

        // Start TimeToPlayback measure
        $startTimeToPlayback = microtime(true);
        $process->start();

        // Build base stream url
        $baseUri = "https://live.api.video/";

        // Download master hls manifest
        do {
            $file_headers = get_headers($live->getAssets()->getHls());
            sleep(1);
        } while (strpos($file_headers[0], '404') !== false);
        $masterManifestHls = file_get_contents($live->getAssets()->getHls());

        // Extract uri of the first playlist manifest
        preg_match('/.*\/index.m3u8/', $masterManifestHls, $matchMasterHls);
        $firstQualityHlsPlaylistUrl = $matchMasterHls[0];

        // Download first playlist hls manifest & Extract uri of the first fragment
        do {
            $firstQualityHls = file_get_contents($baseUri . $firstQualityHlsPlaylistUrl);
            usleep(500);
        } while (preg_match('/\d+.ts/', $firstQualityHls, $matchPlaylistHls) === false);

        if (!array_key_exists(0, $matchPlaylistHls)) {
            throw new Exception("Error: index 0 doesn't exist");
        }

        // Build fragment url
        $firstQuality = (explode('/', $firstQualityHlsPlaylistUrl))[0];
        $fragmentUrl = $baseUri . "$firstQuality/" . $matchPlaylistHls[0];

        // Download first video fragment
        file_get_contents($fragmentUrl);

        // Compute TimeToPlayback measure
        $timeToPlayback = microtime(true) - $startTimeToPlayback;

        $process->stop(5, SIGINT);

        // Delete live
        $this->apivideo->liveStreams()->delete($live->getLiveStreamId());

        return [
            'TimeToPlayback' => $timeToPlayback,
        ];
    }
}

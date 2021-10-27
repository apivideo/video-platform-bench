<?php

namespace App\Service;

use Exception;
use JetBrains\PhpStorm\ArrayShape;
use MuxPhp\Api\AssetsApi;
use MuxPhp\Api\LiveStreamsApi;
use MuxPhp\ApiException;
use MuxPhp\Models\CreateAssetRequest;
use MuxPhp\Models\CreateLiveStreamRequest;
use MuxPhp\Models\InputSettings;
use MuxPhp\Models\PlaybackPolicy;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class BenchMuxService
{

    public function __construct(private AssetsApi $mux, private LiveStreamsApi $muxLive)
    {
    }

    /**
     * @throws ApiException
     * @throws Exception
     */
    #[ArrayShape(['TimeToEncodeFirstQuality' => "", 'FullTimeToEncode' => "", 'TimeToPlayback' => ""])]
    public function performVod(string $videoUriPath): array
    {
        // Start TimeToPlayback measure
        $startTimeToPlayback = microtime(true);

        $input = new InputSettings(["url" => $videoUriPath]);
        $createAssetRequest = new CreateAssetRequest(["input" => $input, "mp4_support" => "standard", "playback_policy" => [PlaybackPolicy::_PUBLIC]]);

        // Start EncodingTime measure
        $startEncodingTime = microtime(true);

        // Upload/Download video file
        $video = $this->mux->createAsset($createAssetRequest);

        $assetId = $video->getData()->getId();
        $playbackId = $video->getData()->getPlaybackIds()[0]->getId();

        // Waiting for Ready status
        do {
            $response = $this->mux->getAsset($assetId);
            if ($response->getData()->getStatus() === 'errored') {
                throw new Exception("Video encountered an error during transcoding.");
            }
            sleep(1);
        } while ($response->getData()->getStatus() !== 'ready');

        // Compute EncodingTime measure
        $encodingTime = microtime(true) - $startEncodingTime;

        // Build hls stream url
        $playbackUrl = "https://stream.mux.com/$playbackId.m3u8";

        // Download master hls manifest
        $masterManifestHls = file_get_contents($playbackUrl);

        // Extract url of the first playlist manifest
        preg_match('/https:\/\/.*/', $masterManifestHls, $matchMasterHls);
        $firstQualityHlsPlaylistUrl = $matchMasterHls[0];

        // Download first playlist hls manifest
        $firstQualityHls = file_get_contents($firstQualityHlsPlaylistUrl);

        // Extract url of the first fragment
        preg_match('/https:\/\/.*/', $firstQualityHls, $matchPlaylistHls);

        // Download first video fragment
        file_get_contents($matchPlaylistHls[0]);

        // Compute TimeToPlayback measure
        $timeToPlayback = microtime(true) - $startTimeToPlayback;

        // Checking all encoding are done
        do {
            $response = $this->mux->getAsset($assetId);

            sleep(1);
        } while ($response->getData()->getStaticRenditions()->getStatus() !== 'ready');

        // Compute FullEncodingTime measure
        $fullEncodingTime = microtime(true) - $startEncodingTime;

        // Delete video
        $this->mux->deleteAsset($assetId);

        return [
            'TimeToEncodeFirstQuality' => $encodingTime,
            'FullTimeToEncode' => $fullEncodingTime,
            'TimeToPlayback' => $timeToPlayback,
        ];
    }

    /**
     * @throws ApiException
     */
    #[ArrayShape(['TimeToPlayback' => ""])]
    public function performLive(string $videoUriPath): array
    {

        // Create Live Stream
        $createAssetRequest = new CreateAssetRequest(["playback_policy" => [PlaybackPolicy::_PUBLIC]]);
        $createLiveStreamRequest = new CreateLiveStreamRequest(["playback_policy" => [PlaybackPolicy::_PUBLIC], "new_asset_settings" => $createAssetRequest]);
        $live = $this->muxLive->createLiveStream($createLiveStreamRequest);

        $assetId = $live->getData()->getId();
        $playbackId = $live->getData()->getPlaybackIds()[0]->getId();

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
            "rtmp://global-live.mux.com:5222/app/{$live->getData()->getStreamKey()}"
        ]);

        // Start TimeToPlayback measure
        $startTimeToPlayback = microtime(true);
        $process->start();

        // Download master hls manifest

        // Build hls stream url
        $playbackUrl = "https://stream.mux.com/$playbackId.m3u8";
        do {
            $file_headers = get_headers($playbackUrl);
            sleep(1);
        } while (strpos($file_headers[0], '412') !== false);
        $masterManifestHls = file_get_contents($playbackUrl);

        // Extract url of the first playlist manifest
        preg_match('/https:\/\/.*/', $masterManifestHls, $matchMasterHls);
        $firstQualityHlsPlaylistUrl = $matchMasterHls[0];

        // Download first playlist hls manifest
        $firstQualityHls = file_get_contents($firstQualityHlsPlaylistUrl);

        // Extract url of the first fragment
        preg_match('/https:\/\/.*/', $firstQualityHls, $matchPlaylistHls);

        // Download first video fragment
        file_get_contents($matchPlaylistHls[0]);

        // Compute TimeToPlayback measure
        $timeToPlayback = microtime(true) - $startTimeToPlayback;

        $process->stop(5, SIGINT);

        // Delete live
        do {
            $response = $this->muxLive->getLiveStream($assetId);
            sleep(1);
        } while ($response->getData()->getStatus() === 'active');

        $this->muxLive->deleteLiveStream($assetId);

        return [
            'TimeToPlayback' => $timeToPlayback,
        ];
    }
}

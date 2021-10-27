<?php


namespace App\Service;


use ApiVideo\Client\Model\LiveStreamCreationPayload;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Vimeo\Exceptions\VimeoRequestException;
use Vimeo\Vimeo;

class BenchVimeoService
{

    public function __construct(private Vimeo $vimeo)
    {
    }

    /**
     * @throws VimeoRequestException
     * @throws Exception
     */
    #[ArrayShape(['TimeToEncodeFirstQuality' => "", 'FullTimeToEncode' => "", 'TimeToPlayback' => "null"])]
    public function performVod(string $videoUriPath): array
    {
        // Start TimeToPlayback measure
        $startTimeToPlayback = microtime(true);

        // Start EncodingTime measure
        $startEncodingTime = microtime(true);

        // Upload/Download video file
        $video = $this->vimeo->request(
            '/me/videos',
            [
                'upload' => [
                    'approach' => 'pull',
                    'link' => $videoUriPath
                ],
            ],
            'POST'

        );

        // Waiting for Ready status
        do {
            $response = $this->vimeo->request($video['body']['uri'] . '?fields=transcode.status,is_playable,files');

            if ($response['body']['transcode']['status'] === 'error') {
                throw new Exception('Vimeo encoding error');
            }
            sleep(3);
        } while ($response['body']['is_playable'] === false);

        // Compute EncodingTime measure
        $encodingTime = microtime(true) - $startEncodingTime;

        // Retrieve HLS URL
        $files = $response['body']['files'];
        $masterManifestHlsUrl = array_values(array_filter($files, function ($v) {
            return $v['quality'] === 'hls';
        }));
        $masterManifestHlsUrl = $masterManifestHlsUrl[0]['link'];

        // Download master hls manifest
        $masterManifestHls = file_get_contents($masterManifestHlsUrl);

        // Extract url of the first playlist manifest
        preg_match('/https:\/\/.*/', $masterManifestHls, $matchMasterHls);
        $firstQualityHlsPlaylistUrl = $matchMasterHls[0];

        // Download first playlist hls manifest
        $firstQualityHls = file_get_contents($firstQualityHlsPlaylistUrl);

        // Extract url of the first fragment
        preg_match('/.*.ts/', $firstQualityHls, $matchPlaylistHls);

        // Build fragment url
        $fragmentShortUrl = $matchPlaylistHls[0];
        $fragmentBaseUrl = explode('/', $firstQualityHlsPlaylistUrl);
        array_pop($fragmentBaseUrl);
        $fragmentUrl = implode('/', $fragmentBaseUrl) . '/' . $fragmentShortUrl;

        // Download first video fragment
        file_get_contents($fragmentUrl);

        // Compute TimeToPlayback measure
        $timeToPlayback = microtime(true) - $startTimeToPlayback;

        $this->vimeo->request($video['body']['uri'], [], 'DELETE');

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

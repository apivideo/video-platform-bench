<?php


namespace App\Controller;


use App\Service\BenchApiVideoService;
use App\Service\BenchAWSService;
use App\Service\BenchCloudflareStreamService;
use App\Service\BenchJWPlayerService;
use App\Service\BenchMuxService;
use App\Service\BenchVimeoService;
use App\Service\BenchYoutubeService;
use Exception;
use MuxPhp\ApiException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class BenchLivePlatformController extends AbstractController
{

    public function __construct(
        private BenchMuxService $muxBench,
        private BenchApiVideoService $apivideoBench,
        private BenchYoutubeService $youtubeBench,
        private BenchJWPlayerService $jwPlayerBench,
        private BenchAWSService $awsBench,
        private BenchVimeoService $vimeoBench,
        private BenchCloudflareStreamService $cloudflareBench
    )
    {
    }

    /**
     * @param string $videoUriPath
     * @return JsonResponse
     * @throws ApiException
     * @throws Exception
     */
    public function benchmark(string $videoUriPath): JsonResponse
    {
        $filePath = sys_get_temp_dir() . '/' . basename($videoUriPath);
        file_put_contents(
            $filePath,
            file_get_contents($videoUriPath)
        );

        // Mux bench
        $muxResult = $this->muxBench->performLive($filePath);

        // api.video bench
        $apivideoResult = $this->apivideoBench->performLive($filePath);

        // Youtube bench
        $ytResult = $this->youtubeBench->performLive($filePath);

        // JW Player bench
        $jwPlayerResult = $this->jwPlayerBench->performLive($filePath);

        // AWS MediaConvert Bench
        $awsResult = $this->awsBench->performLive($filePath);

        // Vimeo Benchmark
        $vimeoResult = $this->vimeoBench->performLive($filePath);

        // Clouflare Stream Benchmark
        $cloudflareResult = $this->cloudflareBench->performLive($filePath);

        $benchmark = [
            'date' => date('c'),
            'mux' => $muxResult,
            'api.video' => $apivideoResult,
            'youtube' => $ytResult,
            'jwplayer' => $jwPlayerResult,
            'aws' => $awsResult,
            'vimeo' => $vimeoResult,
            'cloudflare' => $cloudflareResult,
        ];

        // Delete tmp file
        unlink($filePath);

        return new JsonResponse($benchmark);
    }
}

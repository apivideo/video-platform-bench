<?php


namespace App\Controller;


use App\Service\BenchCloudflareStreamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class BenchCloudflareController extends AbstractController
{
    private array $results = [];

    public function __construct(private BenchCloudflareStreamService $cloudflareStreamBench)
    {
    }

    /**
     * @param string $videoUriPath
     * @return JsonResponse
     */
    public function benchmark(string $videoUriPath): JsonResponse
    {
        $this->results['date'] = date('c');

        // Cloudflare Stream Benchmark
        $this->results['cloudflare'] = $this->cloudflareStreamBench->performVod($videoUriPath);

        return new JsonResponse($this->results);
    }
}

<?php


namespace App\Controller;


use App\Service\BenchMuxService;
use MuxPhp\ApiException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class BenchMuxController extends AbstractController
{
    private array $results = [];

    public function __construct(private BenchMuxService $muxBench)
    {
    }

    /**
     * @param string $videoUriPath
     * @return JsonResponse
     * @throws ApiException
     */
    public function benchmark(string $videoUriPath): JsonResponse
    {
        $this->results['date'] = date('c');

        // Mux bench
        $this->results['mux'] = $this->muxBench->performVod($videoUriPath);

        return new JsonResponse($this->results);
    }
}

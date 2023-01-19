<?php


namespace App\Controller;


use App\Service\BenchApiVideoService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class BenchApiVideoController extends AbstractController
{
    private array $results = [];

    public function __construct(private BenchApiVideoService $apivideoBench)
    {
    }

    /**
     * @param string $videoUriPath
     * @return JsonResponse
     * @throws Exception
     */
    public function benchmark(string $videoUriPath): JsonResponse
    {
        $this->results['date'] = date('c');

        // api.video bench
        $this->results['api.video'] = $this->apivideoBench->performVod($videoUriPath);

        return new JsonResponse($this->results);
    }
}

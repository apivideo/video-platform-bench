<?php


namespace App\Controller;


use App\Service\BenchJWPlayerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class BenchJWPlayerController extends AbstractController
{
    private array $results = [];

    public function __construct(private BenchJWPlayerService $jwPlayerBench)
    {
    }

    /**
     * @param string $videoUriPath
     * @return JsonResponse
     */
    public function benchmark(string $videoUriPath): JsonResponse
    {
        $this->results['date'] = date('c');

        // JW Player bench
        $this->results['jwplayer'] = $this->jwPlayerBench->performVod($videoUriPath);

        return new JsonResponse($this->results);
    }
}

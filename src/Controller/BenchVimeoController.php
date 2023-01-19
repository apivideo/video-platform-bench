<?php


namespace App\Controller;


use App\Service\BenchVimeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Vimeo\Exceptions\VimeoRequestException;

class BenchVimeoController extends AbstractController
{
    private array $results = [];

    public function __construct(private BenchVimeoService $vimeoBench,)
    {
    }

    /**
     * @param string $videoUriPath
     * @return JsonResponse
     * @throws VimeoRequestException
     */
    public function benchmark(string $videoUriPath): JsonResponse
    {
        $this->results['date'] = date('c');

        // Vimeo Benchmark
        $this->results['vimeo'] = $this->vimeoBench->performVod($videoUriPath);

        return new JsonResponse($this->results);
    }
}

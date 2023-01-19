<?php


namespace App\Controller;


use App\Service\BenchAWSService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class BenchAWSController extends AbstractController
{
    private array $results = [];

    public function __construct(private BenchAWSService $awsBench)
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

        // AWS MediaConvert Bench
        $this->results['aws'] = $this->awsBench->performVod($videoUriPath);

        return new JsonResponse($this->results);
    }
}

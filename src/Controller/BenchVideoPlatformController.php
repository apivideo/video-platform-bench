<?php


namespace App\Controller;


use App\Service\BenchApiVideoService;
use App\Service\BenchAWSService;
use App\Service\BenchJWPlayerService;
use App\Service\BenchMuxService;
use App\Service\BenchVimeoService;
use App\Service\BenchYoutubeService;
use Google\Exception;
use Google_Client;
use MuxPhp\ApiException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Vimeo\Exceptions\VimeoRequestException;

class BenchVideoPlatformController extends AbstractController
{
    private array $results = [];

    public function __construct(private CacheInterface $cache, private Google_Client $googleClient, private BenchMuxService $muxBench, private BenchApiVideoService $apivideoBench, private BenchYoutubeService $youtubeBench, private BenchJWPlayerService $jwPlayerBench, private BenchAWSService $awsBench, private BenchVimeoService $vimeoBench)
    {
    }

    /**
     * @param string $videoUriPath
     * @return JsonResponse
     * @throws ApiException
     * @throws VimeoRequestException
     */
    public function benchmark(string $videoUriPath): JsonResponse
    {

        // Init client and authorizations config
        $this->googleClient->setScopes([
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly',
            'https://www.googleapis.com/auth/youtube'
        ]);
        $cached_token = $this->cache->getItem('google_access_token');
        if ($cached_token->isHit()){
            $this->youtubeBench->setAccessToken($cached_token->get());
        }else{
            print("Not authenticate to Google API \n");
            $this->googleClient->setAccessType('offline');
            $this->googleClient->setPrompt("consent");
            $this->googleClient->setIncludeGrantedScopes(true);

            // Request authorization from the user.
            $authUrl = $this->googleClient->createAuthUrl();
            printf("Open this link in your browser and then relaunch the command:\n%s\n", $authUrl);
            exit(0);
        }

        $this->results['date'] =  date('c');

        // Mux bench
        $this->results['mux'] = $this->muxBench->performVod($videoUriPath);

        // api.video bench
        $this->results['api.video'] = $this->apivideoBench->performVod($videoUriPath);

        // Youtube bench
        $this->results['youtube'] = $this->youtubeBench->performVod($videoUriPath);

        // JW Player bench
        $this->results['jwplayer'] = $this->jwPlayerBench->performVod($videoUriPath);

        // AWS MediaConvert Bench
        $this->results['aws'] = $this->awsBench->performVod($videoUriPath);

        // Vimeo Benchmark
        $this->results['vimeo'] = $this->vimeoBench->performVod($videoUriPath);

        return new JsonResponse($this->results);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/youtube-auth-callback', name: 'youtube-auth-callback')]
    public function handleYoutubeAuthCallback(Request $request): Response
    {
        $authCode = $request->query->get('code');
        $accessToken = $this->googleClient->fetchAccessTokenWithAuthCode($authCode);
        $token = $this->cache->getItem('google_access_token');
        $token->set($accessToken);
        $this->cache->save($token);

        return new Response('Authenticated, you can relaunch the command');
    }
}

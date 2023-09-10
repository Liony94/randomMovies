<?php

namespace App\Controller;

use App\Entity\Serie;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FindSerieController extends AbstractController
{
    private HttpClientInterface $client;
    private $currentPage = 1;
    private $lastPage = 3;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    #[Route('/findSerie', name: 'app_find_serie')]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $allSeries = $this->fetchSeriesFromEndpoints();
        $filteredSeries = $this->filterSeries($allSeries, $user, $em);
        $currentSerie = reset($filteredSeries);
        $genres = $this->fetchSerieGenres();

        return $this->render('find/serie.html.twig', [
            'serie' => $currentSerie,
            'genres' => $genres,
            'userIsLoggedIn' => null !== $user
        ]);
    }

    #[Route('/serie_details/{serieDbId}', name: 'app_serie_details')]
    public function getSerieDetails(int $serieDbId): JsonResponse
    {
        $serieDetails = $this->fetchSerieDetails($serieDbId);
        $serieVideos = $this->fetchSerieVideos($serieDbId);

        return new JsonResponse([
            'serie_details' => $serieDetails,
            'serie_videos' => $serieVideos
        ]);
    }

    #[Route('/action_serie/{type}/{serieDbId}', name: 'app_action_serie')]
    public function handleAction(string $type, int $serieDbId, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $serie = $this->findOrCreateSerie($serieDbId, $em);
        $matchedUsers = [];

        if (null === $user || null === $serie) {
            return new JsonResponse(['status' => 'error']);
        }

        if ($user instanceof User) {
            $this->updateUserAction($type, $user, $serie, $em);
        }

        if ('like' === $type) {
            foreach ($user->getFriends() as $friend) {
                if ($friend->hasLikedSerie($serie)) {
                    $matchedUsers[] = $friend->getUsername();
                }
            }
        }

        return new JsonResponse(['status' => 'success', 'matched_users' => $matchedUsers]);
    }

    #[Route('/random_serie', name: 'app_random_serie')]
    public function randomSerie(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $allSeries = $this->fetchSeriesFromEndpoints();
        $filteredSeries = $this->filterSeries($allSeries, $user, $em);
        $nextRandomSerie = reset($filteredSeries);
        $genres = $this->fetchSerieGenres();

        return new JsonResponse(['next_serie' => $nextRandomSerie, 'genres' => $genres]);
    }

    private function fetchSeriesFromEndpoints(): array
    {
        $endpoints = ['top_rated', 'popular'];
        $allSeries = [];

        foreach ($endpoints as $endpoint) {
            for ($page = $this->currentPage; $page <= $this->lastPage; $page++) {
                $response = $this->client->request('GET', "https://api.themoviedb.org/3/tv/$endpoint", [
                    'query' => [
                        'api_key' => $_ENV['TMDB_API_KEY'],
                        'language' => 'fr-FR',
                        'page' => $page,
                    ],
                ]);
                $data = $response->toArray();
                $allSeries = array_merge($allSeries, $data['results']);
            }
            $this->currentPage = $this->lastPage + 1;
            $this->lastPage += 3;
        }
//        dd($allSeries);

        return $allSeries;
    }

    #[Route('/load_more_series', name: 'app_load_more_series')]
    public function loadMoreSeries(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $allSeries = $this->fetchSeriesFromEndpoints();
        $filteredSeries = $this->filterSeries($allSeries, $user, $em);

        return new JsonResponse(['new_series' => $filteredSeries]);
    }

    private function filterSeries(array $series, ?User $user, EntityManagerInterface $em): array
    {
        $watchedSeries = $dislikedSeries = [];

        if (null !== $user) {
            foreach ($user->getSeries() as $serie) {
                $watchedSeries[] = $serie->getSerieDbId();
            }
            foreach ($user->getDislikeSeries() as $serie) {
                $dislikedSeries[] = $serie->getSerieDbId();
            }
        }

        return array_filter($series, function ($serie) use ($watchedSeries, $dislikedSeries) {
            return !in_array($serie['id'], $watchedSeries) && !in_array($serie['id'], $dislikedSeries);
        });
    }

    private function fetchSerieDetails(int $serieDbId): array
    {
        $response = $this->client->request('GET', "https://api.themoviedb.org/3/tv/$serieDbId", [
            'query' => [
                'api_key' => $_ENV['TMDB_API_KEY'],
                'language' => 'fr-FR',
            ],
        ]);
        return $response->toArray();
    }

    private function fetchSerieVideos(int $serieDbId): array
    {
        $response = $this->client->request('GET', "https://api.themoviedb.org/3/tv/$serieDbId/videos", [
            'query' => [
                'api_key' => $_ENV['TMDB_API_KEY'],
                'language' => 'fr-FR',
            ],
        ]);

        return $response->toArray();
    }

    private function findOrCreateSerie(int $serieDbId, EntityManagerInterface $em): ?Serie
    {
        $serie = $em->getRepository(Serie::class)->findOneBy(['serieDbId' => $serieDbId]);
        if (null === $serie) {
            $serie = new Serie();
            $serie->setSerieDbId($serieDbId);

            $details = $this->fetchSerieDetails($serieDbId);
            $serie->setTitle($details['name']);
            $serie->setPosterPath($details['poster_path']);
            $serie->setDescription($details['overview']);

            $videos = $this->fetchSerieVideos($serieDbId);
            if (!empty($videos['results'])) {
                $serie->setTrailerUrl($videos['results'][0]['key']);
            }

            $em->persist($serie);
            $em->flush();
        }
        return $serie;
    }

    private function updateUserAction(string $type, User $user, Serie $serie, EntityManagerInterface $em): void
    {
        if ('like' === $type) {
            $user->addSeries($serie);
        } elseif ('dislike' === $type) {
            $user->addDislikeSeries($serie);
        }

        $em->persist($user);
        $em->flush();
    }

    private function fetchSerieGenres(): array
    {
        $response = $this->client->request('GET', 'https://api.themoviedb.org/3/genre/tv/list', [
            'query' => [
                'api_key' => $_ENV['TMDB_API_KEY'],
                'language' => 'fr-FR',
            ],
        ]);

        $data = $response->toArray();
        $genres = [];

        foreach ($data['genres'] as $genre) {
            $genres[$genre['id']] = $genre['name'];
        }

        return $genres;
    }
}
<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FindMovieController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    #[Route('/findMovie', name: 'app_find_movie')]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $allMovies = $this->fetchMoviesFromEndpoints();
        $filteredMovies = $this->filterMovies($allMovies, $user, $em);
        $currentMovie = reset($filteredMovies);
        $genres = $this->fetchMovieGenres();

        return $this->render('find/movie.html.twig', [
            'movie' => $currentMovie,
            'genres' => $genres,
            'userIsLoggedIn' => null !== $user
        ]);
    }

    #[Route('/movie_details/{movieDbId}', name: 'app_movie_details')]
    public function getMovieDetails(int $movieDbId): JsonResponse
    {
        $movieDetails = $this->fetchMovieDetails($movieDbId);
        $movieVideos = $this->fetchMovieVideos($movieDbId);

        return new JsonResponse([
            'movie_details' => $movieDetails,
            'movie_videos' => $movieVideos
        ]);
    }

    #[Route('/action/{type}/{movieDbId}', name: 'app_action')]
    public function handleAction(string $type, int $movieDbId, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $movie = $this->findOrCreateMovie($movieDbId, $em);

        if (null === $user || null === $movie) {
            return new JsonResponse(['status' => 'error']);
        }

        $this->updateUserAction($type, $user, $movie, $em);

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/random_movie', name: 'app_random_movie')]
    public function randomMovie(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $allMovies = $this->fetchMoviesFromEndpoints();
        $filteredMovies = $this->filterMovies($allMovies, $user, $em);
        $nextRandomMovie = reset($filteredMovies);
        $genres = $this->fetchMovieGenres();

        return new JsonResponse(['next_movie' => $nextRandomMovie, 'genres' => $genres]);
    }

    private function fetchMoviesFromEndpoints(): array
    {
        $endpoints = ['popular', 'now_playing', 'top_rated', 'upcoming'];
        $allMovies = [];

        foreach ($endpoints as $endpoint) {
            $response = $this->client->request('GET', "https://api.themoviedb.org/3/movie/$endpoint", [
                'query' => [
                    'api_key' => $_ENV['TMDB_API_KEY'],
                    'language' => 'fr-FR',
                    'page' => 1,
                ],
            ]);
            $data = $response->toArray();
            $allMovies = array_merge($allMovies, $data['results']);
        }

        return $allMovies;
    }

    private function filterMovies(array $movies, ?User $user, EntityManagerInterface $em): array
    {
        $watchedMovies = $dislikedMovies = [];

        if (null !== $user) {
            foreach ($user->getWatchedMovies() as $movie) {
                $watchedMovies[] = $movie->getMovieDbId();
            }
            foreach ($user->getDislikeMovies() as $movie) {
                $dislikedMovies[] = $movie->getMovieDbId();
            }
        }

        return array_filter($movies, function ($movie) use ($watchedMovies, $dislikedMovies) {
            return !in_array($movie['id'], $watchedMovies) && !in_array($movie['id'], $dislikedMovies);
        });
    }

    private function fetchMovieDetails(int $movieDbId): array
    {
        $response = $this->client->request('GET', "https://api.themoviedb.org/3/movie/$movieDbId", [
            'query' => [
                'api_key' => $_ENV['TMDB_API_KEY'],
                'language' => 'fr-FR',
            ],
        ]);
        return $response->toArray();
    }

    private function fetchMovieVideos(int $movieDbId): array
    {
        $response = $this->client->request('GET', "https://api.themoviedb.org/3/movie/$movieDbId/videos", [
            'query' => [
                'api_key' => $_ENV['TMDB_API_KEY'],
                'language' => 'fr-FR',
            ],
        ]);

        return $response->toArray();
    }

    private function findOrCreateMovie(int $movieDbId, EntityManagerInterface $em): ?Movie
    {
        $movie = $em->getRepository(Movie::class)->findOneBy(['movieDbId' => $movieDbId]);
        if (null === $movie) {
            $movie = new Movie();
            $movie->setMovieDbId($movieDbId);

            $details = $this->fetchMovieDetails($movieDbId);
            $movie->setTitle($details['title']);
            $movie->setPosterPath($details['poster_path']);
            $movie->setDescription($details['overview']);

            $videos = $this->fetchMovieVideos($movieDbId);
            if (!empty($videos['results'])) {
                $movie->setTrailerUrl($videos['results'][0]['key']);
            }

            $em->persist($movie);
            $em->flush();
        }
        return $movie;
    }

    private function updateUserAction(string $type, User $user, Movie $movie, EntityManagerInterface $em): void
    {
        if ('like' === $type) {
            $user->addWatchedMovie($movie);
        } elseif ('dislike' === $type) {
            $user->addDislikeMovie($movie);
        }

        $em->persist($user);
        $em->flush();
    }

    private function fetchMovieGenres(): array
    {
        $response = $this->client->request('GET', 'https://api.themoviedb.org/3/genre/movie/list', [
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
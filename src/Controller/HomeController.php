<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class HomeController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request, Security $security, EntityManagerInterface $em): Response
    {
        $user = $security->getUser();

        $likedMovies = [];
        if ($user instanceof User) {
            foreach ($user->getWatchedMovies() as $movie) {
                $likedMovies[] = $movie->getMovieDbId();
            }
        }

        $endpoints = ['popular', 'now_playing', 'top_rated', 'upcoming'];
        $allMovies = [];

        foreach ($endpoints as $endpoint) {
            $response = $this->client->request('GET', 'https://api.themoviedb.org/3/movie/' . $endpoint, [
                'query' => [
                    'api_key' => $_ENV['TMDB_API_KEY'],
                    'language' => 'fr-FR',
                    'page' => 1,
                ],
            ]);

            $data = $response->toArray();
            $allMovies = array_merge($allMovies, $data['results']);
        }

        $allMovies = array_filter($allMovies, function($movie) use ($likedMovies) {
            return !in_array($movie['id'], $likedMovies);
        });

        shuffle($allMovies);

        $currentMovie = reset($allMovies);

        $genres = $this->fetchMovieGenres();

        return $this->render('home/index.html.twig', [
            'movie' => $currentMovie,
            'genres' => $genres,
            'user' => $user,
        ]);
    }

    #[Route('/action/{type}/{movieDbId}', name: 'app_action')]
    public function action(string $type, int $movieDbId, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['status' => 'error']);
        }

        $movie = $em->getRepository(Movie::class)->findOneBy(['movieDbId' => $movieDbId]);

        if ($movie === null) {
            $movie = new Movie();
            $movie->setMovieDbId($movieDbId);
            $em->persist($movie);
            $em->flush();
        }

        if ($user instanceof User && $movie instanceof Movie) {
            if ($type === 'like') {
                $user->addWatchedMovie($movie);
            } elseif ($type === 'dislike') {
                $user->removeWatchedMovie($movie);
            }

            $em->persist($user);
            $em->flush();
        }

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/random_movie', name: 'app_random_movie')]
    public function randomMovie(Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();

        $likedMovies = [];
        if ($user instanceof User) {
            foreach ($user->getWatchedMovies() as $movie) {
                $likedMovies[] = $movie->getMovieDbId();
            }
        }

        $endpoints = ['popular', 'now_playing', 'top_rated', 'upcoming'];
        $allMovies = [];

        foreach ($endpoints as $endpoint) {
            $response = $this->client->request('GET', 'https://api.themoviedb.org/3/movie/' . $endpoint, [
                'query' => [
                    'api_key' => $_ENV['TMDB_API_KEY'],
                    'language' => 'fr-FR',
                    'page' => 1,
                ],
            ]);

            $data = $response->toArray();
            $allMovies = array_merge($allMovies, $data['results']);
        }

        $allMovies = array_filter($allMovies, function($movie) use ($likedMovies) {
            return !in_array($movie['id'], $likedMovies);
        });

        shuffle($allMovies);

        $nextRandomMovie = reset($allMovies);

        return new JsonResponse(['next_movie' => $nextRandomMovie, 'genres' => $this->fetchMovieGenres()]);
    }

    public function fetchMovieGenres(): array
    {
        $response = $this->client->request('GET', 'https://api.themoviedb.org/3/genre/movie/list', [
            'query' => [
                'api_key' => $_ENV['TMDB_API_KEY'],
                'language' => 'fr-FR'
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

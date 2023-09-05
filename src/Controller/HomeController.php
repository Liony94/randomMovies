<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
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
        $user = $security->getUser();  // get currently logged in user

        // Fetch movies already liked by the user
        $likedMovies = [];
        if ($user instanceof User) {
            foreach ($user->getWatchedMovies() as $movie) {
                $likedMovies[] = $movie->getMovieDbId();
            }
        }

        // Fetch new movies from MovieDB API
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

        // Filter out movies that the user has already liked
        $allMovies = array_filter($allMovies, function($movie) use ($likedMovies) {
            return !in_array($movie['id'], $likedMovies);
        });

        shuffle($allMovies);

        // Pick the first movie to display
        $currentMovie = reset($allMovies);

        return $this->render('home/index.html.twig', [
            'movie' => $currentMovie,
        ]);
    }

    #[Route('/action/{type}/{movieDbId}', name: 'app_action')]
    public function action(string $type, int $movieDbId, Security $security, EntityManagerInterface $em): Response
    {
        $user = $security->getUser();  // get currently logged in user

        // Check if the movie already exists in the database
        $movie = $em->getRepository(Movie::class)->findOneBy(['movieDbId' => $movieDbId]);

        // If the movie doesn't exist, create a new Movie entity and save it
        if ($movie === null) {
            $movie = new Movie();
            $movie->setMovieDbId($movieDbId);
            $em->persist($movie);
            $em->flush();
        }

        if ($user instanceof User && $movie instanceof Movie) {
            if ($type === 'like') {
                // Handle the like action
                $user->addWatchedMovie($movie);
            } elseif ($type === 'dislike') {
                // Handle the dislike action
                $user->removeWatchedMovie($movie);
            }

            $em->persist($user);
            $em->flush();
        }

        return $this->redirectToRoute('app_home');
    }
}

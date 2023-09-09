<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserProfileController extends AbstractController
{
    #[Route('/user/profile', name: 'app_user_profile')]
    public function index(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $userWatchedMovies = $user->getWatchedMovies();
        $lastLikedMovie = $userWatchedMovies->last();

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'userWatchedMovies' => $userWatchedMovies,
            'lastLikedMovie' => $lastLikedMovie
        ]);
    }

    #[Route('/user/profile/{id}', name: 'app_user_profile_id', methods: ["GET"])]
    public function showProfile($id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->redirectToLogin();
        }

        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $areFriends = $this->areFriends($currentUser, $user);
        $requestSent = $this->requestSent($currentUser, $user);

        $numberOfFriends = count($user->getFriends());

        return $this->render('user/profileId.html.twig', [
            'user' => $user,
            'areFriends' => $areFriends,
            'requestSent' => $requestSent,
            'numberOfFriends' => $numberOfFriends
        ]);
    }

    private function areFriends(User $user1, User $user2): bool
    {
        return $user1->getFriends()->contains($user2) || $user2->getFriends()->contains($user1);
    }

    private function requestSent(User $sender, User $receiver): bool
    {
        foreach ($sender->getSentFriendRequests() as $request) {
            if ($request->getReceiver() === $receiver) {
                return true;
            }
        }

        foreach ($receiver->getReceivedFriendRequests() as $request) {
            if ($request->getSender() === $sender) {
                return true;
            }
        }

        return false;
    }
}

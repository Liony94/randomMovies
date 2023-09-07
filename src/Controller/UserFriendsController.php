<?php

namespace App\Controller;

use App\Entity\FriendsRequest;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserFriendsController extends AbstractController
{
    #[Route(path: '/user/friends', name: 'app_user_friends_search', methods: ['GET'])]
    public function displayFriends(Request $request, UserRepository $userRepository): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();

        return $this->render('user/friends.html.twig');
    }

    #[Route(path: '/user/friends/list', name: 'app_user_friends_list', methods: ['GET'])]
    public function displayFriendsList(Request $request): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        $friends = $user->getFriends();

        return $this->render('user/friendsList.html.twig', [
            'friends' => $friends,
        ]);
    }

    #[Route(path: '/user/friends/{username}', name: 'app_user_friends_add', methods: ['POST'])]
    public function addFriend(EntityManagerInterface $entityManager, UserRepository $userRepository, string $username): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        if ($user->getUsername() === $username) {
            throw new \InvalidArgumentException('Vous ne pouvez pas vous ajouter vous-même comme ami.');
        }

        $receiver = $userRepository->findOneBy(['username' => $username]);
        if (!$receiver) {
            throw $this->createNotFoundException('L\'utilisateur demandé n\'existe pas.');
        }

        $friendRequest = new FriendsRequest();
        $friendRequest->setSender($user);
        $friendRequest->setReceiver($receiver);

        $entityManager->persist($friendRequest);
        $entityManager->flush();

        return new Response('Demande d\'ami envoyée avec succès.');
    }

    #[Route(path: '/user/search', name: 'app_user_search', methods: ['GET'])]
    public function searchUsers(Request $request, UserRepository $userRepository): JsonResponse
    {
        $query = $request->query->get('query', '');
        $users = $userRepository->searchUsersByName($query);
        $usersData = [];

        $currentUser = $this->getUser();
        $friends = $currentUser->getFriends();
        $sentFriendRequests = $currentUser->getSentFriendRequests();

        foreach ($users as $user) {
            if ($user->getId() !== $currentUser->getId() &&
                !$friends->contains($user) &&
                !$sentFriendRequests->exists(function($key, $element) use ($user) {
                    return $element->getReceiver() === $user;
                })) {
                $usersData[] = [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'image' => $user->getProfileImage(),
                ];
            }
        }

        return $this->json($usersData);
    }

    #[Route(path: '/user/friends/requests', name: 'app_user_friends_requests', methods: ['GET'])]
    public function displayFriendRequests(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        $receivedFriendRequests = $user->getReceivedFriendRequests();

        return $this->render('user/friendsRequest.html.twig', [
            'receivedFriendRequests' => $receivedFriendRequests,
        ]);
    }

    #[Route(path: '/user/friends/requests/{id}/accept', name: 'app_user_friends_requests_accept', methods: ['POST'])]
    public function acceptFriendRequest(EntityManagerInterface $entityManager, int $id): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $friendRequest = $entityManager->getRepository(FriendsRequest::class)->find($id);
        if (!$friendRequest) {
            throw $this->createNotFoundException('La demande d\'ami n\'existe pas.');
        }

        $friendRequest->setAccepted(true);
        $entityManager->flush();

        return $this->redirectToRoute('app_user_friends_list');
    }
}
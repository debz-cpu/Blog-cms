<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Comment;
use App\Form\Type\PostType;
use App\Form\Type\CommentType;
use App\Repository\PostRepository;
use App\Repository\CommentRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PostController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/posts', name: 'post_index')]
    public function index(PostRepository $postRepository): Response
    {
        return $this->render('post/index.html.twig', [
            'posts' => $postRepository->findAll(),
        ]);
    }

    #[Route('/post/{id}', name: 'post_show', requirements: ['id' => '\\d+'])]
    public function show(
        Post $post,
        Request $request,
        EntityManagerInterface $em,
        CommentRepository $commentRepo
    ): Response {
        $comment = new Comment();
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted('ROLE_USER');

            $comment->setUser($this->getUser());
            $comment->setPost($post);
            $comment->setCreatedAt(new \DateTime());
            $comment->setIsApproved(false);

            $em->persist($comment);
            $em->flush();

            return $this->redirectToRoute('post_show', [
                'id' => $post->getId()
            ]);
        }

        $comments = $commentRepo->findBy([
            'post' => $post,
            'isApproved' => true
        ], [
            'createdAt' => 'DESC'
        ]);

        return $this->render('post/show.html.twig', [
            'post' => $post,
            'comments' => $comments,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/post/new', name: 'post_new')]
    public function new(Request $request, EntityManagerInterface $em, FileUploader $fileUploader): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $file = $form->get('imageFile')->getData();

            if ($file) {
                $fileName = $fileUploader->upload($file);
                $post->setImage($fileName);
            }

            $post->setUser($this->getUser());
            $post->setCreatedAt(new \DateTime());
            $post->setUpdatedAt(new \DateTime());

            $em->persist($post);
            $em->flush();

            return $this->redirectToRoute('post_index');
        }

        return $this->render('post/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
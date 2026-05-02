<?php
namespace App\Controller;

use App\Entity\Comment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class CommentController extends AbstractController
{
    #[Route('/comment/delete/{id}', name: 'comment_delete')]
    public function delete(Comment $comment, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $em->remove($comment);
        $em->flush();

        return $this->redirectToRoute('post_index');
    }

    #[Route('/comment/approve/{id}', name: 'comment_approve')]
    public function approve(Comment $comment, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $comment->setIsApproved(true);
        $em->flush();

        return $this->redirectToRoute('post_index');
    }
}
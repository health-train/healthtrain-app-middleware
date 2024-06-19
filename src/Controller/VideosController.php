<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class VideosController extends AbstractController
{

    public function tools_embed(): Response
    {
        return $this->render('videos/tools_embed.html.twig');
    }

    public function tools_video(): Response
    {
        return $this->redirectToRoute('video_tools_embed');
    }
    
    public function embed_sproutvideo(string $video_id, string $key_id): Response
    {
        return $this->render('videos/embed_sproutvideo.html.twig', [
            'video_id' => $video_id,
            'key_id' => $key_id
        ]);
    }
}



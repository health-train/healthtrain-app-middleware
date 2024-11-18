<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class VideosController extends AbstractController
{

    public function __construct(
        private LoggerInterface $logger,
    ) {
        $this->logger = $logger;
    }

    public function tools_embed(): Response
    {
        return $this->render('videos/tools_embed.html.twig');
    }

    public function embed_sproutvideo(string $video_id, string $key_id): Response
    {
        $this->logger->info('Video embed loaded [video_id: ' . $video_id . ']', array('properties' => array('type' => 'videos', 'action' => __FUNCTION__), 'video_Id' => $video_id));
        return $this->render('videos/embed_sproutvideo.html.twig', [
            'video_id' => $video_id,
            'key_id' => $key_id
        ]);
    }
}



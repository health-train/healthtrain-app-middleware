<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class VideosController extends AbstractController
{
    // The constructor automatically handles dependency injection
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function tools_embed(): Response
    {
        // Render the tools_embed template
        return $this->render('videos/tools_embed.html.twig');
    }

    public function embed_sproutvideo(string $video_id, string $key_id): Response
    {
        // Cache key based on video_id and key_id
        $cacheKey = sprintf('video_embed_%s_%s', $video_id, $key_id);

        // Using the filesystem adapter to cache the rendered content
        $cache = new FilesystemAdapter();
        $cachedResponse = $cache->getItem($cacheKey);

        if (!$cachedResponse->isHit()) {

            $this->logger->info(sprintf('Video embed saved to cache [video_id: %s, key_id: %s]', $video_id, $key_id), [
                'properties' => [
                    'type' => 'videos',
                    'action' => 'embed_cache_save'
                ],
                'video_id' => $video_id,
                'key_id' => $key_id
            ]);

            // Render the template if the cache is not hit
            $content = $this->renderView('videos/embed_sproutvideo.html.twig', [
                'video_id' => $video_id,
                'key_id' => $key_id
            ]);

            // Set the cached content with a TTL (Time-to-Live), e.g., 1 hour
            $cachedResponse->set($content);
            $cachedResponse->tag('embed_sproutvideo');
            // $cachedResponse->expiresAfter(3600 * 24 * 7); // Cache for 1 hour
            $cache->save($cachedResponse);
        }

        $this->logger->info(sprintf('Video embed loaded from cache [video_id: %s, key_id: %s]', $video_id, $key_id), [
            'properties' => [
                'type' => 'videos',
                'action' => 'embed_cache_hit'
            ],
            'video_id' => $video_id,
            'key_id' => $key_id
        ]);

        // Return the cached or newly rendered response
        return new Response($cachedResponse->get());
    }
}

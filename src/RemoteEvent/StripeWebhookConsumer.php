<?php

namespace App\RemoteEvent;

use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Psr\Log\LoggerInterface;

#[AsRemoteEventConsumer('stripe')]
final class StripeWebhookConsumer implements ConsumerInterface
{
    public function __construct(private LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function consume(RemoteEvent $event): void
    {
        $this->logger->info('Stripe Webhook received', array('properties' => array('type' => 'webhook_stripe', 'action' => __FUNCTION__), 'event' => $event));
    }
}

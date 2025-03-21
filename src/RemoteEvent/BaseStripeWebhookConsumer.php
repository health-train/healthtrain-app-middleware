<?php

namespace App\RemoteEvent;

use App\Service\ProductService;
use App\Service\HealthTrainPlatformService;
use App\Service\SlackService;
use App\Service\StripeService;
use App\Service\MailPlusService;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Psr\Log\LoggerInterface;

abstract class BaseStripeWebhookConsumer implements ConsumerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private HealthTrainPlatformService $healthTrainPlatformService,
        private ProductService $productService,
        private StripeService $stripeService,
        private MailPlusService $mailPlusService,
        private SlackService $slackService
    ) {}

    public function consume(RemoteEvent $event): void
    {
        $eventType = $event->getName();
        $payload = $event->getPayload();

        $this->logger->info('Stripe Webhook received', [
            'event_type' => $eventType,
            'payload' => $payload
        ]);

        if ($this->isCheckoutSessionEvent($eventType)) {
            $this->processCheckoutSession($payload);
        }
    }

    private function isCheckoutSessionEvent(string $eventType): bool
    {
        return in_array($eventType, ['checkout.session.completed', 'checkout.session.async_payment_succeeded']);
    }

    private function processCheckoutSession(array $payload): void
    {
        $configKey = $this->getConfigKey();
        $this->stripeService->handleCheckoutSessionCompleted(
            $payload['data']['object']['id'],
            $this->productService->getConfig($configKey),
            $payload['livemode']
        );
    }

    abstract protected function getConfigKey(): string;
}

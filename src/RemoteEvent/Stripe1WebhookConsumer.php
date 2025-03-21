<?php

namespace App\RemoteEvent;

use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;

#[AsRemoteEventConsumer('stripe1')]
final class Stripe1WebhookConsumer extends BaseStripeWebhookConsumer
{
    protected function getConfigKey(): string
    {
        return 'ht1_livemode';
    }
}

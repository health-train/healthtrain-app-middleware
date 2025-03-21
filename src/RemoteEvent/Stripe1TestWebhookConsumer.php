<?php

namespace App\RemoteEvent;

use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;

#[AsRemoteEventConsumer('stripe1test')]
final class Stripe1TestWebhookConsumer extends BaseStripeWebhookConsumer
{
    protected function getConfigKey(): string
    {
        return 'ht1_testmode';
    }
}

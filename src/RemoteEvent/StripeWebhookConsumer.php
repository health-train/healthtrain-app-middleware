<?php

namespace App\RemoteEvent;

use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;

#[AsRemoteEventConsumer('stripe')]
final class StripeWebhookConsumer extends BaseStripeWebhookConsumer
{
    protected function getConfigKey(): string
    {
        return 'ht2_livemode';
    }
}

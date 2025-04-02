<?php

namespace App\RemoteEvent;

use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;

#[AsRemoteEventConsumer('stripetest')]
final class StripeTestWebhookConsumer extends BaseStripeWebhookConsumer
{
    protected function getConfigKey(): string
    {
        return 'ht2_testmode';
    }
}

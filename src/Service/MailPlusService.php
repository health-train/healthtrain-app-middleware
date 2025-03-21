<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use App\OAuth1\OAuth1Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class MailPlusService
{
    private const BASE_URL = 'https://restapi.mailplus.nl/integrationservice/';

    public function __construct(
        private LoggerInterface $logger,
        private HttpClientInterface $client,
        private HttpClientInterface $oauthClient
    ) {
        if (empty($_ENV['SPOTLER_CONSUMER_KEY']) || empty($_ENV['SPOTLER_CONSUMER_SECRET'])) {
            throw new \RuntimeException('Missing required Spotler environment variables.');
        }
    }

    public function triggerAutomation($customer, $product): bool
    {
        $OAuthRequest = new OAuth1Request($_ENV['SPOTLER_CONSUMER_KEY'], $_ENV['SPOTLER_CONSUMER_SECRET']);

        // 🟢 Contact toevoegen of bijwerken
        $mailPlusContactParams = [
            "update" => true,
            "purge" => false,
            'contact' => [
                'externalId' => $customer->id,
                'properties' => [
                    'email' => $customer->email,
                    'firstName' => $customer->metadata->organisation_contact_name,
                    'organisation' => $customer->name,
                    "permissions" => [["bit" => 4, "enabled" => true]]
                ]
            ]
        ];

        try {
            $OAuthRequest->request(
                $this->oauthClient,
                'POST',
                self::BASE_URL . 'contact',
                [],
                $mailPlusContactParams
            );
        } catch (HttpExceptionInterface $e) {
            $this->logger->error("Failed to create/update contact: " . $e->getMessage(), [
                'type' => 'checkout',
                'action' => __FUNCTION__,
                'customer_id' => $customer->id,
                'body' => $mailPlusContactParams,
                'exception' => $e
            ]);
            return false;
        }

        // 🟢 Automatisering activeren
        $mailPlusAutomationParams = ['externalContactId' => $customer->id];

        try {
            $OAuthRequest->request(
                $this->oauthClient,
                'POST',
                self::BASE_URL . 'automation/trigger/' . $product->mailplus->automationId,
                [],
                $mailPlusAutomationParams
            );

            $this->logger->info("Automation triggered for customer: {$customer->id}", [
                'type' => 'checkout',
                'action' => __FUNCTION__,
                'customer_id' => $customer->id
            ]);

            return true;
        } catch (HttpExceptionInterface $e) {
            $this->logger->error("Failed to trigger automation: " . $e->getMessage(), [
                'type' => 'checkout',
                'action' => __FUNCTION__,
                'customer_id' => $customer->id,
                'exception' => $e
            ]);
        }

        return false;
    }
}

<?php

// src/Service/StripeService.php
namespace App\Service;

use Psr\Log\LoggerInterface;
use App\OAuth1\OAuth1Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MailPlusService
{

    public function __construct(
        private LoggerInterface $logger,
        private HttpClientInterface $client,
        private HttpClientInterface $oauthClient
    ) {
        $this->oauthClient = $oauthClient;
        $this->logger = $logger;

        // Initialize
        if (!$_ENV['SPOTLER_CONSUMER_KEY'] || !$_ENV['SPOTLER_CONSUMER_SECRET']) {
            echo 'Missing required env variable';
            exit;
        }
    }
    public function triggerAutomation($customer, $product): bool
    {
        // Subscribe customer to Spotler Permission and trigger automation
        $OAuthRequest = new OAuth1Request($_ENV['SPOTLER_CONSUMER_KEY'], $_ENV['SPOTLER_CONSUMER_SECRET']);
        // Create MailPlus Contact
        try {
            $mailPlusContactParams = [
                "update" => true,
                "purge" => false,
                'contact' => [
                    'externalId' => $customer->id,
                    'properties' => [
                        'email' => $customer->email,
                        'firstName' => $customer->metadata->organisation_contact_name,
                        'organisation' => $customer->name,
                        "permissions" => [
                            [
                                "bit" => 4,
                                "enabled" => true
                            ]
                        ]
                    ]
                ]
            ];
            $OAuthRequest->request($this->oauthClient, 'POST', 'https://restapi.mailplus.nl/integrationservice/contact', [], $mailPlusContactParams);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'customer_id' => $customer->id, 'body' => $mailPlusContactParams, 'exception' => $e));
        }

        // Trigger automation for MailPlus Contact
        try {
            $mailPlusAutomationParams = ['externalContactId' => $customer->id];
            $OAuthRequest->request($this->oauthClient, 'POST', 'https://restapi.mailplus.nl/integrationservice/automation/trigger/'. $product->mailplus->automationId, [], $mailPlusAutomationParams);
            $this->logger->info('Automation triggered ' . $customer->id, array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), $customer->id));
            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'customer_id' => $customer->id, 'body' => $mailPlusAutomationParams, 'exception' => $e));
        }
        return false;
    }
}
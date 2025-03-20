<?php

namespace App\Service;

use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use App\Service\ProductService;

class HealthTrainPlatformService
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private string $endpointAuthenticate = '/Authentication/Authenticate/';
    private string $endpointOrganizations = '/v1/Organizations/';

    public function __construct(
        private LoggerInterface $logger,
        private HttpClientInterface $client,
        private ProductService $productService,
        private SlackService $slackService
    ) {
        $this->productService = $productService;
        $this->slackService = $slackService;
    }


    public function createOrg($customer, $plan): bool|array
    {
        $plan = $this->productService->getPlan($plan);
        $this->setApiClientCredentials($plan['config']);

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \RuntimeException('Missing required environment variables: CLIENT_ID, CLIENT_SECRET');
        }

        // Get auth bearer token
        $token = $this->getAuthToken();
        if (!$token) {
            throw new \RuntimeException('Error authenticating: Check provided client credentials');
        }

        if($this->checkOrganizationExists($customer->metadata->organisation_name)) {
            $this->logger->error("Organization already exists: ". $customer->metadata->organisation_name, [
                'type' => 'checkout',
                'action' => __FUNCTION__,
            ]);
            return false;
        }

        $nameFields = $this->extractNameFields($customer->metadata->organisation_contact_name);

        // Prepare request payload
        $payload = [
            "organization" => [
                "id" => key($plan) === "standalone" ? "htp_standalone_" . random_bytes(10) : null,
                "name" => $customer->metadata->organisation_name,
                "stripeCustomerId" => $customer->id,
                "externalReference" => $customer->metadata->organisation_kvk ? $customer->metadata->organisation_kvk : null,
                "country" => "NLD"
            ],
            "therapistAdmin" => [
                'firstName' => $nameFields['firstName'],
                'lastName' => $nameFields['lastName'],
                'displayName' => $customer->metadata->organisation_contact_name,
                'phoneNumber' => $customer->phone,
                'email' => $customer->email
            ]
        ];

        // Call the onboard API
        try {
            $response = $this->client->request('POST', $this->baseUrl . $this->endpointOrganizations, [
                'auth_bearer' => $token,
                'json' => $payload
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error("Organization onboarding failed: " . $response->getContent(false));
                $this->slackService->sendMessage(['message' => "Organisatie aanmaken mislukt", 'onboarding' => $payload], 'ht_org');
                return false;
            }

            $this->logger->info("Onboarded organisation: {$customer->id}", [
                'type' => 'checkout',
                'action' => __FUNCTION__,
                'customer_id' => $customer->id
            ]);

            $this->slackService->sendMessage(['message' => "Organisatie aangemaakt ✅", 'onboarding' => $payload], 'ht_org');

            return true;
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error("Onboarding request error: " . $e->getMessage(), [
                'type' => 'checkout',
                'action' => __FUNCTION__,
                'exception' => $e
            ]);
            return false;
        }
    }

    private function extractNameFields($name)
    {
        $nameArr   = explode(' ', trim($name));
        $firstName = $nameArr[0] ?? null;
        $lastName  = ($nameArr[1] ?? null) ? implode(' ', array_slice($nameArr, 1)) : null;
        return ['firstName' => $firstName, 'lastName' => $lastName];
    }

    private function checkOrganizationExists($organizationName)
    {
        $organizations = $this->getOrganizations();
        $organizationNames = array_column($organizations, 'name');
        if(!empty($organizationName) && in_array($organizationName, $organizationNames)) return true;
        return false;
    }

    private function getOrganizations(): ?array
    {

        // Get auth bearer token
        $token = $this->getAuthToken();
        if (!$token) {
            throw new \RuntimeException('Error authenticating: Check provided client credentials');
        }

        try {
            $response = $this->client->request('GET', $this->baseUrl . $this->endpointOrganizations, ['auth_bearer' => $token,]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error("getOrganizations failed: " . $response->getContent(false));
                return null;
            }

            $data = $response->toArray();
            return $data ?? null;
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error("getOrganizations error: " . $e->getMessage(), [
                'type' => 'auth',
                'action' => __FUNCTION__,
                'exception' => $e
            ]);
            return null;
        }
    }

    private function getAuthToken(): ?string
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . $this->endpointAuthenticate, [
                'json' => [
                    'clientId' => $this->clientId,
                    'clientSecret' => $this->clientSecret
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error("Authentication failed: " . $response->getContent(false));
                return null;
            }

            $data = $response->toArray();
            return $data['token'] ?? null;
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error("Auth request error: " . $e->getMessage(), [
                'type' => 'auth',
                'action' => __FUNCTION__,
                'exception' => $e
            ]);
            return null;
        }
    }

    private function setApiClientCredentials($config): bool
    {

        if (!$config['HT_API_BASEURL'] || !$config['HT_API_CLIENT_ID'] || !$config['HT_API_CLIENT_SECRET']) {
            return false;
        }
        $this->baseUrl = $_ENV[$config['HT_API_BASEURL']];
        $this->clientId = $_ENV[$config['HT_API_CLIENT_ID']];
        $this->clientSecret = $_ENV[$config['HT_API_CLIENT_SECRET']];
        return true;
    }
}

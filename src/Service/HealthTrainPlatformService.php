<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

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
    ) {}

    public function createOrg(object $customer, string $plan): bool|array
    {
        $planData = $this->productService->getPlan($plan);
        if (!$this->setApiClientCredentials($planData['config'])) {
            throw new \RuntimeException('API credentials ontbreken of onjuist geconfigureerd.');
        }

        $token = $this->getAuthToken();
        if (!$token) {
            throw new \RuntimeException('Authenticatie mislukt: controleer API-credentials.');
        }

        if ($this->checkOrganizationExists($customer->metadata->organisation_name)) {
            $this->logger->error("Organisatie bestaat al: {$customer->metadata->organisation_name}", [
                'type' => 'checkout',
                'action' => __FUNCTION__,
            ]);
            return false;
        }

        $nameFields = $this->extractNameFields($customer->metadata->organisation_contact_name);

        $payload = [
            "organization" => [
                "id" => $plan === "standalone" ? "htp_standalone_" . bin2hex(random_bytes(5)) : null,
                "name" => $customer->metadata->organisation_name,
                "stripeCustomerId" => $customer->id,
                "externalReference" => $customer->metadata->organisation_kvk ?: null,
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

        return $this->sendOnboardingRequest($token, $payload, $customer->id);
    }

    private function extractNameFields(string $name): array
    {
        $nameParts = explode(' ', trim($name), 2);
        return [
            'firstName' => $nameParts[0] ?? '',
            'lastName'  => $nameParts[1] ?? '',
        ];
    }

    private function checkOrganizationExists(string $organizationName): bool
    {
        $organizations = $this->getOrganizations();
        return !empty($organizationName) && in_array($organizationName, array_column($organizations, 'name'), true);
    }

    private function getOrganizations(): array
    {
        $token = $this->getAuthToken();
        if (!$token) {
            throw new \RuntimeException('Authenticatie mislukt bij ophalen organisaties.');
        }

        try {
            $response = $this->client->request('GET', $this->baseUrl . $this->endpointOrganizations, [
                'auth_bearer' => $token,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error("Fout bij ophalen organisaties: " . $response->getContent(false));
                return [];
            }

            return $response->toArray();
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error("getOrganizations fout: " . $e->getMessage(), [
                'type' => 'auth',
                'action' => __FUNCTION__,
                'exception' => $e
            ]);
            return [];
        }
    }

    private function getAuthToken(): string
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . $this->endpointAuthenticate, [
                'json' => [
                    'clientId' => $this->clientId,
                    'clientSecret' => $this->clientSecret
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException("Authenticatie mislukt: " . $response->getContent(false));
            }

            $data = $response->toArray();
            return $data['token'] ?? throw new \RuntimeException('Geen token ontvangen van API.');
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error("Authenticatie mislukt: " . $e->getMessage(), [
                'type' => 'auth',
                'action' => __FUNCTION__,
                'exception' => $e
            ]);
            throw new \RuntimeException('Authenticatie verzoek mislukt.', 0, $e);
        }
    }

    private function setApiClientCredentials(array $config): bool
    {
        if (empty($config['HT_API_BASEURL']) || empty($config['HT_API_CLIENT_ID']) || empty($config['HT_API_CLIENT_SECRET'])) {
            return false;
        }

        $this->baseUrl = $_ENV[$config['HT_API_BASEURL']] ?? '';
        $this->clientId = $_ENV[$config['HT_API_CLIENT_ID']] ?? '';
        $this->clientSecret = $_ENV[$config['HT_API_CLIENT_SECRET']] ?? '';

        return !empty($this->baseUrl) && !empty($this->clientId) && !empty($this->clientSecret);
    }

    private function sendOnboardingRequest(string $token, array $payload, string $customerId): bool
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . $this->endpointOrganizations, [
                'auth_bearer' => $token,
                'json' => $payload
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->error("Onboarding mislukt: " . $response->getContent(false));
                $this->slackService->sendMessage([
                    'message' => "Organisatie aanmaken mislukt ❌",
                    'onboarding' => $payload
                ], 'ht_org');
                return false;
            }

            $this->logger->info("Organisatie aangemaakt: {$customerId}", [
                'type' => 'checkout',
                'action' => __FUNCTION__,
                'customer_id' => $customerId
            ]);

            $this->slackService->sendMessage([
                'message' => "Organisatie aangemaakt ✅",
                'onboarding' => $payload
            ], 'ht_org');

            return true;
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error("Fout bij organisatie aanmaken: " . $e->getMessage(), [
                'type' => 'checkout',
                'action' => __FUNCTION__,
                'exception' => $e
            ]);
            return false;
        }
    }
}

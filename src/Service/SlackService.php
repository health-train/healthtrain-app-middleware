<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SlackService
{
    public function __construct(
        private readonly HttpClientInterface $slackClient,
    ) {
    }

    public function sendMessage(array $data, string $format = "default"): void
    {
        $slackData = match ($format) {
            "ht_org" => $this->formatHtOrgMessage($data),
            "stripe" => $this->formatStripeMessage($data),
            default => ['text' => $data['message'] ?? 'Geen bericht'],
        };

        $this->slackClient->request('POST', '', [
            'json' => $slackData,
        ]);
    }

    private function formatHtOrgMessage(array $data): array
    {
        return [
            'blocks' => [
                [
                    "type" => "section",
                    "text" => [
                        "type" => "mrkdwn",
                        "text" => $data['message'] ?? "Geen bericht"
                    ]
                ],
                [
                    "type" => "section",
                    "fields" => [
                        [
                            "type" => "mrkdwn",
                            "text" => "*Bedrijfsnaam*\n" . ($data['onboarding']['organization']['name'] ?? "Onbekend")
                        ],
                        [
                            "type" => "mrkdwn",
                            "text" => "*Telefoonnummer*\n" . 
                                (isset($data['onboarding']['therapistAdmin']['phoneNumber']) 
                                    ? substr($data['onboarding']['therapistAdmin']['phoneNumber'], 0, 5) . "*****" 
                                    : "Onbekend"
                                )
                        ]
                    ]
                ]
            ]
        ];
    }

    private function formatStripeMessage(array $data): array
    {
        $customerName = $data['customer']['name'] ?? "Onbekend";
        $subscription = $data['subscription']['items']['data'][0] ?? [];
        
        return [
            'blocks' => [
                [
                    "type" => "section",
                    "text" => [
                        "type" => "mrkdwn",
                        "text" => $data['message'] ?? "Geen bericht"
                    ]
                ],
                [
                    "type" => "section",
                    "fields" => [
                        [
                            "type" => "mrkdwn",
                            "text" => "*Bedrijfsnaam*\n" . $customerName
                        ],
                        [
                            "type" => "mrkdwn",
                            "text" => "*Product*\n" . 
                                ($subscription['plan']['nickname'] ?? "Onbekend") . 
                                " [" . ($subscription['price']['lookup_key'] ?? "Onbekend") . "]"
                        ],
                        isset($subscription) ?: [
                            "type" => "mrkdwn",
                            "text" => "*Licenties*\n" . ($subscription['quantity'] ?? "Onbekend")
                        ],
                        isset($subscription) ?: [
                            "type" => "mrkdwn",
                            "text" => "*Status*\n" . ($subscription['status'] ?? "Onbekend")
                        ]
                    ]
                ],
                ["type" => "divider"],
                [
                    "type" => "actions",
                    "elements" => [
                        [
                            "type" => "button",
                            "text" => ["type" => "plain_text", "text" => "Open Stripe Customer"],
                            "url" => "https://dashboard.stripe.com/customers/" . ($data['customer']['id'] ?? "#")
                        ],
                        isset($subscription) ?: [
                            "type" => "button",
                            "text" => ["type" => "plain_text", "text" => "Open Stripe Subscription"],
                            "url" => "https://dashboard.stripe.com/subscriptions/" . ($subscription['id'] ?? "#")
                        ]
                    ]
                ]
            ]
        ];
    }
}

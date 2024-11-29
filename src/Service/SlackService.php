<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SlackService
{
    public function __construct(
        private readonly HttpClientInterface $slackClient,
    ) {
    }

    public function sendMessage($data, $format = "default"): void
    {
        $slackData = [];
        switch ($format) {
            case "stripe":
                $slackData = [
                    'blocks' => [
                        [
                            "type" => "section",
                            "text" => [
                                "type" => "mrkdwn",
                                "text" => $data['message']
                            ]
                        ],
                        [
                            "type" => "section",
                            "fields" => [
                                [
                                    "type" => "mrkdwn",
                                    "text" => "*Bedrijfsnaam*\n" . $data['customer']['name'] ?? "Onbekend"
                                ],
                                [
                                    "type" => "mrkdwn",
                                    "text" => "*Product*\n" . ($data['subscription']['items']['data'][0]['plan']['nickname'] ?? "Onbekend") . "(". ($data['subscription']['items']['data'][0]['price']['lookup_key'] ?? "Onbekend") . ")"
                                ],
                                [
                                    "type" => "mrkdwn",
                                    "text" => "*Licenties*\n" . $data['subscription']['items']['data'][0]['quantity'] ?? "Onbekend"
                                ],
                                [
                                    "type" => "mrkdwn",
                                    "text" => "*Omzet*\n€ " . number_format(($data['subscription']['items']['data'][0]['plan']['amount'] / 100), 2, ",", ".") ?? "Onbekend"
                                ],
                                [
                                    "type" => "mrkdwn",
                                    "text" => "*Status*\n" . $data['subscription']['status'] ?? "Onbekend"
                                ]
                            ]
                        ],
                        [
                            "type" => "divider"
                        ],
                        [
                            "type" => "actions",
                            "elements" => [
                                [
                                    "type" => "button",
                                    "text" => [
                                        "type" => "plain_text",
                                        "text" => "Open Stripe Customer"
                                    ],
                                    "url" => "https://dashboard.stripe.com/customers/" . $data['customer']['id']
                                ],
                                [
                                    "type" => "button",
                                    "text" => [
                                        "type" => "plain_text",
                                        "text" => "Open Stripe Subscription"
                                    ],
                                    "url" => "https://dashboard.stripe.com/subscriptions/" . $data['subscription']['id']
                                ]

                            ]
                        ]
                    ]
                ];
                break;
            default:
                $slackData = [
                    'text' => $data['message']
                ];
                break;
        }

        $this->slackClient->request('POST', '', [
            'json' => $slackData,
        ]);
    }

}
<?php

// src/Service/ProductService.php
namespace App\Service;

class ProductService
{

    private $config = [
        'ht1_intramed' => [
            'HT_API_BASEURL' => 'HEALTHTRAIN_IT_API_BASEURL',
            'HT_API_CLIENT_ID' => 'HEALTHTRAIN_IT_INTRAMED_CLIENT_ID',
            'HT_API_CLIENT_SECRET' => 'HEALTHTRAIN_IT_INTRAMED_CLIENT_SECRET',
            'STRIPE_SECRET_KEY' => 'STRIPE_HT2_TESTMODE_SECRET_KEY',
            'STRIPE_BILLING_URL' => 'STRIPE_HT2_TESTMODE_BILLING_URL',
        ],
        'ht2_spotonmedics' => [
            'HT_API_BASEURL' => 'HEALTHTRAIN_IT_API_BASEURL',
            'HT_API_CLIENT_ID' => 'HEALTHTRAIN_IT_SOM_CLIENT_ID',
            'HT_API_CLIENT_SECRET' => 'HEALTHTRAIN_IT_SOM_CLIENT_SECRET',
            'STRIPE_SECRET_KEY' => 'STRIPE_HT2_TESTMODE_SECRET_KEY',
            'STRIPE_BILLING_URL' => 'STRIPE_HT2_TESTMODE_BILLING_URL',
        ],
        'ht1' => [
            'STRIPE_SECRET_KEY' => 'STRIPE_HT1_SECRET_KEY',
            'STRIPE_BILLING_URL' => 'STRIPE_HT1_BILLING_URL',
        ],
        'ht2' => [
            'STRIPE_SECRET_KEY' => 'STRIPE_HT2_SECRET_KEY',
            'STRIPE_BILLING_URL' => 'STRIPE_HT2_BILLING_URL',
        ],
    ];

    private $products = [
        "htp_live_pCiBce4AF6XXzH2mn" => [
            'healthtrain' => [
                'plan' => 'spotonmedics'
            ],
            'stripe' => [
                'priceId' => 'price_1QZaG0AU7J6SUXutWSzBW6WD',
                'paymentMethods' => 'pmc_1QNGRLAU7J6SUXutucJVChnS'
            ],
            'mailplus' => [
                'automationId' => 'bc0a8795-536c-432b-83ef-bcb46944eb0f'
            ],
        ],
        "htp_test_H8oYvathg6fv4XF2u" => [
            'healthtrain' => [
                'plan' => 'spotonmedics'
            ],
            'stripe' => [
                'priceId' => 'price_1QD4l5AU7J6SUXutqQV2xIkg',
                'paymentMethods' => 'pmc_1QFP2jAU7J6SUXutnqqCXyO3'
            ],
            'mailplus' => [
                'automationId' => '61fa1a1c-ba87-484d-8890-39868d538989'
            ]
        ],
        "htp_live_g8pyaCarYzqGHcQYUpwY" => [
            'healthtrain' => [
                'plan' => 'intramed-pilot'
            ],
            'stripe' => [
                'priceId' => '',
                'paymentMethods' => ''
            ],
            'mailplus' => false
        ],
        "htp_test_3hZ4hzPBCsmsXLmjTebZ" => [
            'healthtrain' => [
                'plan' => 'intramed-pilot'
            ],
            'stripe' => [
                'priceId' => 'price_1R4IF2CQZsgE0X5Z6zqadeFe',
                'paymentMethods' => 'pmc_1QFLFpCQZsgE0X5Z7dZy75Nb'
            ],
            'mailplus' => false
        ],
        'htp_live_yXq6sCHEKvHfAYkrqWCv' => [
            'healthtrain' => [
                'plan' => 'standalone'
            ],
            'stripe' => [
                'priceId' => 'price_1R1YziAU7J6SUXuteXP3MBRG',
                'paymentMethods' => 'pmc_1QNGRLAU7J6SUXutucJVChnS'
            ],
            'mailplus' => false
        ],
        'htp_test_yXq6sCHEKvHfAYkrqWCv' => [
            'healthtrain' => [
                'plan' => 'standalone'
            ],
            'stripe' => [
                'priceId' => 'price_1R1ZeBAU7J6SUXutGcxZcf2H',
                'paymentMethods' => 'pmc_1QFP2jAU7J6SUXutnqqCXyO3'
            ],
            'mailplus' => false
        ],
        'hwo_live_syK2z4Y9Qz8VQYraA' => [
            'healthtrain' => [
                'plan' => 'hwo'
            ],
            'stripe' => [
                'priceId' => 'price_1QgnqxAU7J6SUXut3qZzE0KU',
                'paymentMethods' => 'pmc_1QNGRLAU7J6SUXutucJVChnS'
            ],
            'mailplus' => false
        ],
        'hwo_test_syK2z4Y9Qz8VQYraA' => [
            'healthtrain' => [
                'plan' => 'hwo'
            ],
            'stripe' => [
                'priceId' => 'price_1Qgn0bAU7J6SUXutnjXsDfIF',
                'paymentMethods' => 'pmc_1QFP2jAU7J6SUXutnqqCXyO3'
            ],
            'mailplus' => false
        ],
    ];

    private $plans = [
        "spotonmedics" => [
            'livemode' => [
                'config' => 'ht2_spotonmedics',
                'identifier' => 'HealthTrain SOM',
                'products' => ['htp_live_pCiBce4AF6XXzH2mn'],
                'organisationOnboarding' => false,
                'testmode' => false
            ],
            'testmode' => [
                'config' => 'ht2_spotonmedics',
                'identifier' => 'HealthTrain SOM Testmode',
                'products' => ['htp_test_H8oYvathg6fv4XF2u'],
                'organisationOnboarding' => true,
                'testmode' => true
            ]
        ],
        "intramed-pilot" => [
            'livemode' => [
                'config' => 'ht1',
                'products' => ['htp_live_g8pyaCarYzqGHcQYUpwY'],
                'identifier' => 'HealthTrain Intramed Pilot',
                'organisationOnboarding' => false,
                'testmode' => false
            ],
            'testmode' => [
                'config' => 'ht1_intramed',
                'products' => ['htp_test_3hZ4hzPBCsmsXLmjTebZ'],
                'identifier' => 'HealthTrain Intramed Pilot Testmode',
                'organisationOnboarding' => true,
                'testmode' => true
            ]
        ],
        "standalone" => [
            'livemode' => [
                'config' => 'ht2',
                'products' => ['htp_live_yXq6sCHEKvHfAYkrqWCv'],
                'identifier' => 'HealthTrain Standalone',
                'organisationOnboarding' => false,
                'testmode' => false
            ],
            'testmode' => [
                'config' => 'ht2',
                'products' => ['htp_test_yXq6sCHEKvHfAYkrqWCv'],
                'identifier' => 'HealthTrain Standalone Testmode',
                'organisationOnboarding' => false,
                'testmode' => true
            ]
        ],
        "hwo" => [
            'livemode' => [
                'config' => 'ht2',
                'products' => ['hwo_live_syK2z4Y9Qz8VQYraA'],
                'identifier' => 'Huiswerkoefeningen.nl',
                'organisationOnboarding' => false,
                'testmode' => false
            ],
            'testmode' => [
                'config' => 'ht2',
                'products' => ['hwo_test_syK2z4Y9Qz8VQYraA'],
                'identifier' => 'Huiswerkoefeningen.nl Testmode',
                'organisationOnboarding' => false,
                'testmode' => true
            ]
        ]
    ];

    public function getPlan($planKey, $testmode = true): array|bool
    {
        if (!key_exists($planKey, $this->plans)) {
            throw new \Exception("Plan does not exist: " . $planKey);
        }
        $plan = $testmode ? $this->plans[$planKey]['testmode'] : $this->plans[$planKey]['livemode'];
        $plan['config'] = $this->getConfig($plan['config']);
        if ($plan['testmode'] != $testmode) {
            throw new \Exception("Plan not configured for this mode: " . $planKey);
        }
        $plan['products'] = $this->getProducts($plan['products']);

        if (!$plan || empty($plan['config']) || empty($plan['products'])) {
            throw new \Exception("Plan not configured correctly: " . $planKey);
        }
        return $plan;
    }

    public function getProduct($productId): array|bool
    {
        if (!key_exists($productId, $this->products)) {
            throw new \Exception('Product is not available: ' . $productId);
        }
        return $this->products[$productId];
    }

    public function getProducts($productIds): array|bool
    {
        $productArray = [];
        foreach ($productIds as $productId) {
            $productArray[$productId] = $this->getProduct($productId);
        }
        return $productArray;
    }

    public function getConfig($configKey): array|bool
    {
        if (!key_exists($configKey, $this->config)) {
            throw new \Exception('Config is not available: ' . $configKey);
        }
        $config = $this->config[$configKey];
        if (!$config) {
            throw new \Exception('The plan config does not exist: ' . $config);
        }
        return $config;
    }
}

<?php

// src/Service/ProductService.php
namespace App\Service;

class ProductService
{

    private $products = [
        'hwo_live_syK2z4Y9Qz8VQYraA' => [
            'healthtrain' => [
                'productId' => 'hwo_live_syK2z4Y9Qz8VQYraA',
                'identifier' => 'Huiswerkoefeningen.nl',
                'plan' => 'hwo'
            ],
            'stripe' => [
                'priceId' => 'price_1QgnqxAU7J6SUXut3qZzE0KU',
                'paymentMethods' => 'pmc_1QNGRLAU7J6SUXutucJVChnS'
            ],
            'mailplus' => false,
            'testmode' => false
        ],
        'hwo_test_syK2z4Y9Qz8VQYraA' => [
            'healthtrain' => [
                'productId' => 'hwo_test_syK2z4Y9Qz8VQYraA',
                'identifier' => 'Huiswerkoefeningen.nl',
                'plan' => 'hwo'
            ],
            'stripe' => [
                'priceId' => 'price_1Qgn0bAU7J6SUXutnjXsDfIF',
                'paymentMethods' => 'pmc_1QFP2jAU7J6SUXutnqqCXyO3'
            ],
            'mailplus' => false,
            'testmode' => true
        ],
        'htp_live_pCiBce4AF6XXzH2mn' => [
            'healthtrain' => [
                'productId' => 'htp_live_pCiBce4AF6XXzH2mn',
                'identifier' => 'HealthTrain SOM',
                'plan' => 'spotonmedics'
            ],
            'stripe' => [
                'priceId' => 'price_1QZaG0AU7J6SUXutWSzBW6WD',
                'paymentMethods' => 'pmc_1QNGRLAU7J6SUXutucJVChnS'
            ],
            'mailplus' => [
                'automationId' => 'bc0a8795-536c-432b-83ef-bcb46944eb0f'
            ],
            'testmode' => false
        ],
        'htp_test_H8oYvathg6fv4XF2u' => [
            'healthtrain' => [
                'productId' => 'htp_test_H8oYvathg6fv4XF2u',
                'identifier' => 'HealthTrain SOM Testmode',
                'plan' => 'spotonmedics'
            ],
            'stripe' => [
                'priceId' => 'price_1QD4l5AU7J6SUXutqQV2xIkg',
                'paymentMethods' => 'pmc_1QFP2jAU7J6SUXutnqqCXyO3'
            ],
            'mailplus' => [
                'automationId' => '61fa1a1c-ba87-484d-8890-39868d538989'
            ],
            'testmode' => true
        ]
    ];

    public function get($key): object
    {
        return json_decode(json_encode($this->products[$key]), FALSE);
    }
}
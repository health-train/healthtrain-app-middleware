<?php

// src/Service/ProductService.php
namespace App\Service;

class ProductService
{

    private $products = [
        'htp_live_pCiBce4AF6XXzH2mn' => [
            'healthtrain' => [
                'productId' => 'htp_live_pCiBce4AF6XXzH2mn',
                'identifier' => 'HealthTrain SOM',
            ],
            'stripe' => [
                'priceId' => 'price_1QFvPlAU7J6SUXutO92GxepP',
            ],
            'mailplus' => [
                'automationId' => 'bc0a8795-536c-432b-83ef-bcb46944eb0f'
            ],
            'testmode' => false
        ],
        'htp_test_H8oYvathg6fv4XF2u' => [
            'healthtrain' => [
                'productId' => 'htp_test_H8oYvathg6fv4XF2u',
                'identifier' => 'HealthTrain SOM Testmode'
            ],
            'stripe' => [
                'priceId' => 'price_1QD4l5AU7J6SUXutqQV2xIkg',
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
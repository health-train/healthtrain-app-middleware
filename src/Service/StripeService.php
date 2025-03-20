<?php

// src/Service/StripeService.php
namespace App\Service;

use Psr\Log\LoggerInterface;
use App\Service\ProductService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StripeService
{

    public function __construct(
        private UrlGeneratorInterface $urlgenerator,
        private LoggerInterface $logger,
        private ProductService $productService
    ) {

        // Initialize
        if (!$_ENV['STRIPE_SECRET_KEY']) {
            echo 'Missing required env variable';
            exit;
        }

        $this->urlgenerator = $urlgenerator;
        $this->logger = $logger;
        $this->productService = $productService;
    }

    public function createCheckoutSession($productKey, $quantity = 1, $testmode = true)
    {

        if (!$productKey) {
            throw new HttpException(\Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR, 'Missing required parameters');
        }

        $product = $this->productService->getProduct($productKey);
        if (!$product) {
            throw new \Exception('Invalid product: ' . $productKey);
        }

        $plan = $this->productService->getPlan($product['healthtrain']['plan'], $testmode);
        if (!$plan) {
            throw new \Exception('Invalid plan: ' . $product['healthtrain']['plan']);
        }

        $stripe = new \Stripe\StripeClient($_ENV[$plan['config']['STRIPE_SECRET_KEY']]);
        return $stripe->checkout->sessions->create($this->buildCheckout($stripe, $productKey, $product, $plan, $quantity));
    }

    private function buildCheckout($stripe, $productKey, $product, $plan, $quantity)
    {

        // Validation: Check if priceId is available
        $stripePrice = $stripe->prices->retrieve($product['stripe']['priceId']);
        if (!$stripePrice) {
            throw new \Exception('PriceId not found.');
        }
        $stripePriceData = $stripePrice->metadata;

        if (!$stripePriceData->taxRateId) {
            throw new \Exception('taxRateId not set.');
        }

        // Config: Adjustable quantity
        $adjustable_quantity_config = [];
        if ($stripePriceData->adjust_quantity == "true") {
            $adjustable_quantity_config = [
                'enabled' => $stripePriceData->adjust_quantity ?? true,
                'maximum' => $stripePriceData->adjust_quantity_max ?? 75,
                'minimum' => $stripePriceData->adjust_quantity_min ?? 1
            ];
        }
        // Config: Return URL for cancelled checkouts
        $cancelled_return_url = $this->urlgenerator->generate('checkout_plans', ['planKey' => $product['healthtrain']['plan']], UrlGeneratorInterface::ABSOLUTE_URL);
        if ($plan['testmode']) {
            $cancelled_return_url .= "?testmode=true";
        }

        // Config: Return URL for successful checkouts
        $success_params = ['planKey' => $product['healthtrain']['plan'], 'checkout_session_id' => '{CHECKOUT_SESSION_ID}'];
        if ($plan['testmode']) {
            $success_params['testmode'] = true;
        }

        $success_return_url = $this->urlgenerator->generate('checkout_create_session_success', $success_params, UrlGeneratorInterface::ABSOLUTE_URL);

        // Config: Subscription line item
        $line_item_subscription = [
            'price' => $stripePrice->id,
            'quantity' => $quantity,
            'tax_rates' => [$stripePriceData->taxRateId],
            'adjustable_quantity' => $adjustable_quantity_config
        ];

        // Config: Trial period
        $subscription_data_config = [];
        if ($stripePriceData->trial_period == "true") {
            $subscription_data_config = [
                'trial_settings' => ['end_behavior' => ['missing_payment_method' => 'cancel']],
                'trial_period_days' => $stripePriceData->trial_period_days ?? 14,
            ];
        }
        $subscription_data_config['metadata'] = ['htStripeProductId' => $productKey];

        // Config: Custom text
        $custom_text = [];
        if ($stripePriceData->custom_text_after_submit) {
            $custom_text['after_submit'] = [
                'message' => $stripePriceData->custom_text_after_submit
            ];
        }
        if ($stripePriceData->custom_text_submit) {
            $custom_text['submit'] = [
                'message' => $stripePriceData->custom_text_submit
            ];
        }
        if ($stripePriceData->custom_text_terms) {
            $custom_text['terms_of_service_acceptance'] = [
                'message' => $stripePriceData->custom_text_terms
            ];
        }

        $checkout_params = [
            'success_url' => urldecode($success_return_url),
            'cancel_url' => urldecode($cancelled_return_url),
            'mode' => 'subscription',
            'line_items' => [
                $line_item_subscription
            ],
            'subscription_data' => $subscription_data_config,
            'consent_collection' => [
                'terms_of_service' => "required"
            ],
            'billing_address_collection' => "required",
            'payment_method_configuration' => $product['stripe']['paymentMethods'],
            'phone_number_collection' => [
                'enabled' => true
            ],
            'custom_fields' => [
                [
                    'key' => "organisation_contact_name",
                    'label' => [
                        'custom' => "Naam contactpersoon",
                        'type' => "custom"
                    ],
                    'type' => "text",
                ],
                [
                    'key' => "organisation_name",
                    'label' => [
                        'custom' => "Bedrijfsnaam",
                        'type' => "custom"
                    ],
                    'type' => "text",
                ],
                // [
                //     'key' => "organisation_kvk",
                //     'label' => [
                //         'custom' => "KVK-nummer",
                //         'type' => "custom"
                //     ],
                //     'type' => "numeric",
                //     'numeric' => [
                //         'maximum_length' => 8,
                //         'minimum_length' => 8
                //     ],
                //     'optional' => true
                // ]
            ],
            'locale' => $stripePriceData->locale ?? 'nl',
            'allow_promotion_codes' => $stripePriceData->allow_promotion_codes ?? false,
            'custom_text' => $custom_text
        ];

        return $checkout_params;
    }

    public function updateCustomer($customer, $data, $plan)
    {
        $stripe = new \Stripe\StripeClient($_ENV[$plan['config']['STRIPE_SECRET_KEY']]);

        $customerData = [];
        foreach ($data as $custom_field) {
            if ($custom_field->key == "organisation_contact_name") {
                $customerData['organisation_contact_name'] = $custom_field->text->value ?? 'Not set';
            }
            if ($custom_field->key == "organisation_name") {
                $customerData['organisation_name'] = $custom_field->text->value ?? 'Not set';
            }
            if ($custom_field->key == "organisation_kvk") {
                $customerData['organisation_kvk'] = $custom_field->numeric->value ?? 'Not set';
            }
        }

        // Update customer to save contact name to customer metadata
        try {
            $body = [
                'name' => $customerData['organisation_name'] ?? $customer->name,
                'metadata' => $customerData
            ];
            $customer = $stripe->customers->update($customer->id, $body);
            $this->logger->info('Stripe customer updated ' . $customer->id, array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'customer' => $customer, 'body' => $body, 'testmode' => $plan['testmode']));
            return $customer;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'customer' => $customer, 'body' => $body, 'testmode' => $plan['testmode'], 'exception' => $e));
        }

        return false;
    }

    public function retrieveSubscriptionProductId($subscriptionId, $plan): bool
    {
        $stripe = new \Stripe\StripeClient($_ENV[$plan['config']['STRIPE_SECRET_KEY']]);

        try {
            $subscriptionData = $stripe->subscriptions->retrieve($subscriptionId);
            if ($subscriptionData->metadata->productId) {
                return $subscriptionData->metadata->productId;
            } else {
                throw new \Exception('Subscription does not contain productId metadata.');
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'body' => ['subscription' => $subscriptionId], 'testmode' => $plan['testmode'], 'exception' => $e));
        }

        return false;
    }
}

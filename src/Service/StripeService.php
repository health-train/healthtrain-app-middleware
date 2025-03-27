<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use App\Service\ProductService;
use App\Service\SlackService;
use App\Service\MailPlusService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StripeService
{
    public function __construct(
        private UrlGeneratorInterface $urlgenerator,
        private LoggerInterface $logger,
        private ProductService $productService,
        private SlackService $slackService,
        private MailplusService $mailPlusService,
        private HealthTrainPlatformService $healthTrainPlatformService
    ) {
    }

    private function getStripeClient(string $stripeSecretKey): \Stripe\StripeClient
    {
        return new \Stripe\StripeClient($stripeSecretKey);
    }

    public function createCheckoutSession(string $productKey, int $quantity = 1, bool $testmode = true)
    {
        if (!$productKey) {
            throw new HttpException(\Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR, 'Missing required parameters');
        }

        $product = $this->productService->getProduct($productKey);
        if (!$product) {
            throw new \Exception("Invalid product: {$productKey}");
        }

        $plan = $this->productService->findPlanByProductId($productKey, $testmode);
        if (!$plan) {
            throw new \Exception("Can't find plan for product: {$productKey}");
        }
        
        // Retrieve the correct Stripe Secret Key from the plan
        $stripeSecretKey = $_ENV[$plan[key($plan)]['config']['STRIPE_SECRET_KEY']] ?? '';
        if (empty($stripeSecretKey)) {
            throw new \Exception("Missing Stripe Secret Key for plan: {$plan['config']['STRIPE_SECRET_KEY']}");
        }

        $stripe = $this->getStripeClient($stripeSecretKey);
        return $stripe->checkout->sessions->create($this->buildCheckout($stripe, $productKey, $product, $plan, $quantity));
    }

    private function buildCheckout($stripe, string $productKey, array $product, array $plan, int $quantity): array
    {
        // print_r($plan);exit;
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
        $adjustable_quantity_config = $this->buildAdjustableQuantityConfig($stripePriceData);

        // Config: Return URL for cancelled checkouts
        $cancelled_return_url = $this->generateCancelReturnUrl($plan);

        // Config: Return URL for successful checkouts
        $success_return_url = $this->generateSuccessReturnUrl($plan);

        // Subscription line item and other configurations
        $line_item_subscription = [
            'price' => $stripePrice->id,
            'quantity' => $quantity,
            'tax_rates' => [$stripePriceData->taxRateId],
            'adjustable_quantity' => $adjustable_quantity_config
        ];

        $subscription_data_config = $this->buildSubscriptionDataConfig($stripePriceData, $productKey);
        $custom_text = $this->buildCustomText($stripePriceData);

        return [
            'success_url' => urldecode($success_return_url),
            'cancel_url' => urldecode($cancelled_return_url),
            'mode' => 'subscription',
            'line_items' => [$line_item_subscription],
            'subscription_data' => $subscription_data_config,
            'consent_collection' => ['terms_of_service' => "required"],
            'billing_address_collection' => "required",
            'payment_method_configuration' => $product['stripe']['paymentMethods'],
            'phone_number_collection' => ['enabled' => true],
            'custom_fields' => $this->getCustomFields(),
            'locale' => $stripePriceData->locale ?? 'nl',
            'allow_promotion_codes' => $stripePriceData->allow_promotion_codes ?? false,
            'custom_text' => $custom_text
        ];
    }

    private function buildAdjustableQuantityConfig($stripePriceData): array
    {
        return $stripePriceData->adjust_quantity == "true" ? [
            'enabled' => $stripePriceData->adjust_quantity ?? true,
            'maximum' => $stripePriceData->adjust_quantity_max ?? 75,
            'minimum' => $stripePriceData->adjust_quantity_min ?? 1
        ] : [];
    }

    private function generateCancelReturnUrl(array $plan): string
    {
        $planKey = array_key_first($plan);
        $cancelled_return_url = $this->urlgenerator->generate('checkout_plans', ['planKey' => $planKey], UrlGeneratorInterface::ABSOLUTE_URL);
        if ($plan[$planKey]['testmode'] == true) {
            $cancelled_return_url .= "?testmode=true";
        }
        return $cancelled_return_url;
    }

    private function generateSuccessReturnUrl(array $plan): string
    {
        $planKey = array_key_first($plan);
        $success_params = ['planKey' => $planKey, 'checkout_session_id' => '{CHECKOUT_SESSION_ID}'];
        if ($plan[$planKey]['testmode'] == true) {
            $success_params['testmode'] = true;
        }
        return $this->urlgenerator->generate('checkout_create_session_success', $success_params, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function buildSubscriptionDataConfig($stripePriceData, string $productKey): array
    {
        $subscription_data_config = [];
        if ($stripePriceData->trial_period == "true") {
            $subscription_data_config = [
                'trial_settings' => ['end_behavior' => ['missing_payment_method' => 'cancel']],
                'trial_period_days' => $stripePriceData->trial_period_days ?? 14,
            ];
        }
        $subscription_data_config['metadata'] = ['htStripeProductId' => $productKey];
        return $subscription_data_config;
    }

    private function buildCustomText($stripePriceData): array
    {
        $custom_text = [];
        if ($stripePriceData->custom_text_after_submit) {
            $custom_text['after_submit'] = ['message' => $stripePriceData->custom_text_after_submit];
        }
        if ($stripePriceData->custom_text_submit) {
            $custom_text['submit'] = ['message' => $stripePriceData->custom_text_submit];
        }
        if ($stripePriceData->custom_text_terms) {
            $custom_text['terms_of_service_acceptance'] = ['message' => $stripePriceData->custom_text_terms];
        }
        return $custom_text;
    }

    private function getCustomFields(): array
    {
        return [
            [
                'key' => "organisation_contact_name",
                'label' => ['custom' => "Naam contactpersoon", 'type' => "custom"],
                'type' => "text",
            ],
            [
                'key' => "organisation_name",
                'label' => ['custom' => "Bedrijfsnaam", 'type' => "custom"],
                'type' => "text",
            ]
        ];
    }

    public function updateCustomer($customer, array $data, array $plan)
    {
        // Retrieve Stripe secret key based on the plan
        $stripeSecretKey = $_ENV[$plan['config']['STRIPE_SECRET_KEY']] ?? '';
        if (empty($stripeSecretKey)) {
            throw new \Exception('Missing Stripe Secret Key for plan: ' . $plan['config']['STRIPE_SECRET_KEY']);
        }

        $stripe = $this->getStripeClient($stripeSecretKey);

        $customerData = $this->extractCustomerData($data);

        try {
            $body = [
                'name' => $customerData['organisation_name'] ?? $customer->name,
                'metadata' => $customerData
            ];
            $customer = $stripe->customers->update($customer->id, $body);
            $this->logger->info('Stripe customer updated ' . $customer->id, ['properties' => ['type' => 'checkout', 'action' => __FUNCTION__], 'customer' => $customer, 'body' => $body, 'testmode' => $plan['testmode']]);
            return $customer;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['properties' => ['type' => 'checkout', 'action' => __FUNCTION__], 'customer' => $customer, 'body' => $body, 'testmode' => $plan['testmode'], 'exception' => $e]);
        }

        return false;
    }

    private function extractCustomerData(array $data): array
    {
        $customerData = [];
        foreach ($data as $custom_field) {
            if ($custom_field->key == "organisation_contact_name") {
                $customerData['organisation_contact_name'] = $custom_field->text->value ?? 'Not set';
            }
            if ($custom_field->key == "organisation_name") {
                $customerData['organisation_name'] = $custom_field->text->value ?? 'Not set';
            }
            if ($custom_field->key == "organisation_kvk") {
                $customerData['organisation_kvk'] = $custom_field->text->value ?? 'Not set';
            }
        }
        return $customerData;
    }

    /*
     * TODO: Save fulfilment status of each order to prevent duplicate handling
     */
    public function handleCheckoutSessionCompleted(string $checkoutSessionId, array $config, bool $livemode): void
    {
        $stripeClient = new \Stripe\StripeClient($_ENV[$config['STRIPE_SECRET_KEY']]);

        $this->logger->info('Handling checkout session', [
            'checkout_session_id' => $checkoutSessionId,
            'testmode' => !$livemode
        ]);

        $checkoutSession = $stripeClient->checkout->sessions->retrieve($checkoutSessionId);

        if (empty($checkoutSession) || !isset($checkoutSession['customer'])) {
            $this->logger->warning('No customer id found in checkout session', [
                'checkout_session_id' => $checkoutSessionId
            ]);
            $this->slackService->sendMessage([
                'message' => "Nieuwe klant aangemeld (🚨 Afhandeling niet doorlopen)"
            ]);
            return;
        }

        $customer = $stripeClient->customers->retrieve($checkoutSession['customer']);
        if(empty($customer)) return;
        if(!empty($checkoutSession['$subscription'])) $subscription = $stripeClient->subscriptions->retrieve($checkoutSession['subscription']);
        if(empty($subscription)) return;
        $subscriptionProductId = $subscription->metadata->htStripeProductId ?: null;

        if (!empty($subscriptionProductId)) {
            $plan = $this->productService->findPlanByProductId($subscriptionProductId, !$livemode);

            // Trigger automation for customer contact details
            if (isset($product['mailplus']) && !empty($product['mailplus'])) {
                $this->mailPlusService->triggerAutomation($customer, $product);
            }

            if ($plan['organisationOnboarding'] == true) {
                $this->healthTrainPlatformService->createOrg($customer, array_key_first($plan));
            }
        }

        // Send alerts
        $this->slackService->sendMessage([
            'message' => "Stripe checkout afgerond ✅",
            'customer' => $customer ?: null,
            'subscription' => $subscription ?: null,
            'testmode' => !$livemode
        ]);

        $this->logger->info('Checkout session success', [
            'checkout_session_id' => $checkoutSession['id'],
            'testmode' => !$livemode
        ]);
    }
}

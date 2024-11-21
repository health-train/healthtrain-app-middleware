<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\OAuth1\OAuth1Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class CheckoutController extends AbstractController
{

    private $oauthClient;

    public function __construct(
        private LoggerInterface $logger,
        private HttpClientInterface $client,
        private HttpClientInterface $oauthPocClient
    ) {
        $this->oauthClient = $oauthPocClient;
        $this->logger = $logger;
    }

    public function index(): Response
    {
        return $this->redirect(url: $_ENV['APP_WEBSITE_PLANS']);
    }

    /*
     * show test subscription plans
     */

    public function plans(Request $request, string $plan = "default"): Response
    {
        $testmode = $request->query->get('testmode') == true ? true : false;

        switch ($plan) {
            case "spotonmedics":
                $view = "checkout/plan-spotonmedics.html.twig";
                break;
            default:
                return $this->redirect(url: $_ENV['APP_WEBSITE_PLANS']);
        }
        return $this->render($view, [
            'testmode' => $testmode,
        ]);
    }

    /*
     * create checkout session
     */

    public function create_session(Request $request): Response
    {

        // Initialize
        if (!$_ENV['STRIPE_SECRET_KEY'] || !$_ENV['STRIPE_DEFAULT_TAXRATE_ID']) {
            echo 'Missing required env variable';
            exit;
        }

        $testmode = $request->request->get('testmode') == true ? true : false;

        $stripe = new \Stripe\StripeClient($testmode ? $_ENV['STRIPE_SECRET_KEY_TESTMODE'] : $_ENV['STRIPE_SECRET_KEY']);

        // Get request params
        $stripePriceId = $request->request->get('priceId');
        $quantity = $request->request->get('quantity') ?? 1;

        // Validation: Check if we have required params
        if (!$stripePriceId) {
            echo 'Invalid request';
            exit;
        }

        // Validation: Check if priceId is available
        $stripePrice = $stripe->prices->retrieve($stripePriceId);
        $stripePriceData = $stripePrice->metadata;
        if (!$stripePrice) {
            echo 'Data not available';
            exit;
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
        $cancelled_return_url = $this->generateUrl('checkout_plans_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // Config: Return URL for successful checkouts
        $success_params = ['checkout_session_id' => '{CHECKOUT_SESSION_ID}'];
        if ($testmode) {
            $success_params = ['checkout_session_id' => '{CHECKOUT_SESSION_ID}', 'testmode' => 'true'];
        }
        $success_return_url = $this->generateUrl('checkout_create_session_success', $success_params, UrlGeneratorInterface::ABSOLUTE_URL);

        // Config: Subscription line item
        $line_item_subscription = [
            'price' => $stripePrice->id,
            'quantity' => $quantity,
            'tax_rates' => [$stripePriceData->taxRateId ?? $_ENV['STRIPE_DEFAULT_TAXRATE_ID']],
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
            'phone_number_collection' => [
                'enabled' => false
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
                // [
                //     'key' => "organisation_name",
                //     'label' => [
                //         'custom' => "Bedrijfsnaam",
                //         'type' => "custom"
                //     ],
                //     'type' => "text",
                // ],
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

        $checkout_session = $stripe->checkout->sessions->create($checkout_params);

        $this->logger->info('Checkout session started: ' . $checkout_session->id . ' [stripePriceId: ' . $stripePriceId . '] [Quantity: ' . $quantity . ']', array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'checkout_session_id' => $checkout_session->id, 'stripePriceId' => $stripePriceId, 'quantity' => $quantity, 'testmode' => $testmode));
        return $this->redirect(url: $checkout_session->url);
    }

    /*
     * session result: cancelled
     */
    public function session_cancelled(Request $request): Response
    {
        $testmode = $request->query->get('testmode') == true ? true : false;
        return $this->render('checkout/cancelled.html.twig', [
            'testmode' => $testmode,
        ]);
    }

    /*
     * session result: success
     */
    public function session_success(Request $request, string $checkout_session_id): Response
    {
        $testmode = $request->query->get('testmode') == true ? true : false;

        $stripe = new \Stripe\StripeClient($testmode ? $_ENV['STRIPE_SECRET_KEY_TESTMODE'] : $_ENV['STRIPE_SECRET_KEY']);

        // Fetch the Checkout Session to display the JSON result on the success page
        $checkout_session = $stripe->checkout->sessions->retrieve($checkout_session_id);
        $subscription = $stripe->subscriptions->retrieve(($checkout_session->subscription));
        $customer = $stripe->customers->retrieve($checkout_session->customer);
        $payment_method = $stripe->paymentMethods->retrieve($subscription->default_payment_method);

        $organisation_name = $organisation_contact_name = $organisation_kvk = false;
        foreach ($checkout_session->custom_fields as $custom_field) {
            if ($custom_field->key == "organisation_contact_name") {
                $organisation_contact_name = $custom_field->text->value;
            }
            if ($custom_field->key == "organisation_name") {
                $organisation_name = $custom_field->text->value;
            }
            if ($custom_field->key == "organisation_kvk") {
                $organisation_kvk = $custom_field->numeric->value;
            }
        }

        // TODO: Move to webhook
        if ($customer && $organisation_contact_name) {

            
            // Update customer to save contact name to customer metadata
            try {
                $stripeCustomerParams = ['contact_name' => $organisation_contact_name];
                $stripe->customers->update($customer->id, ['metadata' => $stripeCustomerParams]);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage(), array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'checkout_session_id' => $checkout_session->id, 'body' => $stripeCustomerParams, 'testmode' => $testmode, 'exception' => $e));
            }

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
                            'firstName' => $organisation_contact_name,
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
                $this->logger->error($e->getMessage(), array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'checkout_session_id' => $checkout_session->id, 'body' => $mailPlusContactParams, 'testmode' => $testmode, 'exception' => $e));
            }

            // Trigger automation for MailPlus Contact
            try {
                $mailPlusAutomationParams = ['externalContactId' => $customer->id];
                $OAuthRequest->request($this->oauthClient, 'POST', 'https://restapi.mailplus.nl/integrationservice/automation/trigger/bc0a8795-536c-432b-83ef-bcb46944eb0f', [], $mailPlusAutomationParams);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage(), array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'checkout_session_id' => $checkout_session->id, 'body' => $mailPlusAutomationParams, 'testmode' => $testmode, 'exception' => $e));
            }
        }

        $this->logger->info('Checkout session success: ' . $checkout_session->id, array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'checkout_session_id' => $checkout_session->id, 'testmode' => $testmode));

        // Format as JSON for the demo.
        return $this->render('checkout/success.html.twig', [
            'testmode' => $testmode,
            'checkout_session' => $checkout_session,
            'customer' => $customer,
            'subscription' => $subscription,
            'payment_method' => $payment_method,
            'organisation_name' => $organisation_name,
            'organisation_contact_name' => $organisation_contact_name,
            'organisation_kvk' => $organisation_kvk,
            'checkout_session_id' => $checkout_session_id
        ]);
    }

    /*
     * redirect to Stripe billing portal (public)
     */
    public function portal_redirect(Request $request): Response
    {
        $testmode = $request->query->get('testmode') == true ? true : false;
        $prefilled_email = $request->query->get('prefilled_email') ? $request->query->get('prefilled_email') : false;

        $portal_redirect_url = $testmode ? $_ENV['STRIPE_BILLING_URL_TESTMODE'] : $_ENV['STRIPE_BILLING_URL'];
        if($request->query->get('prefilled_email')) {
            $portal_redirect_url = $portal_redirect_url.'?prefilled_email='.$prefilled_email;
        }

        return $this->redirect($portal_redirect_url);
 
    }

    /*
     * redirect to Stripe billing portal (logged in)
     */
    public function portal_redirect_login(Request $request): Response
    {
        $testmode = $request->request->get('testmode') == true ? true : false;
        $stripe = new \Stripe\StripeClient($testmode ? $_ENV['STRIPE_SECRET_KEY_TESTMODE'] : $_ENV['STRIPE_SECRET_KEY']);

        $stripeCustomerId = $request->request->get('stripeCustomerId');
        $sessionId = $request->request->get('sessionId');
        $redirectChannel = $request->request->get('redirect');

        if (!$stripeCustomerId && !$sessionId) {
            echo 'Invalid request';
            exit;
        }

        if (!$stripeCustomerId && $sessionId) {
            $checkout_session = $stripe->session->retrieve($sessionId);
            $stripeCustomerId = $checkout_session->customer;
        }

        switch ($redirectChannel) {
            case "portal":
                $return_url = "https://portal.healthtrain.app";
                break;
            default:
                $return_url = $_ENV['APP_WEBSITE'];
        }

        $portal_session = $stripe->billingPortal->sessions->create([
            'customer' => $stripeCustomerId,
            'return_url' => $return_url,
        ]);
        $this->logger->info('Portal redirected for customer(' . $stripeCustomerId . ')', array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'customer' => $stripeCustomerId, 'return_url' => $return_url));
        return $this->redirect(url: $portal_session->url);
    }

    public function stripe_webhooks(Request $request): JsonResponse
    {
        $testmode = $request->request->get('testmode') == true ? true : false;

        \Stripe\Stripe::setApiKey($testmode ? $_ENV['STRIPE_SECRET_KEY_TESTMODE'] : $_ENV['STRIPE_SECRET_KEY']);

        $endpoint_secret = $_ENV["STRIPE_WEBHOOK_SECRET"];

        $payload = $request->getContent();
        $sig_header = $request->headers->get('Stripe-Signature', '');
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return new JsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_BAD_REQUEST);
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->logger->info('payment_intent.succeeded');
                $paymentIntent = $event->data->object;
            // ... handle other event types
            default:
                $this->logger->warning('Unknown event type:' . $event->type);
        }

        return new JsonResponse(['OK']);

    }
}
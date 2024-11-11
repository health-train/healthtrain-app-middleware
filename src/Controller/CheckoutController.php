<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CheckoutController extends AbstractController
{

    public function index(): Response
    {
        return $this->redirect(url: $_ENV['APP_WEBSITE_PLANS']);
    }

    /*
     * show test subscription plans
     */

    public function plans(Request $request, string $plan = "default"): Response
    {
        $testmode = $request->query->get('testmode') == true ? true: false;

        switch($plan) {
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

        if (!$_ENV['STRIPE_SECRET_KEY'] || !$_ENV['STRIPE_DEFAULT_TAXRATE_ID']) {
            echo 'Missing required env variable';
            exit;
        }

        $testmode = $request->request->get('testmode') == true ? true : false;

        \Stripe\Stripe::setApiKey($testmode ? $_ENV['STRIPE_SECRET_KEY_TESTMODE'] : $_ENV['STRIPE_SECRET_KEY']);

        $stripePriceId = $request->request->get('priceId');
        $quantity = $request->request->get('quantity') ?? 1;

        if (!$stripePriceId) {
            echo 'Invalid request';
            exit;
        }

        $stripePrice = \Stripe\Price::retrieve($stripePriceId);
        $stripePriceData = $stripePrice->metadata;

        if (!$stripePrice) {
            echo 'Data not available';
            exit;
        }

        $adjustable_quantity_config = [];
        if ($stripePriceData->adjust_quantity == "true") {
            $adjustable_quantity_config = [
                'enabled' => $stripePriceData->adjust_quantity ?? true,
                'maximum' => $stripePriceData->adjust_quantity_max ?? 75,
                'minimum' => $stripePriceData->adjust_quantity_min ?? 1
            ];
        }

        $subscription_data_config = [];
        if ($stripePriceData->trial_period == "true") {
            $subscription_data_config = [
                'trial_settings' => ['end_behavior' => ['missing_payment_method' => 'cancel']],
                'trial_period_days' => $stripePriceData->trial_period_days ?? 14,
            ];
        }

        $return_url = $this->generateUrl('checkout_plans_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $success_params = ['checkout_session_id' => '{CHECKOUT_SESSION_ID}'];
        if($testmode) {
            $success_params = ['checkout_session_id' => '{CHECKOUT_SESSION_ID}', 'testmode' => 'true'];
        }

        $checkout_params = [
            'success_url' => urldecode($this->generateUrl('checkout_create_session_success', $success_params, UrlGeneratorInterface::ABSOLUTE_URL)),
            'cancel_url' => urldecode($return_url),
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $stripePrice->id,
                    'quantity' => $quantity,
                    'tax_rates' => [$stripePriceData->taxRateId ?? $_ENV['STRIPE_DEFAULT_TAXRATE_ID']],
                    'adjustable_quantity' => $adjustable_quantity_config
                ]
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
            'custom_text' => [
                'after_submit' => [
                    'message' => $stripePriceData->custom_text_after_submit ?? null
                ],
                'submit' => [
                    'message' => $stripePriceData->custom_text_submit ?? null
                ],
                'terms_of_service_acceptance' => [
                    'message' => $stripePriceData->custom_text_terms ?? null
                ]
            ]
        ];

        $checkout_session = \Stripe\Checkout\Session::create($checkout_params);
        return $this->redirect(url: $checkout_session->url);
    }

    /*
     * session result: cancelled
     */

    public function session_cancelled(Request $request): Response
    {
        $testmode = $request->query->get('testmode') == true ? true: false;
        return $this->render('checkout/cancelled.html.twig', [
            'testmode' => $testmode,
        ]);
    }

    /*
     * session result: success
     */
    public function session_success(Request $request, string $checkout_session_id): Response
    {
        $testmode = $request->query->get('testmode') == true ? true: false;

        $stripe = new \Stripe\StripeClient($testmode ? $_ENV['STRIPE_SECRET_KEY_TESTMODE'] : $_ENV['STRIPE_SECRET_KEY']);

        // Fetch the Checkout Session to display the JSON result on the success page
        $checkout_session = $stripe->checkout->sessions->retrieve($checkout_session_id);
        $subscription = $stripe->subscriptions->retrieve(($checkout_session->subscription));
        $customer = $stripe->customers->retrieve($checkout_session->customer);
        $payment_method = $stripe->paymentMethods->retrieve($subscription->default_payment_method);


        $organisation_name = $organisation_contact_name = $organisation_kvk = false;
        foreach($checkout_session->custom_fields as $custom_field) {
            if($custom_field->key == "organisation_contact_name") {
                $organisation_contact_name = $custom_field->text->value;
            }
            if($custom_field->key == "organisation_name") {
                $organisation_name = $custom_field->text->value;
            }
            if($custom_field->key == "organisation_kvk") {
                $organisation_kvk = $custom_field->numeric->value;
            }
        }

        // Update customer to save contact name to customer metadata
        // TODO: Move to webhook
        if($organisation_contact_name) $stripe->customers->update($customer->id, ['metadata' => ['contact_name' => $organisation_contact_name]] );

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
     * redirect to Stripe billing portal
     */
    public function portal_redirect(Request $request): Response
    {
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        $stripeCustomerId = $request->request->get('$stripeCustomerId');
        $sessionId = $request->request->get('sessionId');
        $redirectChannel = $request->request->get('redirect');

        if (!$stripeCustomerId && !$sessionId) {
            echo 'Invalid request';
            exit;
        }

        if (!$stripeCustomerId && $sessionId) {
            $checkout_session = \Stripe\Checkout\Session::retrieve($sessionId);
            $stripeCustomerId = $checkout_session->customer;
        }

        switch ($redirectChannel) {
            case "portal":
                $return_url = "https://portal.healthtrain.app";
                break;
            default:
                $return_url = $_ENV['APP_WEBSITE'];
        }

        $portal_session = \Stripe\BillingPortal\Session::create([
            'customer' => $stripeCustomerId,
            'return_url' => $return_url,
        ]);
        return $this->redirect(url: $portal_session->url);
    }
}
<?php

namespace App\Controller;

use App\Service\StripeService;
use App\Service\ProductService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;


class CheckoutController extends AbstractController
{

    private $oauthClient;

    public function __construct(
        private LoggerInterface $logger
    ) {
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
            case "hwo":
                $view = "checkout/plan-hwo.html.twig";
                break;
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

    public function create_session(Request $request, StripeService $stripeService, ProductService $productService): Response
    {

        // Validation: Check if we have required params
        if (!$request->request->get('productId')) {
            echo 'Invalid request';
            exit;
        }

        $product = $productService->get($request->request->get('productId'));
        $testmode = $request->request->get('testmode') == true ? true : false;
        $quantity = $request->request->get(key: 'quantity');
        $quantity = (is_numeric($quantity) && $quantity >= 1 && $quantity <= 999) ? $quantity : 1;

        $checkoutSession = $stripeService->createCheckoutSession($product, $quantity, $testmode);

        $this->logger->info('Checkout session started: ' . $checkoutSession->id . ' [productId: ' . $product->healthtrain->productId . '] [Quantity: ' . $quantity . ']', array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'checkout_session_id' => $checkoutSession->id, 'product' => $product, 'quantity' => $quantity, 'testmode' => $testmode));
        return $this->redirect(url: $checkoutSession->url);
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
    public function session_success(Request $request, string $checkout_session_id, StripeService $stripeService): Response
    {
        $testmode = $request->query->get('testmode') == true ? true : false;

        $stripe = new \Stripe\StripeClient($testmode ? $_ENV['STRIPE_SECRET_KEY_TESTMODE'] : $_ENV['STRIPE_SECRET_KEY']);

        // Fetch the Checkout Session to display the JSON result on the success page
        $checkoutSession = $stripe->checkout->sessions->retrieve($checkout_session_id);
        $subscription = $stripe->subscriptions->retrieve(($checkoutSession->subscription));
        $customer = $stripe->customers->retrieve($checkoutSession->customer);
        $payment_method = $stripe->paymentMethods->retrieve($subscription->default_payment_method);

        $customerData = [];
        foreach ($checkoutSession->custom_fields as $custom_field) {
            if ($custom_field->key == "organisation_contact_name") {
                $customerData['organisation_contact_name'] = $custom_field->text->value;
            }
            if ($custom_field->key == "organisation_name") {
                $customerData['organisation_name'] = $custom_field->text->value;
            }
            if ($custom_field->key == "organisation_kvk") {
                $customerData['organisation_kvk'] = $custom_field->numeric->value;
            }
        }

        $stripeService->updateCustomer($customer, $checkoutSession['custom_fields'], $testmode);

        return $this->render('checkout/success.html.twig', [
            'testmode' => $testmode ?? false,
            'customer' => $customer,
            'payment_method' => $payment_method,
            'customer_data' => $customerData
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
        if ($request->query->get('prefilled_email')) {
            $portal_redirect_url = $portal_redirect_url . '?prefilled_email=' . $prefilled_email;
        }
        $this->logger->info('Stripe billing portal public redirect', array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'prefilled_email' => $prefilled_email));
        return $this->redirect($portal_redirect_url);
    }

    /*
     * redirect to Stripe billing portal (logged in)
     */
    public function portal_redirect_login(Request $request): Response
    {
        $testmode = $request->request->get('testmode') == true ? true : false;
        $stripe = new \Stripe\StripeClient($testmode ? $_ENV['STRIPE_SECRET_KEY_TESTMODE'] : $_ENV['STRIPE_SECRET_KEY']);

        // Stripe customer ID
        $stripeCustomerId = $request->request->get('customerId');

        // Stripe customer checkout session
        // Will be used to get Stripe Customer ID from session
        $stripeCheckoutSessionId = $request->request->get('sessionId');

        // Which channel the Stripe portal logo should direct to. Default: marketing website
        $redirectChannel = $request->request->get('redirect');

        // Return URL or default: redirect to url
        $action = $request->request->get('action') ?? 'redirect';

        if (!$stripeCustomerId && !$stripeCheckoutSessionId) {
            echo 'Invalid request';
            exit;
        }

        if (!$stripeCustomerId && $stripeCheckoutSessionId) {
            $checkout_session = $stripe->session->retrieve($stripeCheckoutSessionId);
            $stripeCustomerId = $checkout_session->customer;
        }

        switch ($redirectChannel) {
            case "portal":
                $return_url = "https://portal.healthtrain.app";
                break;
            default:
                $return_url = $_ENV['APP_WEBSITE'];
        }

        try {
            $portal_session = $stripe->billingPortal->sessions->create([
                'customer' => $stripeCustomerId,
                'return_url' => $return_url,
            ]);
            $this->logger->info('Stripe billing portal customer(' . $stripeCustomerId . ') redirect', array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'customer' => $stripeCustomerId, 'return_url' => $return_url, 'action' => $action));

            switch ($action) {
                case "redirect":
                    return $this->redirect($portal_session->url);
                default:
                    return $this->json(['status' => 'ok', 'url' => $portal_session->url]);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'customer' => $stripeCustomerId, 'body' => ['action' => $action, 'redirect' => $redirectChannel, 'stripeCustomerId' => $stripeCustomerId], 'testmode' => $testmode, 'exception' => $e));
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

    }
}
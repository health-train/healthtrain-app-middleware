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
    public function __construct(
        private LoggerInterface $logger,
        private ProductService $productService,
        private StripeService $stripeService
    ) {}

    public function index(): Response
    {
        return $this->redirect($_ENV['APP_WEBSITE_PLANS']);
    }

    public function plans(Request $request, string $planKey): Response
    {
        if (!$planKey) {
            throw $this->createNotFoundException('Missing parameter: planKey');
        }
        
        $testmode = (bool)$request->query->get('testmode');
        $plan = $this->productService->getPlan($planKey, $testmode);
        
        if (!$plan) {
            throw new \Exception('The plan does not exist or is misconfigured: ' . $planKey);
        }

        $template = "checkout/plan-" . $planKey . ".html.twig";

        if (!$this->container->get('twig')->getLoader()->exists($template)) {
            throw new \Exception('The plan template does not exist: ' . $planKey);
        }

        return $this->render($template, [
            'testmode' => $testmode,
            'products' => $plan['products']
        ]);
    }

    public function create_session(Request $request): Response
    {
        $testmode = (bool)$request->request->get('testmode');
        
        $quantity = $request->request->get('quantity');
        $quantity = is_numeric($quantity) && $quantity >= 1 && $quantity <= 999 ? (int)$quantity : 1;
        
        $productId = $request->request->get('productId');
        
        if (!$productId) {
            throw new \Exception('Invalid request: Missing productId');
        }

        $checkoutSession = $this->stripeService->createCheckoutSession($productId, $quantity, $testmode);

        // Log after session creation
        $this->logger->info('Checkout session started', ['sessionId' => $checkoutSession->id, 'productId' => $productId, 'quantity' => $quantity, 'testmode' => $testmode]);

        return $this->redirect($checkoutSession->url);
    }

    public function session_cancelled(Request $request): Response
    {
        $testmode = (bool)$request->query->get('testmode');
        return $this->render('checkout/cancelled.html.twig', ['testmode' => $testmode]);
    }

    public function session_success(Request $request, string $planKey, string $checkout_session_id): Response
    {
        $testmode = (bool)$request->query->get('testmode');
        $plan = $this->productService->getPlan($planKey, $testmode);

        $stripe = new \Stripe\StripeClient($_ENV[$plan['config']['STRIPE_SECRET_KEY']]);

        // Fetch Checkout Session data
        try {
        $checkoutSession = $stripe->checkout->sessions->retrieve($checkout_session_id);
            $subscription = $stripe->subscriptions->retrieve($checkoutSession->subscription);
        $customer = $stripe->customers->retrieve($checkoutSession->customer);
        $payment_method = $stripe->paymentMethods->retrieve($subscription->default_payment_method);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving Stripe session', ['exception' => $e]);
            return $this->render('checkout/error.html.twig', ['message' => 'An error occurred while processing your session']);
        }

        // Customer data parsing from custom fields
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

        // Update customer in Stripe
        $this->stripeService->updateCustomer($customer, $checkoutSession['custom_fields'], $plan);

        return $this->render('checkout/success.html.twig', [
            'testmode' => $testmode,
            'plan' => $planKey,
            'customer' => $customer,
            'payment_method' => $payment_method,
            'customer_data' => $customerData
        ]);
    }

    public function portal_redirect(Request $request, string $configKey): Response
    {
        $testmode = (bool)$request->query->get('testmode');
        $prefilled_email = $request->query->get('prefilled_email', false);
        $config = $this->productService->getConfig($configKey, $testmode);

        $portal_redirect_url = $_ENV[$config['STRIPE_BILLING_URL']] . ($prefilled_email ? '?prefilled_email=' . $prefilled_email : '');
        
        $this->logger->info('Redirecting to Stripe billing portal', ['prefilled_email' => $prefilled_email, 'testmode' => $testmode]);

        return $this->redirect($portal_redirect_url);
    }

    public function portal_redirect_login(Request $request, string $configKey): Response
    {
        $testmode = (bool)$request->request->get('testmode');
        $config = $this->productService->getConfig($configKey, $testmode);
        $stripe = new \Stripe\StripeClient($_ENV[$config['STRIPE_SECRET_KEY']]);

        $stripeCustomerId = $request->request->get('customerId');
        $stripeCheckoutSessionId = $request->request->get('sessionId');
        $redirectChannel = $request->request->get('redirect', 'default');
        $action = $request->request->get('action', 'redirect');

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

            $this->logger->info('Stripe billing portal redirect', ['customer' => $stripeCustomerId, 'return_url' => $return_url]);

            switch ($action) {
                case "redirect":
                    return $this->redirect($portal_session->url);
                default:
                    return $this->json(['status' => 'ok', 'url' => $portal_session->url]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error creating billing portal session', ['exception' => $e, 'customer' => $stripeCustomerId]);
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}

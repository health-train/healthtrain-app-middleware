<?php

namespace App\RemoteEvent;

use App\Service\ProductService;
use App\Service\MailPlusService;
use App\Service\SlackService;
use App\Service\StripeService;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Psr\Log\LoggerInterface;

#[AsRemoteEventConsumer('stripe')]
final class StripeWebhookConsumer implements ConsumerInterface
{
    public function __construct(private LoggerInterface $logger, private ProductService $productService, private StripeService $stripeService, private MailPlusService $mailPlusService, private SlackService $slackService)
    {
        $this->logger = $logger;
        $this->stripeService = $stripeService;
        $this->mailPlusService = $mailPlusService;
        $this->productService = $productService;
        $this->slackService = $slackService;
    }

    public function consume(RemoteEvent $event): void
    {

        $event_type = $event->getName();
        $payload = (object) $event->getPayload();

        $this->logger->info('Stripe Webhook received: ' . $event_type, array('properties' => array('type' => 'webhook', 'action' => 'stripe'), 'payload' => $payload));

        switch ($event_type) {
            case "checkout.session.completed":
                $this->handleCheckoutSessionCompleted($payload);
                break;
        }
    }
    private function handleCheckoutSessionCompleted($payload)
    {
        $stripe = new \Stripe\StripeClient($payload->livemode ? $_ENV['STRIPE_SECRET_KEY'] : $_ENV['STRIPE_SECRET_KEY_TESTMODE']);
        $checkoutSession = $payload->data['object'];
        $this->logger->info('Handling checkout session', array('properties' => array('type' => 'webhooks', 'action' => 'stripe'), 'checkout' => $payload->data['object']));

        // Fetch associated customer
        if (isset($checkoutSession['customer'])) {
            $customer = $stripe->customers->retrieve($checkoutSession['customer']);
            // Fetch associated subscription and productId
            $subscription = $stripe->subscriptions->retrieve($checkoutSession['subscription']);
            $subscriptionProductId = $subscription->metadata->productId;

            if ($subscription && $subscriptionProductId && $product = $this->productService->get($subscriptionProductId)) {
                // Update Stripe customer with custom fields
                // $this->logger->info('updateCustomer', array('properties' => array('type' => 'webhooks', 'action' => 'stripe'), 'customer' => $customer, 'body' => $checkoutSession['custom_fields'], 'testmode' => !$payload->livemode));
                // $customer = $this->stripeService->updateCustomer($customer, $checkoutSession['custom_fields'], !$payload->livemode);

                // Trigger automation for customer contact details
                $this->logger->info('triggerAutomation', array('properties' => array('type' => 'webhooks', 'action' => 'stripe'), 'customer' => $customer, 'product' => $product, 'testmode' => !$payload->livemode));
                if($product->mailplus) {
                    $this->mailPlusService->triggerAutomation($customer, $product);
                }
            }
            
            // Send alerts
            $this->slackService->sendMessage(['message' => "Nieuwe klant aangemeld ✅", 'customer' => $customer, 'subscription' => $subscription, 'testmode' => !$payload->livemode], 'stripe');
            $this->logger->info('Checkout session success: ' . $checkoutSession->id, array('properties' => array('type' => 'checkout', 'action' => __FUNCTION__), 'checkout_session' => $checkoutSession, 'testmode' => !$payload->livemode));

        } else {
            // Send alerts
            $this->slackService->sendMessage(['message' => "Nieuwe klant aangemeld (🚨 Afhandeling niet doorlopen)"]);
            $this->logger->info('Checkout handling dropped: No customer id' . $checkoutSession['id'], array('properties' => array('type' => 'webhooks', 'action' => 'stripe'), 'checkout_session' => $checkoutSession, 'testmode' => !$payload->livemode));
        }

    }
}

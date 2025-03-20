<?php
// src/Controller/DefaultController.php
namespace App\Controller;

use App\Service\HealthTrainPlatformService;
use App\Service\SlackService;
use App\Service\StripeService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DefaultController extends AbstractController
{
    public function index(): Response
    {
        return $this->redirect($_ENV['APP_WEBSITE']);
    }

    public function slackTest(SlackService $slackService): Response
    {
        // $slackService->sendMessage(['message' => 'Test message']);
        return $this->redirect($_ENV['APP_WEBSITE']);
    }

    public function stripeTest(StripeService $stripeService): Response
    {

        // $stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY_TESTMODE']);
    
        // $customer = $stripe->customers->retrieve('cus_RGIakJ9DcvOrRh');
        // $checkoutSession = $stripe->checkout->sessions->retrieve('cs_test_b1qx1myAYslkQqBHUx7g2gaJv2mUtWFaRGB0ciF7Kyi9SP7oOrau2W809b');

        // $customer = $stripeService->updateCustomer($customer, $checkoutSession['custom_fields'], false);

        return $this->redirect($_ENV['APP_WEBSITE']);
    }

    public function onboardTest(HealthTrainPlatformService $healthTrainPlatformService): Response
    {

        // $stripe = new \Stripe\StripeClient($_ENV['STRIPE_HT1_TESTMODE_SECRET_KEY']);
    
        // $customer = $stripe->customers->retrieve('cus_RymYxgo0nXCeJW');
        // // print_r($customer);
        // $org = $healthTrainPlatformService->createOrg($customer, 'intramed-pilot', true);
        // print_r($org);

        // exit;
 
        return $this->redirect($_ENV['APP_WEBSITE']);
    }
    
}
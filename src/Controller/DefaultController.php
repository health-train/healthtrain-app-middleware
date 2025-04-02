<?php
// src/Controller/DefaultController.php
namespace App\Controller;

use App\Service\HealthTrainPlatformService;
use App\Service\SlackService;
use App\Service\StripeService;
use App\Service\ProductService;
use PhpParser\Node\Scalar\MagicConst\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DefaultController extends AbstractController
{
    public function index(): Response
    {
        return $this->redirect($_ENV['APP_WEBSITE']);
    }

    public function slackTest(): Response
    {
        // $slackService->sendMessage(['message' => 'Test message']);
        return $this->redirect($_ENV['APP_WEBSITE']);
    }

    public function stripeTest(): Response
    {

        // $stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY_TESTMODE']);
    
        // $customer = $stripe->customers->retrieve('cus_RGIakJ9DcvOrRh');
        // $checkoutSession = $stripe->checkout->sessions->retrieve('cs_test_b1qx1myAYslkQqBHUx7g2gaJv2mUtWFaRGB0ciF7Kyi9SP7oOrau2W809b');

        // $customer = $stripeService->updateCustomer($customer, $checkoutSession['custom_fields'], false);

        return $this->redirect($_ENV['APP_WEBSITE']);
    }

    public function onboardTest(ProductService $productService, StripeService $stripeService): Response
    {


        $checkout = "cs_test_a1DyPx1bbkCaDGz3yATJ5N3CX8IzNiDeqZoL4AEia35hyqIFSRVkIT5XiN";
        print_r($checkout);

        $stripeService->handleCheckoutSessionCompleted($checkout, $productService->getConfig("ht1_testmode"), false);

        exit;

        // return $this->redirect($_ENV['APP_WEBSITE']);
    }
    
    public function hashtest(): Response
    {
        // $json = (array) json_decode(file_get_contents('../config/healthtrain/hash.json'));
        // unset($json['old_hwo']);
        // unset($json['startdate']);
        // unset($json['enddate']);

        // $externalId = "129876";
        // $salt = "abcdefghijklmnop1234567890";
        // $sso_key = "fJBQd53a!bz*!D";
        // $timestamp = "1742999661";


        // // $hashInput = implode("", $json) . $externalId . $timestamp. $sso_key . 'abcdefghijklmnop1234567890';
        // $hashInput = implode("", $json) . $sso_key . $timestamp . $salt;
        // $hash = hash('sha512', $hashInput);
        // echo "91111jelle+support@healthtrain.nlJelleJouwsma1222111222333k.ebbenhorst@outlook.comKoenLuucclientfJBQd53a!bz*!D1742999661abcdefghijklmnop1234567890\n<br>";
        // echo "hashInput: <br>\n";
        // print_r($hashInput. "\n\n");
        // echo "<br><br>calculated hash: <br>\n";
        // print_r($hash); exit;

        return $this->redirect($_ENV['APP_WEBSITE']);
    }
}
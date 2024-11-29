<?php
// src/Controller/DefaultController.php
namespace App\Controller;

use App\Service\SlackService;
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
        $slackService->sendMessage('Test message');
        return $this->redirect($_ENV['APP_WEBSITE']);
    }
}
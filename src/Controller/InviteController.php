<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class InviteController extends AbstractController
{

    public function __construct(
        private LoggerInterface $logger,
    ) {
        $this->logger = $logger;
    }

    /*
     * HT - HealthTrain
     */
    public function invite_ht_prod(int $invitationId, int $userOrganisationConnectionId): Response
    {
        $this->logger->info('Invite link used [HT_PROD] [InvitationId: ' . $invitationId . '] [userOrganisationConnectionId: ' . $userOrganisationConnectionId . ']', array('properties' => array('type' => 'invite', 'action' => __FUNCTION__), 'invitationId' => $invitationId, 'userOrganisationConnectId', $userOrganisationConnectionId));
        return $this->redirect("https://backend.healthtrain.app/Invite/" . $invitationId . "/" . $userOrganisationConnectionId);
    }

    public function invite_ht_at(int $invitationId, int $userOrganisationConnectionId): Response
    {
        $this->logger->info('Invite link used [HT_AT] [InvitationId: ' . $invitationId . '] [userOrganisationConnectionId: ' . $userOrganisationConnectionId . ']', array('properties' => array('type' => 'invite', 'action' => __FUNCTION__), 'invitationId' => $invitationId, 'userOrganisationConnectId', $userOrganisationConnectionId));
        return $this->redirect("https://healthtrain-beheer-at.fenetre.nl/Invite/" . $invitationId . "/" . $userOrganisationConnectionId);
    }

    public function invite_ht_it(int $invitationId, int $userOrganisationConnectionId): Response
    {
        $this->logger->info('Invite link used [HT_IT] [InvitationId: ' . $invitationId . '] [userOrganisationConnectionId: ' . $userOrganisationConnectionId . ']', array('properties' => array('type' => 'invite', 'action' => __FUNCTION__), 'invitationId' => $invitationId, 'userOrganisationConnectId', $userOrganisationConnectionId));
        return $this->redirect("https://healthtrain-beheer-it.fenetre.nl/Invite/" . $invitationId . "/" . $userOrganisationConnectionId);
    }

    /*
     * HTGER - HealthTrain Germany
     */

    public function invite_htger_prod(int $invitationId, int $userOrganisationConnectionId): Response
    {
        $this->logger->info('Invite link used [HTGER_PROD] [InvitationId: ' . $invitationId . '] [userOrganisationConnectionId: ' . $userOrganisationConnectionId . ']', array('properties' => array('type' => 'invite', 'action' => __FUNCTION__), 'invitationId' => $invitationId, 'userOrganisationConnectId', $userOrganisationConnectionId));
        return $this->redirect("https://backend-germany.healthtrain.app/Invite/" . $invitationId . "/" . $userOrganisationConnectionId);
    }

    public function invite_htger_at(int $invitationId, int $userOrganisationConnectionId): Response
    {
        $this->logger->info('Invite link used [HTGER_AT] [InvitationId: ' . $invitationId . '] [userOrganisationConnectionId: ' . $userOrganisationConnectionId . ']', array('properties' => array('type' => 'invite', 'action' => __FUNCTION__), 'invitationId' => $invitationId, 'userOrganisationConnectId', $userOrganisationConnectionId));
        return $this->redirect("https://healthtrain-germany-beheer-at.fenetre.nl/Invite/" . $invitationId . "/" . $userOrganisationConnectionId);
    }
}
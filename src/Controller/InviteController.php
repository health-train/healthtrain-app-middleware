<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class InviteController extends AbstractController
{
    /*
     * HT - HealthTrain
     */
    public function invite_ht_prod(int $invitationId, int $userOrganisationConnectionId): Response
    {
        return $this->redirect("https://backend.healthtrain.app/Invite/" . $invitationId . "/" . $userOrganisationConnectionId);
    }
    
    public function invite_ht_at(int $invitationId, int $userOrganisationConnectionId): Response
    {
        return $this->redirect("https://healthtrain-beheer-at.fenetre.nl/Invite/" . $invitationId . "/" . $userOrganisationConnectionId);
    }

    public function invite_ht_it(int $invitationId, int $userOrganisationConnectionId): Response
    {
        return $this->redirect("https://healthtrain-beheer-it.fenetre.nl/Invite/" . $invitationId . "/" . $userOrganisationConnectionId);
    }

    /*
     * HTGER - HealthTrain Germany
     */

    public function invite_htger_prod(int $invitationId, int $userOrganisationConnectionId): Response
    {
        return $this->redirect("https://backend-germany.healthtrain.app/Invite/" . $invitationId . "/" . $userOrganisationConnectionId);
    }

    public function invite_htger_at(int $invitationId, int $userOrganisationConnectionId): Response
    {
        return $this->redirect("https://healthtrain-germany-beheer-at.fenetre.nl/Invite/" . $invitationId . "/" . $userOrganisationConnectionId);
    }
}
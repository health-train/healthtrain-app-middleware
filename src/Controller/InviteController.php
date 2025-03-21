<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InviteController extends AbstractController
{
    public function __construct(private LoggerInterface $logger) {}

    /**
     * Redirects to the appropriate app store based on user agent
     */
    public function index(Request $request): Response
    {
        $ua = strtolower($request->headers->get('User-Agent'));

        if (stripos($ua, 'android') !== false) {
            return $this->redirect("https://play.google.com/store/apps/details?id=nl.healthtrain.ht.prod");
        }

        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            return $this->redirect("https://apps.apple.com/app/healthtrain/id6447962551");
        }

        return $this->redirect($_ENV['APP_WEBSITE'] . "/download/");
    }

    /**
     * Helper function to generate the invite URL
     */
    private function generateInviteUrl(string $property, int $invitationId, ?int $userOrganisationConnectionId): string
    {
        $baseUrl = match($property) {
            'ht_prod' => "https://backend.healthtrain.app",
            'ht_at' => "https://backend-at.healthtrain.dev",
            'ht_it' => "https://backend-it.healthtrain.dev",
            'htger_prod' => "https://backend-germany.healthtrain.app",
            'htger_at' => "https://healthtrain-germany-beheer-at.fenetre.nl",
            default => throw new \InvalidArgumentException("Unknown property")
        };

        return "{$baseUrl}/Invite/{$invitationId}" . ($userOrganisationConnectionId ? "/{$userOrganisationConnectionId}" : "");
    }

    /**
     * Invite for HT_PROD property
     */
    public function invite_ht_prod(?int $invitationId, ?int $userOrganisationConnectionId): Response
    {
        $this->logger->info("Invite link used [HT_PROD] [InvitationId: {$invitationId}] [userOrganisationConnectionId: {$userOrganisationConnectionId}]", [
            'properties' => ['type' => 'invite', 'action' => __FUNCTION__],
            'invitationId' => $invitationId,
            'userOrganisationConnectionId' => $userOrganisationConnectionId
        ]);
        
        return $this->redirect($this->generateInviteUrl('ht_prod', $invitationId, $userOrganisationConnectionId));
    }

    /**
     * Invite for HT_AT property
     */
    public function invite_ht_at(?int $invitationId, ?int $userOrganisationConnectionId): Response
    {
        $this->logger->info("Invite link used [HT_AT] [InvitationId: {$invitationId}] [userOrganisationConnectionId: {$userOrganisationConnectionId}]", [
            'properties' => ['type' => 'invite', 'action' => __FUNCTION__],
            'invitationId' => $invitationId,
            'userOrganisationConnectionId' => $userOrganisationConnectionId
        ]);
        
        return $this->redirect($this->generateInviteUrl('ht_at', $invitationId, $userOrganisationConnectionId));
    }

    /**
     * Invite for HT_IT property
     */
    public function invite_ht_it(?int $invitationId, ?int $userOrganisationConnectionId): Response
    {
        $this->logger->info("Invite link used [HT_IT] [InvitationId: {$invitationId}] [userOrganisationConnectionId: {$userOrganisationConnectionId}]", [
            'properties' => ['type' => 'invite', 'action' => __FUNCTION__],
            'invitationId' => $invitationId,
            'userOrganisationConnectionId' => $userOrganisationConnectionId
        ]);

        return $this->redirect($this->generateInviteUrl('ht_it', $invitationId, $userOrganisationConnectionId));
    }

    /**
     * Invite for HTGER_PROD property
     */
    public function invite_htger_prod(int $invitationId, int $userOrganisationConnectionId): Response
    {
        $this->logger->info("Invite link used [HTGER_PROD] [InvitationId: {$invitationId}] [userOrganisationConnectionId: {$userOrganisationConnectionId}]", [
            'properties' => ['type' => 'invite', 'action' => __FUNCTION__],
            'invitationId' => $invitationId,
            'userOrganisationConnectionId' => $userOrganisationConnectionId
        ]);

        return $this->redirect($this->generateInviteUrl('htger_prod', $invitationId, $userOrganisationConnectionId));
    }

    /**
     * Invite for HTGER_AT property
     */
    public function invite_htger_at(int $invitationId, int $userOrganisationConnectionId): Response
    {
        $this->logger->info("Invite link used [HTGER_AT] [InvitationId: {$invitationId}] [userOrganisationConnectionId: {$userOrganisationConnectionId}]", [
            'properties' => ['type' => 'invite', 'action' => __FUNCTION__],
            'invitationId' => $invitationId,
            'userOrganisationConnectionId' => $userOrganisationConnectionId
        ]);

        return $this->redirect($this->generateInviteUrl('htger_at', $invitationId, $userOrganisationConnectionId));
    }
}

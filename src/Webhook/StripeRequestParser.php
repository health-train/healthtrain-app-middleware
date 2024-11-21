<?php

namespace App\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Psr\Log\LoggerInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class StripeRequestParser extends AbstractRequestParser
{
    public function __construct(private LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    /**
     * @throws JsonException
     */
    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?RemoteEvent
    {

        $signature = $request->headers->get('stripe-signature');
        // Parse the request payload and return a RemoteEvent object.
        $payload = $request->getPayload();

        try {
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $secret
            );
        } catch (\UnexpectedValueException $e) {
            throw new RejectWebhookException(Response::HTTP_BAD_REQUEST, 'Request payload does not contain required fields: ' . $e->getMessage());
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new RejectWebhookException(Response::HTTP_UNAUTHORIZED, 'Error verifying webhook signature: ' . $e->getMessage());
        }

        // Parse the request payload and return a RemoteEvent object.
        $payload = $request->getPayload();

        return new RemoteEvent(
            $payload->getString('type'),
            $payload->getString('id'),
            $payload->all(),
        );
    }
}

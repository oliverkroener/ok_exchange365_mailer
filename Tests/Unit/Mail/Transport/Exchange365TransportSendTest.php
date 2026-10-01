<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Tests\Unit\Mail\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport;
use OliverKroener\OkExchange365\Tests\Unit\AbstractTransportTestCase;

/**
 * Covers the send path around the network: token caching, retry and error
 * wrapping.
 *
 * createHttpClient() is the seam; every response comes from a Guzzle MockHandler.
 * No test here reaches Microsoft.
 */
final class Exchange365TransportSendTest extends AbstractTransportTestCase
{
    private const MAIL_SETTINGS = [
        'transport_exchange365_tenantId' => 'tenant',
        'transport_exchange365_clientId' => 'client',
        'transport_exchange365_clientSecret' => 'secret',
    ];

    private const CONF = [
        'tenantId' => 'tenant',
        'clientId' => 'client',
        'clientSecret' => 'secret',
    ];

    /**
     * @param array<int, Response|\Throwable> $responses
     */
    private function createSubjectWithResponses(array $responses, MockHandler &$handler = null): Exchange365Transport
    {
        $handler = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($handler)]);

        $subject = new class(self::MAIL_SETTINGS) extends Exchange365Transport {
            /** @var ClientInterface */
            public $client;

            protected function createHttpClient(): ClientInterface
            {
                return $this->client;
            }
        };
        $subject->client = $client;

        return $subject;
    }

    private function tokenResponse(string $token = 'token-1', int $expiresIn = 3600): Response
    {
        return new Response(200, [], (string)json_encode(['access_token' => $token, 'expires_in' => $expiresIn]));
    }

    public function testAccessTokenIsFetchedOnceAndReused(): void
    {
        $subject = $this->createSubjectWithResponses([$this->tokenResponse()], $handler);

        self::assertSame('token-1', $this->callPrivate($subject, 'getAccessToken', [self::CONF]));
        // A second call would hit the empty mock queue and throw.
        self::assertSame('token-1', $this->callPrivate($subject, 'getAccessToken', [self::CONF]));
        self::assertSame(0, $handler->count());
    }

    public function testAccessTokenIsRefetchedWhenTheCredentialsChange(): void
    {
        $subject = $this->createSubjectWithResponses([$this->tokenResponse('token-1'), $this->tokenResponse('token-2')]);

        $this->callPrivate($subject, 'getAccessToken', [self::CONF]);

        self::assertSame('token-2', $this->callPrivate($subject, 'getAccessToken', [['clientSecret' => 'rotated'] + self::CONF]));
    }

    public function testAccessTokenIsRefetchedWhenItIsAboutToExpire(): void
    {
        // expires_in below the 60 s safety margin means "already expired".
        $subject = $this->createSubjectWithResponses([$this->tokenResponse('token-1', 30), $this->tokenResponse('token-2')]);

        $this->callPrivate($subject, 'getAccessToken', [self::CONF]);

        self::assertSame('token-2', $this->callPrivate($subject, 'getAccessToken', [self::CONF]));
    }

    public function testTokenRequestUsesTheV2EndpointAndTheDefaultScope(): void
    {
        $subject = $this->createSubjectWithResponses([$this->tokenResponse()], $handler);

        $this->callPrivate($subject, 'getAccessToken', [self::CONF]);
        $request = $handler->getLastRequest();

        self::assertSame('https://login.microsoftonline.com/tenant/oauth2/v2.0/token', (string)$request->getUri());
        parse_str((string)$request->getBody(), $form);
        self::assertSame('https://graph.microsoft.com/.default', $form['scope'] ?? null);
        self::assertSame('client_credentials', $form['grant_type'] ?? null);
    }

    public function testThrottledTokenRequestIsRetriedOnce(): void
    {
        $subject = $this->createSubjectWithResponses([
            new Response(429, ['Retry-After' => '1']),
            $this->tokenResponse(),
        ]);

        self::assertSame('token-1', $this->callPrivate($subject, 'getAccessToken', [self::CONF]));
    }

    public function testClientErrorsAreNotRetried(): void
    {
        $subject = $this->createSubjectWithResponses([new Response(401), $this->tokenResponse()], $handler);

        try {
            $this->callPrivate($subject, 'getAccessToken', [self::CONF]);
            self::fail('a 401 must not be retried');
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            self::assertSame(1, $handler->count(), 'the second response must still be queued');
        }
    }

    public function testDoSendPostsTheMimeMessageToTheResolvedMailbox(): void
    {
        $subject = $this->createSubjectWithResponses([$this->tokenResponse(), new Response(202)], $handler);

        self::assertSame(1, $subject->send($this->message()));
        $request = $handler->getLastRequest();

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://graph.microsoft.com/v1.0/users/from%40example.org/sendMail', (string)$request->getUri());
        self::assertSame('Bearer token-1', $request->getHeaderLine('Authorization'));
        self::assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));
        $body = json_decode((string)$request->getBody(), true);
        self::assertSame('test', $body['message']['subject'] ?? null);
        self::assertFalse($body['saveToSentItems'], 'saveToSentItems must be a JSON boolean');
    }

    public function testTwoMailsShareOneToken(): void
    {
        // Only one token response is queued: a second token request would fail.
        $subject = $this->createSubjectWithResponses([$this->tokenResponse(), new Response(202), new Response(202)], $handler);

        $subject->send($this->message());
        $subject->send($this->message());

        self::assertSame(0, $handler->count());
    }

    public function testFailedConnectionIsRetriedOnce(): void
    {
        $subject = $this->createSubjectWithResponses([
            $this->tokenResponse(),
            new \GuzzleHttp\Exception\ConnectException('unreachable', new \GuzzleHttp\Psr7\Request('POST', 'https://graph.microsoft.com')),
            new Response(202),
        ], $handler);

        $subject->send($this->message());

        self::assertSame(0, $handler->count());
    }

    public function testStalledConnectionIsNotRetriedToAvoidDuplicateMails(): void
    {
        $stalled = new \GuzzleHttp\Exception\ConnectException(
            'timed out',
            new \GuzzleHttp\Psr7\Request('POST', 'https://graph.microsoft.com'),
            null,
            ['connect_time' => 0.02]
        );
        $subject = $this->createSubjectWithResponses([$this->tokenResponse(), $stalled, new Response(202)], $handler);

        try {
            $subject->send($this->message());
            self::fail('a request that stalled after connecting must not be repeated');
        } catch (\RuntimeException $e) {
            self::assertSame(1, $handler->count(), 'the second sendMail response must still be queued');
        }
    }

    public function testGraphErrorsAreWrappedInARuntimeException(): void
    {
        $subject = $this->createSubjectWithResponses([$this->tokenResponse(), new Response(403)]);

        $this->expectException(\RuntimeException::class);

        $subject->send($this->message());
    }

    public function testIncompleteConfigurationFailsWithARuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('clientSecret');

        (new Exchange365Transport(['transport_exchange365_tenantId' => 'tenant', 'transport_exchange365_clientId' => 'client']))
            ->send($this->message());
    }

    private function message(): \Swift_Message
    {
        return (new \Swift_Message('test'))
            ->setFrom('from@example.org')
            ->setTo('to@example.org')
            ->setBody('test');
    }
}

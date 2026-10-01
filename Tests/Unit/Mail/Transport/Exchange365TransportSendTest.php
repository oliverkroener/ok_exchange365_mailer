<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Tests\Unit\Mail\Transport;

use Microsoft\Graph\GraphServiceClient;
use OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport;
use OliverKroener\OkExchange365\Tests\Unit\AbstractTransportTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\SymfonyPsrEventDispatcherAdapter\EventDispatcherAdapter;

/**
 * Covers the send path around the network call: client reuse and error wrapping.
 *
 * createGraphServiceClient() is the seam. No test here reaches Microsoft Graph.
 */
final class Exchange365TransportSendTest extends AbstractTransportTestCase
{
    private const CREDENTIALS = [
        'tenantId' => 'tenant',
        'clientId' => 'client',
        'clientSecret' => 'secret',
    ];

    /**
     * A transport that counts how often it builds a Graph client.
     *
     * @param array<string, mixed> $mailSettings
     */
    private function createCountingSubject(array $mailSettings = []): Exchange365Transport
    {
        $dispatcher = $this->registerDispatcher();

        return new class ($mailSettings, $dispatcher, new NullLogger()) extends Exchange365Transport {
            public int $created = 0;

            protected function createGraphServiceClient(array $conf): GraphServiceClient
            {
                $this->created++;

                return parent::createGraphServiceClient($conf);
            }
        };
    }

    public function testGraphClientIsReusedForTheSameCredentials(): void
    {
        $subject = $this->createCountingSubject();

        $first = $this->callPrivate($subject, 'getGraphServiceClient', [self::CREDENTIALS]);
        $second = $this->callPrivate($subject, 'getGraphServiceClient', [self::CREDENTIALS]);

        self::assertSame($first, $second, 'the same client - and with it the cached token - must be reused');
        self::assertSame(1, $subject->created);
    }

    public function testGraphClientIsRebuiltWhenTheCredentialsChange(): void
    {
        $subject = $this->createCountingSubject();

        $this->callPrivate($subject, 'getGraphServiceClient', [self::CREDENTIALS]);
        $this->callPrivate($subject, 'getGraphServiceClient', [['clientSecret' => 'rotated'] + self::CREDENTIALS]);

        self::assertSame(2, $subject->created, 'a frontend site with its own credentials must not reuse another site\'s client');
    }

    public function testIncompleteConfigurationFailsWithATransportExceptionAndNoNetworkCall(): void
    {
        unset($GLOBALS['TSFE']);
        $subject = $this->createCountingSubject(['transport_exchange365_tenantId' => 'tenant']);

        try {
            $this->callPrivate($subject, 'doSend', [$this->sentMessage()]);
            self::fail('doSend() must throw when credentials are missing');
        } catch (TransportException $e) {
            self::assertInstanceOf(\RuntimeException::class, $e, 'existing catch (\RuntimeException) blocks must keep working');
            self::assertStringContainsString('clientId', $e->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }

        self::assertSame(0, $subject->created);
    }

    public function testErrorsAreWrappedEvenWhenTheyAreNotExceptions(): void
    {
        $dispatcher = $this->registerDispatcher();
        $subject = new class (['transport_exchange365_tenantId' => 'tenant', 'transport_exchange365_clientId' => 'client', 'transport_exchange365_clientSecret' => 'secret'], $dispatcher, new NullLogger()) extends Exchange365Transport {
            protected function createGraphServiceClient(array $conf): GraphServiceClient
            {
                throw new \TypeError('boom');
            }
        };
        unset($GLOBALS['TSFE']);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('boom');

        $this->callPrivate($subject, 'doSend', [$this->sentMessage()]);
    }

    public function testExceptionMessageNeverContainsTheClientSecret(): void
    {
        unset($GLOBALS['TSFE']);
        $subject = $this->createCountingSubject([
            'transport_exchange365_clientId' => 'client',
            'transport_exchange365_clientSecret' => 'super-secret-value',
        ]);

        try {
            $this->callPrivate($subject, 'doSend', [$this->sentMessage()]);
            self::fail('doSend() must throw when tenantId is missing');
        } catch (TransportException $e) {
            self::assertStringNotContainsString('super-secret-value', $e->getMessage());
        }
    }

    /**
     * The TYPO3 11 constructor always fetches the adapter via makeInstance().
     */
    private function registerDispatcher(): EventDispatcherInterface
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
        GeneralUtility::addInstance(EventDispatcherAdapter::class, new EventDispatcherAdapter($dispatcher));

        return $dispatcher;
    }

    private function sentMessage(): SentMessage
    {
        $email = (new Email())
            ->from('from@example.org')
            ->to('to@example.org')
            ->subject('test')
            ->text('test');

        return new SentMessage($email, new Envelope(new Address('from@example.org'), [new Address('to@example.org')]));
    }
}

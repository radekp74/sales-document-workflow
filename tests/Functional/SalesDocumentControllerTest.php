<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\SalesDocument;
use App\Enum\SalesDocumentStatus;
use App\Notification\InMemoryNotifier;
use App\Notification\NotifierPort;
use App\Repository\SalesDocumentRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SalesDocumentControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        self::getContainer()->get('doctrine.orm.entity_manager')
            ->createQuery('DELETE FROM ' . SalesDocument::class)
            ->execute();
    }

    public function testCreateAndApproveThroughHttp(): void
    {
        $this->client->request('POST', '/sales-documents', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'contractor_id' => 77,
            'created_by' => 5,
        ]));
        self::assertResponseStatusCodeSame(201);
        $quoteId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        $this->client->request('POST', "/sales-documents/{$quoteId}/approve", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'approved_by' => 9,
        ]));
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('order', $body['type']);
        self::assertSame($quoteId, $body['parent_quote_id']);
    }

    public function testApprovingMissingDocumentReturns404(): void
    {
        $this->client->request('POST', '/sales-documents/999999/approve', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'approved_by' => 9,
        ]));

        self::assertResponseStatusCodeSame(404);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['error' => 'Sales document not found'], $body);
    }

    public function testApprovingAnAlreadyApprovedDocumentReturns409(): void
    {
        $quoteId = $this->createQuoteThroughHttp();

        $this->client->request('POST', "/sales-documents/{$quoteId}/approve", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'approved_by' => 9,
        ]));
        self::assertResponseIsSuccessful();

        $this->client->request('POST', "/sales-documents/{$quoteId}/approve", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'approved_by' => 9,
        ]));

        self::assertResponseStatusCodeSame(409);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['error' => 'Sales document cannot be approved in its current state'], $body);
    }

    public function testInvalidCreatePayloadReturns400(): void
    {
        $this->client->request('POST', '/sales-documents', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'contractor_id' => 77,
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testApproveWithoutApproverReturns400(): void
    {
        $quoteId = $this->createQuoteThroughHttp();

        $this->client->request('POST', "/sales-documents/{$quoteId}/approve", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testUnexpectedTechnicalFailureReturnsGeneric500WithoutLeakingTheException(): void
    {
        $secret = 'internal database credentials leaked here';

        self::getContainer()->set(MessageBusInterface::class, new class($secret) implements MessageBusInterface {
            public function __construct(private readonly string $secret)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new \LogicException($this->secret);
            }
        });

        $this->client->request('POST', '/sales-documents/1/approve', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'approved_by' => 9,
        ]));

        self::assertResponseStatusCodeSame(500);

        $content = $this->client->getResponse()->getContent();
        self::assertStringNotContainsString($secret, $content);
        self::assertStringNotContainsString('LogicException', $content);
        self::assertSame(['error' => 'Internal server error'], json_decode($content, true));
    }

    public function testApprovalStaysSuccessfulOverHttpWhenTheNotificationChannelFails(): void
    {
        // Bez reboota kontenera podmieniony notifier przeżywa oba żądania.
        $this->client->disableReboot();
        self::getContainer()->set(NotifierPort::class, new InMemoryNotifier(failOnCallNumber: 1));

        $quoteId = $this->createQuoteThroughHttp();

        $this->client->request('POST', "/sales-documents/{$quoteId}/approve", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'approved_by' => 9,
        ]));

        self::assertResponseIsSuccessful();

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('order', $body['type']);
        self::assertSame('approved', $body['status']);

        self::getContainer()->get('doctrine.orm.entity_manager')->clear();
        $quote = self::getContainer()->get(SalesDocumentRepository::class)->find($quoteId);
        self::assertSame(SalesDocumentStatus::Approved, $quote->getStatus());
    }

    private function createQuoteThroughHttp(): int
    {
        $this->client->request('POST', '/sales-documents', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'contractor_id' => 77,
            'created_by' => 5,
        ]));
        self::assertResponseStatusCodeSame(201);

        return json_decode($this->client->getResponse()->getContent(), true)['id'];
    }
}

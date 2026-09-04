<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\SalesDocument;
use App\Message\Command\ApproveSalesDocument;
use App\Message\Command\CreateSalesDocument;
use App\Repository\SalesDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Regresja zgłoszenia supportu: "kontrahent i osoba tworząca dokument są jakby
 * zamienione miejscami, ale nie za każdym razem".
 *
 * Testy sprawdzają realnie zapisane wartości, a nie tylko kod odpowiedzi HTTP —
 * to właśnie brak takiej asercji pozwolił defektowi przetrwać w ścieżce HTTP.
 */
final class SalesDocumentOwnershipTest extends WebTestCase
{
    private const CONTRACTOR_ID = 77;
    private const CREATOR_ID = 5;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        self::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('DELETE FROM ' . SalesDocument::class)
            ->execute();
    }

    public function testHttpCreatePersistsOwnershipFieldsWithoutSwappingThem(): void
    {
        $this->client->request('POST', '/sales-documents', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'contractor_id' => self::CONTRACTOR_ID,
            'created_by' => self::CREATOR_ID,
        ]));

        self::assertResponseStatusCodeSame(201);
        $quoteId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        $quote = $this->readFromDatabase($quoteId);

        self::assertSame(self::CONTRACTOR_ID, $quote->getContractorId(), 'contractor_id must land in contractorId');
        self::assertSame(self::CREATOR_ID, $quote->getCreatedBy(), 'created_by must land in createdBy');
    }

    public function testDirectCommandPathKeepsOwnershipCorrect(): void
    {
        $quoteId = $this->dispatch(new CreateSalesDocument(
            contractorId: self::CONTRACTOR_ID,
            createdBy: self::CREATOR_ID,
        ));

        $quote = $this->readFromDatabase($quoteId);

        self::assertSame(self::CONTRACTOR_ID, $quote->getContractorId());
        self::assertSame(self::CREATOR_ID, $quote->getCreatedBy());
    }

    public function testApprovalPropagatesTheCorrectedOwnershipToSnapshotAndOrder(): void
    {
        $this->client->request('POST', '/sales-documents', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'contractor_id' => self::CONTRACTOR_ID,
            'created_by' => self::CREATOR_ID,
        ]));
        self::assertResponseStatusCodeSame(201);
        $quoteId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        $orderId = $this->dispatch(new ApproveSalesDocument(documentId: $quoteId, approvedBy: 9));

        $quote = $this->readFromDatabase($quoteId);
        $order = $this->readFromDatabase($orderId);

        self::assertSame(self::CONTRACTOR_ID, $quote->getSellerSnapshot()['contractor_id']);
        self::assertSame(self::CONTRACTOR_ID, $order->getContractorId(), 'the spawned order keeps the contractor');
        self::assertSame(9, $order->getCreatedBy(), 'the order is created by the approver');
    }

    private function dispatch(object $command): int
    {
        $envelope = self::getContainer()->get(MessageBusInterface::class)->dispatch($command);

        return $envelope->last(HandledStamp::class)->getResult();
    }

    private function readFromDatabase(int $id): SalesDocument
    {
        // Czyścimy identity map, aby asercje dotyczyły wartości odczytanych z PostgreSQL,
        // a nie obiektu pozostawionego w pamięci przez żądanie.
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $document = self::getContainer()->get(SalesDocumentRepository::class)->find($id);
        self::assertInstanceOf(SalesDocument::class, $document);

        return $document;
    }
}

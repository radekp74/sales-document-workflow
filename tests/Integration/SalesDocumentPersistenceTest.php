<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\SalesDocument;
use App\Enum\SalesDocumentStatus;
use App\Enum\SalesDocumentType;
use App\Message\Command\ApproveSalesDocument;
use App\Message\Command\CreateSalesDocument;
use App\Message\Command\RejectSalesDocument;
use App\Repository\SalesDocumentRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Weryfikacja współpracy z realnym PostgreSQL: mapowanie kolumn dodanych migracją,
 * konwersja typów Doctrine oraz zachowanie transakcji.
 *
 * Zakres celowo nie powiela testów funkcjonalnych — sprawdza to, czego nie widać
 * na poziomie Kernel/HTTP.
 */
final class SalesDocumentPersistenceTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        self::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('DELETE FROM ' . SalesDocument::class)
            ->execute();
    }

    public function testRejectionAuditColumnsRoundTripThroughPostgres(): void
    {
        $quoteId = $this->dispatch(new CreateSalesDocument(contractorId: 77, createdBy: 5));
        $this->dispatch(new RejectSalesDocument(documentId: $quoteId, rejectedBy: 9));

        $row = $this->fetchRow($quoteId);

        self::assertSame('rejected', $row['status']);
        self::assertSame(9, (int) $row['rejected_by']);
        self::assertNotNull($row['rejected_at'], 'migracja musi utrwalać znacznik czasu odrzucenia');

        // Odczyt przez ORM potwierdza konwersję kolumny na DateTimeImmutable.
        $document = $this->readThroughOrm($quoteId);
        self::assertInstanceOf(\DateTimeImmutable::class, $document->getRejectedAt());
        self::assertSame(
            $row['rejected_at'],
            $document->getRejectedAt()->format('Y-m-d H:i:s'),
        );
    }

    public function testApprovalPersistsQuoteAndLinkedOrderInOneTransaction(): void
    {
        $quoteId = $this->dispatch(new CreateSalesDocument(contractorId: 77, createdBy: 5));
        $orderId = $this->dispatch(new ApproveSalesDocument(documentId: $quoteId, approvedBy: 9));

        self::assertSame(2, $this->countDocuments());

        $order = $this->readThroughOrm($orderId);
        self::assertSame(SalesDocumentType::Order, $order->getType());
        self::assertSame(SalesDocumentStatus::Approved, $order->getStatus());
        self::assertSame($quoteId, $order->getParentQuoteId());
    }

    public function testFailedApprovalRollsBackWithoutLeavingAPartialOrder(): void
    {
        $quoteId = $this->dispatch(new CreateSalesDocument(contractorId: 77, createdBy: 5));
        $this->dispatch(new ApproveSalesDocument(documentId: $quoteId, approvedBy: 9));

        self::assertSame(2, $this->countDocuments());

        // Druga próba zatwierdzenia jest niedozwolona i nie może utworzyć kolejnego zamówienia.
        try {
            $this->dispatch(new ApproveSalesDocument(documentId: $quoteId, approvedBy: 9));
            self::fail('Expected the second approval to fail');
        } catch (HandlerFailedException) {
            // oczekiwane
        }

        self::assertSame(2, $this->countDocuments(), 'nieudane zatwierdzenie nie może zostawić śladu w bazie');
    }

    public function testRepositoryReturnsNullForAMissingDocument(): void
    {
        self::assertNull(self::getContainer()->get(SalesDocumentRepository::class)->find(999999));
    }

    private function dispatch(object $command): mixed
    {
        $envelope = self::getContainer()->get(MessageBusInterface::class)->dispatch($command);

        return $envelope->last(HandledStamp::class)?->getResult();
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRow(int $id): array
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $row = $connection->fetchAssociative('SELECT * FROM sales_document WHERE id = ?', [$id]);

        self::assertIsArray($row);

        return $row;
    }

    private function countDocuments(): int
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();

        return (int) $connection->fetchOne('SELECT COUNT(*) FROM sales_document');
    }

    private function readThroughOrm(int $id): SalesDocument
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $document = self::getContainer()->get(SalesDocumentRepository::class)->find($id);
        self::assertInstanceOf(SalesDocument::class, $document);

        return $document;
    }
}

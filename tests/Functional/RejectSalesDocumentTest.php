<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\SalesDocument;
use App\Enum\SalesDocumentStatus;
use App\Exception\InvalidSalesDocumentState;
use App\Exception\SalesDocumentNotFound;
use App\Message\Command\ApproveSalesDocument;
use App\Message\Command\CreateSalesDocument;
use App\Message\Command\RejectSalesDocument;
use App\Repository\SalesDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Rozszerzone pokrycie operacji reject. Plik dostarczony wraz z zadaniem
 * (RejectSalesDocumentHandlerTest) pozostaje nietknięty.
 */
final class RejectSalesDocumentTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        self::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('DELETE FROM ' . SalesDocument::class)
            ->execute();
    }

    public function testRejectionPersistsTheAuditMetadata(): void
    {
        $before = new \DateTimeImmutable();
        $quoteId = $this->dispatch(new CreateSalesDocument(contractorId: 77, createdBy: 5));

        $this->dispatch(new RejectSalesDocument(documentId: $quoteId, rejectedBy: 9));

        $document = $this->readFromDatabase($quoteId);

        self::assertSame(SalesDocumentStatus::Rejected, $document->getStatus());
        self::assertSame(9, $document->getRejectedBy());
        self::assertNotNull($document->getRejectedAt());
        self::assertGreaterThanOrEqual(
            $before->getTimestamp(),
            $document->getRejectedAt()->getTimestamp(),
        );
        self::assertNull($document->getApprovedBy(), 'rejection must not fill approval metadata');
        self::assertNull($document->getApprovedAt());
    }

    public function testRejectingAnApprovedDocumentIsAnInvalidStateTransition(): void
    {
        $quoteId = $this->dispatch(new CreateSalesDocument(contractorId: 77, createdBy: 5));
        $this->dispatch(new ApproveSalesDocument($quoteId, 9));

        $exception = $this->dispatchExpectingFailure(new RejectSalesDocument(documentId: $quoteId, rejectedBy: 9));

        self::assertInstanceOf(InvalidSalesDocumentState::class, $exception);
        self::assertSame(SalesDocumentStatus::Approved, $this->readFromDatabase($quoteId)->getStatus());
    }

    public function testRejectingAnAlreadyRejectedDocumentIsAnInvalidStateTransition(): void
    {
        $quoteId = $this->dispatch(new CreateSalesDocument(contractorId: 77, createdBy: 5));
        $this->dispatch(new RejectSalesDocument(documentId: $quoteId, rejectedBy: 9));

        $exception = $this->dispatchExpectingFailure(new RejectSalesDocument(documentId: $quoteId, rejectedBy: 11));

        self::assertInstanceOf(InvalidSalesDocumentState::class, $exception);

        $document = $this->readFromDatabase($quoteId);
        self::assertSame(SalesDocumentStatus::Rejected, $document->getStatus());
        self::assertSame(9, $document->getRejectedBy(), 'the original rejecter must be preserved');
    }

    public function testRejectingAMissingDocumentReportsNotFound(): void
    {
        $exception = $this->dispatchExpectingFailure(new RejectSalesDocument(documentId: 999999, rejectedBy: 9));

        self::assertInstanceOf(SalesDocumentNotFound::class, $exception);
    }

    public function testApprovingARejectedDocumentIsAnInvalidStateTransition(): void
    {
        $quoteId = $this->dispatch(new CreateSalesDocument(contractorId: 77, createdBy: 5));
        $this->dispatch(new RejectSalesDocument(documentId: $quoteId, rejectedBy: 9));

        $exception = $this->dispatchExpectingFailure(new ApproveSalesDocument($quoteId, 9));

        self::assertInstanceOf(InvalidSalesDocumentState::class, $exception);
        self::assertSame(SalesDocumentStatus::Rejected, $this->readFromDatabase($quoteId)->getStatus());
    }

    private function dispatch(object $command): mixed
    {
        $envelope = self::getContainer()->get(MessageBusInterface::class)->dispatch($command);

        return $envelope->last(HandledStamp::class)?->getResult();
    }

    private function dispatchExpectingFailure(object $command): \Throwable
    {
        try {
            $this->dispatch($command);
        } catch (HandlerFailedException $exception) {
            return $exception->getPrevious() ?? $exception;
        }

        self::fail('Expected the command to fail');
    }

    private function readFromDatabase(int $id): SalesDocument
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $document = self::getContainer()->get(SalesDocumentRepository::class)->find($id);
        self::assertInstanceOf(SalesDocument::class, $document);

        return $document;
    }
}

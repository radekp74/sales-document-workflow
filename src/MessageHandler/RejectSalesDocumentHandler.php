<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\SalesDocumentStatus;
use App\Exception\InvalidSalesDocumentState;
use App\Exception\SalesDocumentNotFound;
use App\Message\Command\RejectSalesDocument;
use App\Repository\SalesDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RejectSalesDocumentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SalesDocumentRepository $repository,
    ) {
    }

    public function __invoke(RejectSalesDocument $command): void
    {
        $this->entityManager->wrapInTransaction(function () use ($command): void {
            $document = $this->repository->find($command->documentId);

            if ($document === null) {
                throw SalesDocumentNotFound::withId($command->documentId);
            }

            // Odrzucić można wyłącznie dokument w stanie roboczym. Dotyczy to także
            // dokumentu już odrzuconego — powtórne odrzucenie nie jest przejściem stanu.
            if ($document->getStatus() !== SalesDocumentStatus::Draft) {
                throw InvalidSalesDocumentState::cannotReject($command->documentId, $document->getStatus());
            }

            $document->setStatus(SalesDocumentStatus::Rejected);
            $document->setRejectedBy($command->rejectedBy);
            $document->setRejectedAt(new \DateTimeImmutable());

            $this->entityManager->flush();
        });
    }
}

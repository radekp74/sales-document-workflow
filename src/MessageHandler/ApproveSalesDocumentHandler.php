<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\SalesDocument;
use App\Enum\SalesDocumentStatus;
use App\Enum\SalesDocumentType;
use App\Exception\InvalidSalesDocumentState;
use App\Exception\SalesDocumentNotFound;
use App\Message\Command\ApproveSalesDocument;
use App\Notification\NotifierPort;
use App\Repository\SalesDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ApproveSalesDocumentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SalesDocumentRepository $repository,
        private readonly NotifierPort $notifier,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ApproveSalesDocument $command): int
    {
        // Jeden punkt czasu dla całego zdarzenia approval — quote, order i snapshot
        // opisują to samo zdarzenie biznesowe i nie powinny się rozjeżdżać.
        $approvedAt = new \DateTimeImmutable();

        $approvedId = $this->entityManager->wrapInTransaction(
            fn (): int => $this->approve($command, $approvedAt),
        );

        // Notyfikacje są efektem ubocznym wykonywanym po trwałym commicie.
        // Ich awaria nie może zmienić wyniku zatwierdzenia — patrz ADR-003.
        $this->notifyAboutApproval($approvedId);

        return $approvedId;
    }

    private function approve(ApproveSalesDocument $command, \DateTimeImmutable $approvedAt): int
    {
        $document = $this->repository->find($command->documentId);
        if ($document === null) {
            throw SalesDocumentNotFound::withId($command->documentId);
        }
        if ($document->getStatus() !== SalesDocumentStatus::Draft) {
            throw InvalidSalesDocumentState::cannotApprove($command->documentId, $document->getStatus());
        }

        $document->setStatus(SalesDocumentStatus::Approved);
        $document->setApprovedBy($command->approvedBy);
        $document->setApprovedAt($approvedAt);
        $document->setSellerSnapshot($this->buildSellerSnapshot($document, $approvedAt));

        $quoteId = $document->getId();
        \assert($quoteId !== null, 'Zapisany dokument ma zawsze identyfikator');
        $approvedId = $quoteId;

        if ($document->getType() === SalesDocumentType::Quote) {
            $order = new SalesDocument();
            $order->setContractorId($document->getContractorId());
            $order->setCreatedBy($command->approvedBy);
            $order->setType(SalesDocumentType::Order);
            $order->setStatus(SalesDocumentStatus::Approved);
            $order->setApprovedBy($command->approvedBy);
            $order->setApprovedAt($approvedAt);
            $order->setParentQuoteId($quoteId);
            $order->setSellerSnapshot($document->getSellerSnapshot());
            $this->entityManager->persist($order);
            $this->entityManager->flush();

            $orderId = $order->getId();
            \assert($orderId !== null, 'Doctrine przypisuje identyfikator podczas flush');
            $approvedId = $orderId;
        }

        return $approvedId;
    }

    private function notifyAboutApproval(int $approvedId): void
    {
        $document = $this->repository->find($approvedId);

        if ($document === null) {
            $this->logger->error('Approved sales document is not readable for notification', [
                'documentId' => $approvedId,
            ]);

            return;
        }

        $message = "Document #{$approvedId} has been approved";

        // Każdy odbiorca ma niezależną próbę wysyłki: awaria pierwszego kanału
        // nie może zablokować powiadomienia drugiej strony.
        $this->notifySafely($document->getCreatedBy(), $message, $approvedId);
        $this->notifySafely($document->getContractorId(), $message, $approvedId);
    }

    private function notifySafely(int $userId, string $message, int $documentId): void
    {
        try {
            $this->notifier->notify($userId, $message);
        } catch (\Throwable $exception) {
            $this->logger->error('Approval notification failed', [
                'documentId' => $documentId,
                'userId' => $userId,
                'exception' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSellerSnapshot(SalesDocument $document, \DateTimeImmutable $snapshotAt): array
    {
        return [
            'contractor_id' => $document->getContractorId(),
            'snapshot_at' => $snapshotAt->format(DATE_ATOM),
        ];
    }
}

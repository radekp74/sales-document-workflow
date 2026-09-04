<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SalesDocument;
use App\Exception\InvalidSalesDocumentState;
use App\Exception\SalesDocumentNotFound;
use App\Message\Command\ApproveSalesDocument;
use App\Message\Command\CreateSalesDocument;
use App\Repository\SalesDocumentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class SalesDocumentController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly SalesDocumentRepository $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/sales-documents', name: 'sales_document_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);

        if (!$this->isPositiveInt($payload['contractor_id'] ?? null)
            || !$this->isPositiveInt($payload['created_by'] ?? null)
        ) {
            return new JsonResponse(['error' => 'Missing fields'], 400);
        }

        $ownership = $this->resolveDocumentOwnership($payload);

        try {
            $id = $this->dispatchCommand(new CreateSalesDocument(
                contractorId: $ownership['contractorId'],
                createdBy: $ownership['createdBy'],
            ));
        } catch (\Throwable $exception) {
            return $this->unexpectedFailure($exception, 'create');
        }

        return new JsonResponse(['id' => $id], 201);
    }

    #[Route('/sales-documents/{id}/approve', name: 'sales_document_approve', methods: ['POST'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);

        if (!$this->isPositiveInt($payload['approved_by'] ?? null)) {
            return new JsonResponse(['error' => 'Missing fields'], 400);
        }

        try {
            $resultId = $this->dispatchCommand(new ApproveSalesDocument($id, (int) $payload['approved_by']));
        } catch (SalesDocumentNotFound) {
            return new JsonResponse(['error' => 'Sales document not found'], 404);
        } catch (InvalidSalesDocumentState) {
            return new JsonResponse(['error' => 'Sales document cannot be approved in its current state'], 409);
        } catch (\Throwable $exception) {
            return $this->unexpectedFailure($exception, 'approve');
        }

        $document = $this->repository->find($resultId);

        if (!$document instanceof SalesDocument) {
            // Komenda zakończyła się sukcesem, więc brak dokumentu jest niespójnością
            // techniczną po stronie serwera, a nie brakiem zasobu po stronie klienta.
            return $this->unexpectedFailure(
                new \RuntimeException(sprintf('Approved sales document %s is not readable', var_export($resultId, true))),
                'approve',
            );
        }

        return new JsonResponse([
            'id' => $document->getId(),
            'type' => $document->getType()->value,
            'status' => $document->getStatus()->value,
            'parent_quote_id' => $document->getParentQuoteId(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{contractorId: int, createdBy: int}
     */
    private function resolveDocumentOwnership(array $payload): array
    {
        return [
            'contractorId' => (int) $payload['contractor_id'],
            'createdBy' => (int) $payload['created_by'],
        ];
    }

    /**
     * Zwraca wynik handlera i rozpakowuje wyjątki opakowane przez Messenger,
     * aby warstwa HTTP mogła mapować konkretne typy błędów aplikacyjnych.
     */
    private function dispatchCommand(object $command): ?int
    {
        try {
            $envelope = $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        $result = $envelope->last(HandledStamp::class)?->getResult();

        return is_int($result) ? $result : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    private function isPositiveInt(mixed $value): bool
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value > 0;
    }

    private function unexpectedFailure(\Throwable $exception, string $operation): JsonResponse
    {
        $this->logger->error('Sales document {operation} failed unexpectedly', [
            'operation' => $operation,
            'exception' => $exception,
        ]);

        return new JsonResponse(['error' => 'Internal server error'], 500);
    }
}

<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\SalesDocumentStatus;

/**
 * Dokument istnieje, ale jego aktualny stan nie pozwala na żądaną operację.
 *
 * Rozszerza RuntimeException, aby zachować kompatybilność z dostarczonymi testami,
 * które oczekują tego typu bazowego.
 */
final class InvalidSalesDocumentState extends \RuntimeException
{
    public static function cannotApprove(int $documentId, SalesDocumentStatus $currentStatus): self
    {
        return new self(sprintf(
            'Sales document %d cannot be approved in status "%s"',
            $documentId,
            $currentStatus->value,
        ));
    }

    public static function cannotReject(int $documentId, SalesDocumentStatus $currentStatus): self
    {
        return new self(sprintf(
            'Sales document %d cannot be rejected in status "%s"',
            $documentId,
            $currentStatus->value,
        ));
    }
}

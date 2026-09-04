<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Żądany dokument sprzedażowy nie istnieje.
 *
 * Rozszerza RuntimeException, aby zachować kompatybilność z dostarczonymi testami,
 * które oczekują tego typu bazowego.
 */
final class SalesDocumentNotFound extends \RuntimeException
{
    public static function withId(int $documentId): self
    {
        return new self(sprintf('Sales document %d not found', $documentId));
    }
}

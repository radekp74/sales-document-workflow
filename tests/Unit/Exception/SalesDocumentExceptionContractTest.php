<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Enum\SalesDocumentStatus;
use App\Exception\InvalidSalesDocumentState;
use App\Exception\SalesDocumentNotFound;
use PHPUnit\Framework\TestCase;

/**
 * Kontroler mapuje odpowiedzi HTTP po typie wyjątku, a dostarczone testy oczekują
 * zgodności z RuntimeException. Oba założenia są chronione tutaj, bez uruchamiania
 * Symfony Kernel ani bazy danych.
 */
final class SalesDocumentExceptionContractTest extends TestCase
{
    public function testBothApplicationExceptionsStayCompatibleWithRuntimeException(): void
    {
        self::assertInstanceOf(\RuntimeException::class, SalesDocumentNotFound::withId(1));
        self::assertInstanceOf(
            \RuntimeException::class,
            InvalidSalesDocumentState::cannotApprove(1, SalesDocumentStatus::Approved),
        );
    }

    public function testNotFoundAndInvalidStateAreDistinctTypes(): void
    {
        $notFound = SalesDocumentNotFound::withId(1);
        $invalidState = InvalidSalesDocumentState::cannotReject(1, SalesDocumentStatus::Approved);

        self::assertNotInstanceOf(InvalidSalesDocumentState::class, $notFound);
        self::assertNotInstanceOf(SalesDocumentNotFound::class, $invalidState);
    }

    public function testMessagesCarryTheDocumentIdAndStatusForDiagnostics(): void
    {
        self::assertSame('Sales document 42 not found', SalesDocumentNotFound::withId(42)->getMessage());
        self::assertSame(
            'Sales document 42 cannot be approved in status "approved"',
            InvalidSalesDocumentState::cannotApprove(42, SalesDocumentStatus::Approved)->getMessage(),
        );
        self::assertSame(
            'Sales document 42 cannot be rejected in status "rejected"',
            InvalidSalesDocumentState::cannotReject(42, SalesDocumentStatus::Rejected)->getMessage(),
        );
    }
}

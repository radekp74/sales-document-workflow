<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\SalesDocumentController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Regresja T010: kontroler nie może wrócić do wykonywania własnego SQL zamiast
 * korzystać z repozytorium. Zgłoszone w TASK.MD jako część problemu 2.
 */
final class SalesDocumentControllerBoundaryTest extends TestCase
{
    public function testControllerDoesNotDependOnTheEntityManager(): void
    {
        $constructor = (new \ReflectionClass(SalesDocumentController::class))->getConstructor();
        self::assertNotNull($constructor);

        $types = array_map(
            static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(),
            $constructor->getParameters(),
        );

        self::assertNotContains(EntityManagerInterface::class, $types);
    }

    public function testControllerDoesNotExecuteRawSql(): void
    {
        $fileName = (new \ReflectionClass(SalesDocumentController::class))->getFileName();
        self::assertIsString($fileName);

        $source = file_get_contents($fileName);
        self::assertIsString($source);

        self::assertStringNotContainsString('getConnection(', $source);
        self::assertStringNotContainsString('fetchAssociative(', $source);
        self::assertDoesNotMatchRegularExpression('/\bSELECT\b/i', $source);
    }
}

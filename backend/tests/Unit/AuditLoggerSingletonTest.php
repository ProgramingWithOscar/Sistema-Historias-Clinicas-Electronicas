<?php

namespace Tests\Unit;

use App\Support\Audit\AuditLogger;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class AuditLoggerSingletonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AuditLogger::resetInstance();
    }

    #[Test]
    public function devuelve_siempre_la_misma_instancia(): void
    {
        $this->assertSame(AuditLogger::getInstance(), AuditLogger::getInstance());
    }

    #[Test]
    public function el_constructor_es_privado(): void
    {
        $constructor = (new ReflectionClass(AuditLogger::class))->getConstructor();

        $this->assertTrue($constructor->isPrivate());
    }

    #[Test]
    public function no_puede_clonarse(): void
    {
        $this->assertTrue(
            (new ReflectionClass(AuditLogger::class))->getMethod('__clone')->isPrivate()
        );
    }

    #[Test]
    public function no_puede_deserializarse(): void
    {
        $this->expectException(\LogicException::class);

        AuditLogger::getInstance()->__wakeup();
    }

    #[Test]
    public function el_contenedor_resuelve_la_misma_instancia_que_get_instance(): void
    {
        $this->assertSame(
            AuditLogger::getInstance(),
            $this->app->make(AuditLogger::class)
        );
    }

    #[Test]
    public function comparte_request_id_y_secuencia_entre_llamadas_desacopladas(): void
    {
        AuditLogger::getInstance()->record('primero');
        AuditLogger::getInstance()->record('segundo');

        // Dos llamadas hechas desde puntos distintos del código: si hubiera dos
        // instancias, cada una arrancaría su propia secuencia en 1.
        $this->assertSame(2, AuditLogger::getInstance()->eventCount());
    }
}

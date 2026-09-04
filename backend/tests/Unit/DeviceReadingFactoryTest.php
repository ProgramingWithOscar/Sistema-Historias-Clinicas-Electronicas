<?php

namespace Tests\Unit;

use App\Support\Iot\DeviceReadingFactory;
use App\Support\Iot\DeviceReadingFactoryResolver;
use App\Support\Iot\GlucometerFactory;
use App\Support\Iot\PulseOximeterFactory;
use App\Support\Iot\Readings\BloodPressureReading;
use App\Support\Iot\Readings\ClinicalReading;
use App\Support\Iot\Readings\GlucoseReading;
use App\Support\Iot\Readings\OxygenSaturationReading;
use App\Support\Iot\Readings\ReadingSeverity;
use App\Support\Iot\SphygmomanometerFactory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class DeviceReadingFactoryTest extends TestCase
{
    #[Test]
    public function el_creador_es_abstracto_y_el_metodo_fabrica_tambien(): void
    {
        $clase = new ReflectionClass(DeviceReadingFactory::class);

        $this->assertTrue($clase->isAbstract());
        $this->assertTrue($clase->getMethod('makeReading')->isAbstract());
    }

    #[Test]
    public function el_flujo_de_ingesta_es_final_y_no_puede_sobreescribirse(): void
    {
        // Si una subclase pudiera reescribir `ingest()`, podría saltarse la
        // auditoría obligatoria de la HCEI.
        $this->assertTrue(
            (new ReflectionClass(DeviceReadingFactory::class))->getMethod('ingest')->isFinal()
        );
    }

    #[Test]
    public function cada_creador_concreto_devuelve_su_propio_producto(): void
    {
        $this->assertInstanceOf(
            GlucoseReading::class,
            $this->reading(new GlucometerFactory, ['mg_dl' => 95])
        );

        $this->assertInstanceOf(
            BloodPressureReading::class,
            $this->reading(new SphygmomanometerFactory, ['systolic' => 120, 'diastolic' => 80])
        );

        $this->assertInstanceOf(
            OxygenSaturationReading::class,
            $this->reading(new PulseOximeterFactory, ['spo2' => 98])
        );
    }

    #[Test]
    public function todos_los_productos_cumplen_el_mismo_contrato(): void
    {
        $productos = [
            $this->reading(new GlucometerFactory, ['mg_dl' => 95]),
            $this->reading(new SphygmomanometerFactory, ['systolic' => 120, 'diastolic' => 80]),
            $this->reading(new PulseOximeterFactory, ['spo2' => 98]),
        ];

        foreach ($productos as $producto) {
            $this->assertInstanceOf(ClinicalReading::class, $producto);
            $this->assertNotEmpty($producto->loincCode());
            $this->assertNotEmpty($producto->unit());
            $this->assertInstanceOf(ReadingSeverity::class, $producto->severity());
        }
    }

    #[Test]
    public function el_resolver_entrega_el_creador_que_corresponde(): void
    {
        $resolver = new DeviceReadingFactoryResolver;

        $this->assertInstanceOf(GlucometerFactory::class, $resolver->for('glucometer'));
        $this->assertInstanceOf(SphygmomanometerFactory::class, $resolver->for('sphygmomanometer'));
        $this->assertInstanceOf(PulseOximeterFactory::class, $resolver->for('pulse_oximeter'));
    }

    #[Test]
    public function el_resolver_rechaza_un_dispositivo_desconocido(): void
    {
        $this->expectException(ValidationException::class);

        (new DeviceReadingFactoryResolver)->for('tostadora');
    }

    #[Test]
    public function la_glucemia_interpreta_el_ayuno_en_sus_umbrales(): void
    {
        // 150 mg/dL es normal tras comer, pero elevado en ayunas: la misma
        // cifra cambia de severidad y esa lógica vive en el producto.
        $postprandial = new GlucoseReading(150, fasting: false);
        $ayunas = new GlucoseReading(150, fasting: true);

        $this->assertSame(ReadingSeverity::Normal, $postprandial->severity());
        $this->assertSame(ReadingSeverity::Warning, $ayunas->severity());
        $this->assertNotSame($postprandial->loincCode(), $ayunas->loincCode());
    }

    #[Test]
    #[DataProvider('severidades')]
    public function cada_producto_clasifica_su_criticidad(ClinicalReading $reading, ReadingSeverity $esperada): void
    {
        $this->assertSame($esperada, $reading->severity());
    }

    /** @return array<string, array{ClinicalReading, ReadingSeverity}> */
    public static function severidades(): array
    {
        return [
            'glucemia normal' => [new GlucoseReading(95), ReadingSeverity::Normal],
            'hipoglucemia severa' => [new GlucoseReading(45), ReadingSeverity::Critical],
            'hiperglucemia' => [new GlucoseReading(260), ReadingSeverity::Warning],
            'presión normal' => [new BloodPressureReading(120, 80), ReadingSeverity::Normal],
            'hipertensión' => [new BloodPressureReading(150, 95), ReadingSeverity::Warning],
            'crisis hipertensiva' => [new BloodPressureReading(190, 125), ReadingSeverity::Critical],
            'spo2 normal' => [new OxygenSaturationReading(98), ReadingSeverity::Normal],
            'hipoxemia' => [new OxygenSaturationReading(88), ReadingSeverity::Critical],
        ];
    }

    /**
     * Invoca el método fábrica protegido para comprobar qué producto crea.
     *
     * @param  array<string, mixed>  $payload
     */
    private function reading(DeviceReadingFactory $factory, array $payload): ClinicalReading
    {
        $metodo = (new ReflectionClass($factory))->getMethod('makeReading');
        $metodo->setAccessible(true);

        return $metodo->invoke($factory, $payload);
    }
}

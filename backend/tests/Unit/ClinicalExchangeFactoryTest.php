<?php

namespace Tests\Unit;

use App\Support\Interop\Anonymized\AnonymizedDatasetEnvelope;
use App\Support\Interop\Anonymized\AnonymizedExchangeFactory;
use App\Support\Interop\Anonymized\AnonymizedObservationSerializer;
use App\Support\Interop\Anonymized\AnonymizedPatientSerializer;
use App\Support\Interop\ClinicalExchangeFactoryResolver;
use App\Support\Interop\Contracts\ClinicalExchangeFactory;
use App\Support\Interop\Contracts\ExchangeEnvelope;
use App\Support\Interop\Contracts\ObservationSerializer;
use App\Support\Interop\Contracts\PatientSerializer;
use App\Support\Interop\ExchangeStandard;
use App\Support\Interop\Fhir\FhirBundleEnvelope;
use App\Support\Interop\Fhir\FhirObservationSerializer;
use App\Support\Interop\Fhir\FhirPatientSerializer;
use App\Support\Interop\Fhir\FhirR4ExchangeFactory;
use App\Support\Interop\Rda\RdaDocumentEnvelope;
use App\Support\Interop\Rda\RdaExchangeFactory;
use App\Support\Interop\Rda\RdaObservationSerializer;
use App\Support\Interop\Rda\RdaPatientSerializer;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClinicalExchangeFactoryTest extends TestCase
{
    #[Test]
    public function cada_fabrica_concreta_entrega_la_familia_completa_de_su_estandar(): void
    {
        $fhir = new FhirR4ExchangeFactory;

        $this->assertInstanceOf(FhirPatientSerializer::class, $fhir->createPatientSerializer());
        $this->assertInstanceOf(FhirObservationSerializer::class, $fhir->createObservationSerializer());
        $this->assertInstanceOf(FhirBundleEnvelope::class, $fhir->createEnvelope());

        $rda = new RdaExchangeFactory;

        $this->assertInstanceOf(RdaPatientSerializer::class, $rda->createPatientSerializer());
        $this->assertInstanceOf(RdaObservationSerializer::class, $rda->createObservationSerializer());
        $this->assertInstanceOf(RdaDocumentEnvelope::class, $rda->createEnvelope());

        $anonimo = new AnonymizedExchangeFactory;

        $this->assertInstanceOf(AnonymizedPatientSerializer::class, $anonimo->createPatientSerializer());
        $this->assertInstanceOf(AnonymizedObservationSerializer::class, $anonimo->createObservationSerializer());
        $this->assertInstanceOf(AnonymizedDatasetEnvelope::class, $anonimo->createEnvelope());
    }

    #[Test]
    #[DataProvider('familias')]
    public function todas_las_familias_cumplen_los_mismos_contratos_abstractos(ClinicalExchangeFactory $factory): void
    {
        // El cliente sólo ve estas tres interfaces: por eso puede trabajar con
        // cualquier familia sin conocerla.
        $this->assertInstanceOf(PatientSerializer::class, $factory->createPatientSerializer());
        $this->assertInstanceOf(ObservationSerializer::class, $factory->createObservationSerializer());
        $this->assertInstanceOf(ExchangeEnvelope::class, $factory->createEnvelope());
        $this->assertInstanceOf(ExchangeStandard::class, $factory->standard());
    }

    /** @return array<string, array{ClinicalExchangeFactory}> */
    public static function familias(): array
    {
        return [
            'FHIR R4' => [new FhirR4ExchangeFactory],
            'RDA' => [new RdaExchangeFactory],
            'anonimizada' => [new AnonymizedExchangeFactory],
        ];
    }

    #[Test]
    public function ninguna_familia_comparte_productos_con_otra(): void
    {
        // Si dos familias devolvieran el mismo objeto, mezclar sus productos
        // sería posible y el patrón no estaría aportando nada.
        $productos = array_map(
            fn (ClinicalExchangeFactory $f) => [
                $f->createPatientSerializer()::class,
                $f->createObservationSerializer()::class,
                $f->createEnvelope()::class,
            ],
            [new FhirR4ExchangeFactory, new RdaExchangeFactory, new AnonymizedExchangeFactory],
        );

        $todos = array_merge(...$productos);

        $this->assertSame($todos, array_unique($todos));
    }

    #[Test]
    public function el_resolver_entrega_la_fabrica_del_estandar_pedido(): void
    {
        $resolver = new ClinicalExchangeFactoryResolver;

        $this->assertInstanceOf(FhirR4ExchangeFactory::class, $resolver->for('fhir_r4'));
        $this->assertInstanceOf(RdaExchangeFactory::class, $resolver->for('rda'));
        $this->assertInstanceOf(AnonymizedExchangeFactory::class, $resolver->for('anonymized'));
    }

    #[Test]
    public function el_resolver_rechaza_un_estandar_desconocido(): void
    {
        $this->expectException(ValidationException::class);

        (new ClinicalExchangeFactoryResolver)->for('hoja-de-calculo');
    }

    #[Test]
    public function cada_sobre_declara_su_propio_media_type(): void
    {
        // El Bundle de FHIR no se publica como JSON genérico: tiene su propio
        // media type, y ése es un dato del producto, no del cliente.
        $this->assertSame('application/fhir+json', (new FhirR4ExchangeFactory)->createEnvelope()->mediaType());
        $this->assertSame('application/json', (new RdaExchangeFactory)->createEnvelope()->mediaType());
    }
}

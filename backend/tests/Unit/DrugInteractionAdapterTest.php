<?php

namespace Tests\Unit;

use App\Support\Interactions\Adapters\RxNavInteractionAdapter;
use App\Support\Interactions\Adapters\VademecumInteractionAdapter;
use App\Support\Interactions\Contracts\DrugInteractionChecker;
use App\Support\Interactions\External\RxNavClient;
use App\Support\Interactions\External\VademecumNacionalReader;
use App\Support\Interactions\InteractionCheckerResolver;
use App\Support\Interactions\InteractionReport;
use App\Support\Interactions\InteractionSeverity;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class DrugInteractionAdapterTest extends TestCase
{
    #[Test]
    public function el_adaptee_no_implementa_el_contrato_del_sistema(): void
    {
        // Si lo implementara, no haría falta adaptador. Ésta es la premisa del
        // patrón: la clase ajena tiene la interfaz que su autor quiso.
        $this->assertFalse(
            (new ReflectionClass(RxNavClient::class))->implementsInterface(DrugInteractionChecker::class)
        );
        $this->assertFalse(
            (new ReflectionClass(VademecumNacionalReader::class))->implementsInterface(DrugInteractionChecker::class)
        );
    }

    #[Test]
    public function el_adaptador_usa_composicion_y_no_herencia(): void
    {
        // Adaptador de objeto: envuelve al adaptee en vez de heredarlo. Es lo
        // que permite envolver clases `final` y cambiarlas en pruebas.
        $adaptador = new ReflectionClass(VademecumInteractionAdapter::class);

        $this->assertFalse($adaptador->isSubclassOf(VademecumNacionalReader::class));
        $this->assertTrue($adaptador->implementsInterface(DrugInteractionChecker::class));
        $this->assertSame(
            VademecumNacionalReader::class,
            $adaptador->getConstructor()->getParameters()[0]->getType()->getName(),
        );
    }

    #[Test]
    #[DataProvider('adaptadores')]
    public function todas_las_fuentes_cumplen_el_mismo_contrato(string $clase): void
    {
        $this->assertTrue(
            (new ReflectionClass($clase))->implementsInterface(DrugInteractionChecker::class)
        );
    }

    /** @return array<string, array{string}> */
    public static function adaptadores(): array
    {
        return [
            'vademécum' => [VademecumInteractionAdapter::class],
            'RxNav' => [RxNavInteractionAdapter::class],
        ];
    }

    #[Test]
    public function el_vademecum_encuentra_una_interaccion_grave(): void
    {
        $reporte = $this->vademecum()->check(['Losartán', 'Espironolactona']);

        $this->assertInstanceOf(InteractionReport::class, $reporte);
        $this->assertCount(1, $reporte->interactions);
        $this->assertSame(InteractionSeverity::Grave, $reporte->interactions[0]->severity);
        $this->assertTrue($reporte->hasWarnings());
        $this->assertStringContainsString('hiperpotasemia', $reporte->interactions[0]->description);
    }

    #[Test]
    public function el_vademecum_ignora_las_interacciones_con_farmacos_ajenos_a_la_formula(): void
    {
        // El CSV sabe que el losartán interactúa con litio, pero el paciente no
        // toma litio: esa fila no debe convertirse en alerta.
        $reporte = $this->vademecum()->check(['Losartán', 'Acetaminofén']);

        $this->assertSame([], $reporte->interactions);
        $this->assertFalse($reporte->hasWarnings());
    }

    #[Test]
    public function el_vademecum_no_repite_el_par_aunque_lo_encuentre_dos_veces(): void
    {
        // Se consulta cada fármaco por separado, así que el par aparece dos
        // veces; el adaptador tiene que deduplicarlo.
        $reporte = $this->vademecum()->check(['Warfarina', 'Ibuprofeno']);

        $this->assertCount(1, $reporte->interactions);
    }

    #[Test]
    public function el_vademecum_tolera_tildes_y_mayusculas(): void
    {
        $conTilde = $this->vademecum()->check(['Losartán', 'Ibuprofeno']);
        $sinTilde = $this->vademecum()->check(['losartan', 'IBUPROFENO']);

        $this->assertCount(1, $conTilde->interactions);
        $this->assertCount(1, $sinTilde->interactions);
    }

    #[Test]
    public function el_adaptador_de_rxnav_traduce_los_nombres_a_codigos_rxcui(): void
    {
        // La prueba central del patrón por el lado de la ENTRADA: el sistema
        // dice «Losartán» y al proveedor le tiene que llegar 52175.
        Http::fake([
            'rxnav.nlm.nih.gov/*' => Http::response($this->respuestaRxNav()),
        ]);

        $this->rxnav()->check(['Losartán', 'Ibuprofeno']);

        Http::assertSent(function ($request) {
            // Se comprueba el parámetro, no la URL cruda: el cliente HTTP
            // codifica el «+» al armar la cadena de consulta.
            return $request['rxcuis'] === '52175+5640';
        });
    }

    #[Test]
    public function el_adaptador_de_rxnav_traduce_la_respuesta_al_vocabulario_del_sistema(): void
    {
        // Y por el lado de la SALIDA: «high» en inglés tiene que salir como
        // Grave, y el fármaco con el nombre que escribió el profesional.
        Http::fake([
            'rxnav.nlm.nih.gov/*' => Http::response($this->respuestaRxNav()),
        ]);

        $reporte = $this->rxnav()->check(['Losartán', 'Ibuprofeno']);

        $this->assertCount(1, $reporte->interactions);
        $this->assertSame(InteractionSeverity::Grave, $reporte->interactions[0]->severity);
        $this->assertSame('Losartán', $reporte->interactions[0]->drugA);
        $this->assertSame('Ibuprofeno', $reporte->interactions[0]->drugB);
        $this->assertSame('rxnav_nlm', $reporte->interactions[0]->source);
    }

    #[Test]
    public function el_adaptador_de_rxnav_no_llama_al_servicio_si_no_reconoce_los_farmacos(): void
    {
        Http::fake();

        $reporte = $this->rxnav()->check(['Agua bendita', 'Polvo de hadas']);

        $this->assertSame([], $reporte->interactions);
        Http::assertNothingSent();
    }

    #[Test]
    public function un_fallo_del_servicio_externo_no_tumba_la_verificacion(): void
    {
        // El API se cae: la atención clínica no puede detenerse por eso. Se
        // devuelve un informe vacío y el profesional decide.
        Http::fake([
            'rxnav.nlm.nih.gov/*' => Http::response('', 503),
        ]);

        $reporte = $this->rxnav()->check(['Losartán', 'Ibuprofeno']);

        $this->assertSame([], $reporte->interactions);
        $this->assertSame('rxnav_nlm', $reporte->source);
    }

    #[Test]
    public function las_dos_fuentes_son_intercambiables_para_el_cliente(): void
    {
        // EL PUNTO DEL PATRÓN: el mismo código consume dos fuentes cuyas
        // interfaces no se parecen en nada.
        Http::fake([
            'rxnav.nlm.nih.gov/*' => Http::response($this->respuestaRxNav()),
        ]);

        foreach ([$this->vademecum(), $this->rxnav()] as $checker) {
            $reporte = $checker->check(['Losartán', 'Ibuprofeno']);

            $this->assertInstanceOf(InteractionReport::class, $reporte);
            $this->assertCount(1, $reporte->interactions);
            $this->assertNotEmpty($reporte->source);
            $this->assertSame(['Losartán', 'Ibuprofeno'], $reporte->checkedDrugs);
        }
    }

    #[Test]
    public function el_informe_ordena_de_mas_grave_a_menos(): void
    {
        $reporte = $this->vademecum()->check(['Warfarina', 'Ibuprofeno', 'Acetaminofén']);

        $gravedades = array_map(fn ($i) => $i->severity->weight(), $reporte->sorted());

        $descendente = $gravedades;
        rsort($descendente);

        $this->assertSame($descendente, $gravedades);
        $this->assertGreaterThan(1, count($gravedades));
        $this->assertSame(InteractionSeverity::Grave, $reporte->mostSevere()->severity);
    }

    #[Test]
    public function el_resolver_entrega_el_adaptador_de_la_fuente_pedida(): void
    {
        $resolver = new InteractionCheckerResolver;

        $this->assertInstanceOf(VademecumInteractionAdapter::class, $resolver->for('vademecum'));
        $this->assertInstanceOf(RxNavInteractionAdapter::class, $resolver->for('rxnav'));
    }

    #[Test]
    public function el_resolver_usa_la_fuente_local_por_defecto(): void
    {
        // Verificar una fórmula no puede depender de que haya internet.
        $this->assertInstanceOf(VademecumInteractionAdapter::class, (new InteractionCheckerResolver)->for());
    }

    #[Test]
    public function el_resolver_rechaza_una_fuente_desconocida(): void
    {
        $this->expectException(ValidationException::class);

        (new InteractionCheckerResolver)->for('el_vecino_farmaceuta');
    }

    private function vademecum(): VademecumInteractionAdapter
    {
        return new VademecumInteractionAdapter(
            new VademecumNacionalReader((string) config('interactions.vademecum.csv'))
        );
    }

    private function rxnav(): RxNavInteractionAdapter
    {
        return new RxNavInteractionAdapter(new RxNavClient);
    }

    /**
     * Respuesta con la forma real del API de la NLM.
     *
     * @return array<string, mixed>
     */
    private function respuestaRxNav(): array
    {
        return [
            'fullInteractionTypeGroup' => [[
                'sourceName' => 'DrugBank',
                'fullInteractionType' => [[
                    'interactionPair' => [[
                        'severity' => 'high',
                        'description' => 'The risk or severity of renal failure can be increased.',
                        'interactionConcept' => [
                            ['minConceptItem' => ['rxcui' => '52175', 'name' => 'losartan 50 MG Oral Tablet']],
                            ['minConceptItem' => ['rxcui' => '5640', 'name' => 'ibuprofen 400 MG Oral Tablet']],
                        ],
                    ]],
                ]],
            ]],
        ];
    }
}

<?php

namespace App\Support\Interactions\External;

use RuntimeException;

/**
 * ADAPTEE 2: lector del vademécum que entrega la institución en CSV.
 *
 * Es el contrapunto perfecto del cliente de RxNav: misma información, interfaz
 * radicalmente distinta.
 *
 * - busca **un principio activo a la vez**, no un conjunto;
 * - devuelve filas planas con encabezados en español
 *   (`principio_a`, `principio_b`, `gravedad`, `descripcion`);
 * - usa la escala `leve` / `moderada` / `grave` / `contraindicada`.
 *
 * Que dos fuentes tan dispares quepan por la misma puerta sin que el sistema se
 * entere es exactamente lo que aporta el patrón.
 */
final class VademecumNacionalReader
{
    /** @var list<array<string, string>>|null */
    private ?array $filas = null;

    public function __construct(
        private readonly string $rutaCsv,
    ) {}

    /**
     * Firma del proveedor: un fármaco, sus filas crudas.
     *
     * @return list<array<string, string>>
     */
    public function buscarPorPrincipio(string $principio): array
    {
        $aguja = $this->normalizar($principio);

        return array_values(array_filter(
            $this->cargar(),
            fn (array $fila) => $this->normalizar($fila['principio_a']) === $aguja
                || $this->normalizar($fila['principio_b']) === $aguja,
        ));
    }

    /** Principios activos presentes en el vademécum. */
    public function principiosConocidos(): array
    {
        $nombres = [];

        foreach ($this->cargar() as $fila) {
            $nombres[] = $fila['principio_a'];
            $nombres[] = $fila['principio_b'];
        }

        $unicos = array_values(array_unique($nombres));
        sort($unicos);

        return $unicos;
    }

    /**
     * Compara sin tildes ni mayúsculas: en el CSV institucional conviven
     * «Losartán» y «losartan», y el profesional escribe de cualquiera de las dos
     * formas.
     */
    public function normalizar(string $texto): string
    {
        $sinTildes = strtr(mb_strtolower(trim($texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return $sinTildes;
    }

    /**
     * @return list<array<string, string>>
     */
    private function cargar(): array
    {
        if ($this->filas !== null) {
            return $this->filas;
        }

        if (! is_readable($this->rutaCsv)) {
            throw new RuntimeException("No se pudo leer el vademécum en {$this->rutaCsv}.");
        }

        $manejador = fopen($this->rutaCsv, 'r');
        $encabezados = fgetcsv($manejador);
        $filas = [];

        while (($fila = fgetcsv($manejador)) !== false) {
            if (count($fila) === count($encabezados)) {
                $filas[] = array_combine($encabezados, $fila);
            }
        }

        fclose($manejador);

        return $this->filas = $filas;
    }
}

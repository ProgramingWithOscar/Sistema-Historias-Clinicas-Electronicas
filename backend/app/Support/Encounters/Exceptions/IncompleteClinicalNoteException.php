<?php

namespace App\Support\Encounters\Exceptions;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Se lanza cuando `build()` detecta que la nota no cumple el contenido mínimo
 * de la historia clínica (Resolución 1995 de 1999).
 *
 * Es la pieza que convierte al builder en un guardián: mientras la nota está
 * "a medias" no existe como objeto, así que ninguna capa posterior puede
 * recibir una historia clínica incompleta.
 */
final class IncompleteClinicalNoteException extends DomainException
{
    /**
     * @param  list<string>  $missing  secciones obligatorias que faltan
     */
    public function __construct(public readonly array $missing)
    {
        parent::__construct(
            'La nota clínica no cumple el contenido mínimo: '.implode(', ', $missing).'.'
        );
    }

    /**
     * Laravel invoca este método automáticamente: el error de dominio se
     * presenta como un 422 con el mismo formato que la validación de campos.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['note' => $this->missing],
        ], 422);
    }
}

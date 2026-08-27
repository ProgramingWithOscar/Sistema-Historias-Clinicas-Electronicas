<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gestor centralizado de auditoría clínica.
 *
 * Implementa el patrón Singleton (GoF): una única instancia por proceso PHP,
 * de modo que todos los accesos y modificaciones a una historia clínica se
 * registren de forma centralizada y cronológicamente consistente, como exige
 * la trazabilidad de la HCEI (Ley 2015 de 2020, Res. 1995 de 1999, ISO 27799).
 *
 * Con múltiples instancias se perdería el correlativo de sesión ($requestId) y
 * el orden garantizado ($sequence) de los eventos dentro de una misma petición.
 */
final class AuditLogger
{
    /** Única instancia viva del logger en el proceso. */
    private static ?self $instance = null;

    /** Correlativo compartido por todos los eventos de una misma petición. */
    private readonly string $requestId;

    /** Orden monotónico de los eventos; sólo tiene sentido si la instancia es única. */
    private int $sequence = 0;

    /**
     * Privado: nadie fuera de la clase puede hacer `new AuditLogger()`.
     * Ésta es la restricción que hace del patrón un Singleton real y no una
     * simple convención.
     */
    private function __construct()
    {
        $this->requestId = (string) Str::uuid();
    }

    /** Único punto de acceso a la instancia. */
    public static function getInstance(): self
    {
        return self::$instance ??= new self;
    }

    /** Bloquea la clonación: `clone $logger` crearía una segunda instancia. */
    private function __clone(): void {}

    /** Bloquea la deserialización, la otra vía para duplicar la instancia. */
    public function __wakeup(): void
    {
        throw new \LogicException('AuditLogger es un Singleton y no puede deserializarse.');
    }

    /**
     * Reinicia la instancia. Uso EXCLUSIVO de la suite de pruebas, donde varios
     * casos comparten el mismo proceso PHP y necesitan aislarse entre sí.
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function eventCount(): int
    {
        return $this->sequence;
    }

    /**
     * Registra un evento auditable.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?int $actorId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        $this->sequence++;

        $attributes = [
            'request_id' => $this->requestId,
            'sequence' => $this->sequence,
            'action' => $action,
            'actor_id' => $actorId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 500, ''),
        ];

        $entry = new AuditLog($attributes);

        try {
            $entry->save();
        } catch (Throwable $e) {
            // La auditoría nunca debe tumbar la atención clínica: si la tabla no
            // está disponible se degrada al canal de logs, pero se deja rastro.
            Log::error('No se pudo persistir el evento de auditoría', [
                'action' => $action,
                'request_id' => $this->requestId,
                'error' => $e->getMessage(),
            ]);
        }

        Log::channel(config('logging.default'))->info("audit.{$action}", $attributes);

        return $entry;
    }
}

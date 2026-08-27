<?php

namespace App\Models;

use App\Support\Audit\AuditOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'request_id',
    'sequence',
    'action',
    'outcome',
    'status_code',
    'actor_id',
    'subject_type',
    'subject_id',
    'metadata',
    'ip_address',
    'user_agent',
])]
class AuditLog extends Model
{
    /** Un registro de auditoría es inmutable: sólo se crea, nunca se actualiza. */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'outcome' => AuditOutcome::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

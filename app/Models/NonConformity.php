<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * No Conformidad a proveedor (formato F-CAL-24 Rev 00).
 *
 * Plazos (definidos con Calidad):
 *  - El proveedor tiene 28 días desde la emisión para enviar su acción correctiva.
 *    Si el ingeniero pide otra vuelta, esos 28 días NO se reinician.
 *  - Para las evidencias tiene 28 días desde la PRIMERA vez que envió su acción correctiva.
 *
 * El estado que se muestra (display_status) usa los mismos nombres que el
 * Excel de indicadores y se calcula al momento a partir de las fechas.
 */
class NonConformity extends Model
{
    use SoftDeletes;

    /** Datos del formato controlado (encabezado del PDF). Cambiar aquí si Calidad emite una nueva revisión. */
    const FORMAT_CODE     = 'F-CAL-24';
    const FORMAT_REVISION = '00';
    const FORMAT_ISSUED   = '4-nov-2025';
    const FORMAT_NAME     = 'No Conformidad a proveedor';

    const RESPONSE_DAYS = 28;
    const REMINDER_DAYS_BEFORE = 7; // recordatorio previo al vencimiento (además del día que vence)
    const EVIDENCE_DAYS = 28;
    const MAX_PHOTOS    = 6;

    // Estados del flujo (internos)
    const STATUS_AWAITING_RESPONSE = 'awaiting_response'; // esperando la Sección 2
    const STATUS_PLAN_IN_REVIEW    = 'plan_in_review';    // el proveedor envió, el ingeniero revisa
    const STATUS_PLAN_RETURNED     = 'plan_returned';     // el ingeniero pidió otra vuelta
    const STATUS_AWAITING_EVIDENCE = 'awaiting_evidence'; // plan aceptado, faltan evidencias
    const STATUS_CLOSED            = 'closed';

    // Estados que se muestran (mismos nombres que el Excel de indicadores)
    const DISPLAY_NO_RESPONSE      = 'Sin respuesta';
    const DISPLAY_MISSING_ACTION   = 'Falta acción correctiva';
    const DISPLAY_MISSING_EVIDENCE = 'Falta evidencia';
    const DISPLAY_CLOSED           = 'Finalizada';
    const DISPLAY_CLOSED_LATE      = 'Finalizada, fuera de tiempo';
    const DISPLAY_OPEN_LATE        = 'Sin cerrar, fuera de tiempo';

    const DISPLAY_STATUSES = [
        self::DISPLAY_NO_RESPONSE,
        self::DISPLAY_MISSING_ACTION,
        self::DISPLAY_MISSING_EVIDENCE,
        self::DISPLAY_CLOSED,
        self::DISPLAY_CLOSED_LATE,
        self::DISPLAY_OPEN_LATE,
    ];

    protected $fillable = [
        'folio', 'folio_year', 'folio_sequence',
        'provider_id', 'product_service_id', 'lot', 'deviation_date', 'reception_date',
        'quantity_involved', 'unit_id', 'non_conformity_type_id', 'description',
        'immediate_action', 'immediate_action_date',
        'status', 'issued_at', 'response_due_date', 'response_received_at', 'evidence_due_date',
        'closed_at', 'closed_by', 'closing_comment', 'last_reminder_key',
        'is_imported', 'created_by',
    ];

    protected $casts = [
        'deviation_date'        => 'date',
        'reception_date'        => 'date',
        'immediate_action_date' => 'date',
        'issued_at'             => 'date',
        'response_due_date'     => 'date',
        'response_received_at'  => 'date',
        'evidence_due_date'     => 'date',
        'closed_at'             => 'datetime',
        'quantity_involved'     => 'decimal:2',
        'is_imported'           => 'boolean',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function provider(): BelongsTo       { return $this->belongsTo(Provider::class); }
    public function productService(): BelongsTo { return $this->belongsTo(ProductService::class, 'product_service_id'); }
    public function unit(): BelongsTo           { return $this->belongsTo(Unit::class); }
    public function type(): BelongsTo           { return $this->belongsTo(NonConformityType::class, 'non_conformity_type_id'); }
    public function createdBy(): BelongsTo      { return $this->belongsTo(User::class, 'created_by'); }
    public function closedBy(): BelongsTo       { return $this->belongsTo(User::class, 'closed_by'); }
    public function photos(): HasMany           { return $this->hasMany(NonConformityPhoto::class)->orderBy('id'); }

    /** Vueltas de la Sección 2, de la más reciente a la más antigua */
    public function responses(): HasMany
    {
        return $this->hasMany(NonConformityResponse::class)->orderByDesc('round');
    }

    public function latestResponse(): HasOne
    {
        return $this->hasOne(NonConformityResponse::class)->latestOfMany('round');
    }

    /** Vuelta aceptada: sus acciones son las que reciben evidencia en la Sección 3 */
    public function acceptedResponse(): HasOne
    {
        return $this->hasOne(NonConformityResponse::class)->ofMany(
            ['round' => 'max'],
            fn ($q) => $q->where('review_status', NonConformityResponse::REVIEW_ACCEPTED)
        );
    }

    /**
     * Avance de la Sección 3 sobre las acciones de la vuelta aceptada.
     * Requiere acceptedResponse.actions.evidences cargado.
     */
    public function getEvidenceProgressAttribute(): ?array
    {
        if (!$this->relationLoaded('acceptedResponse') || !$this->acceptedResponse) return null;

        $statuses = $this->acceptedResponse->actions->map(fn ($a) => $a->evidence_status);

        return [
            'total'    => $statuses->count(),
            'accepted' => $statuses->filter(fn ($s) => $s === NonConformityEvidence::REVIEW_ACCEPTED)->count(),
            'pending'  => $statuses->filter(fn ($s) => $s === NonConformityEvidence::REVIEW_PENDING)->count(),
            'rejected' => $statuses->filter(fn ($s) => $s === NonConformityEvidence::REVIEW_REJECTED)->count(),
            'missing'  => $statuses->filter(fn ($s) => $s === 'missing')->count(),
        ];
    }

    /** Se puede cerrar cuando todas las acciones tienen su evidencia en "Cumple" */
    public function getCanCloseAttribute(): bool
    {
        $p = $this->evidence_progress;
        return $this->status === self::STATUS_AWAITING_EVIDENCE && $p && $p['total'] > 0 && $p['accepted'] === $p['total'];
    }

    // ── Estado que se muestra ─────────────────────────────────────────────────

    public function getDisplayStatusAttribute(): string
    {
        $today = Carbon::today();

        if ($this->status === self::STATUS_CLOSED) {
            $limit = $this->evidence_due_date ?? $this->response_due_date;
            return ($limit && $this->closed_at && $this->closed_at->startOfDay()->gt($limit))
                ? self::DISPLAY_CLOSED_LATE
                : self::DISPLAY_CLOSED;
        }

        // Nunca ha enviado su acción correctiva
        if (!$this->response_received_at) {
            return self::DISPLAY_NO_RESPONSE;
        }

        // Ya respondió pero pasó el plazo de evidencias sin cerrarse
        if ($this->evidence_due_date && $today->gt($this->evidence_due_date)) {
            return self::DISPLAY_OPEN_LATE;
        }

        // Plan regresado → falta acción correctiva; plan en revisión o aceptado → falta evidencia
        // (la acción correctiva ya se recibió y el plazo de evidencias ya corre)
        return $this->status === self::STATUS_PLAN_RETURNED
            ? self::DISPLAY_MISSING_ACTION
            : self::DISPLAY_MISSING_EVIDENCE;
    }

    /** Fecha límite vigente: la de respuesta mientras no responda, luego la de evidencias */
    public function getCurrentDueDateAttribute(): ?Carbon
    {
        if ($this->status === self::STATUS_CLOSED) return null;
        return $this->response_received_at ? $this->evidence_due_date : $this->response_due_date;
    }

    public function getIsOverdueAttribute(): bool
    {
        $due = $this->current_due_date;
        return $due !== null && Carbon::today()->gt($due);
    }

    public function getDaysLeftAttribute(): ?int
    {
        $due = $this->current_due_date;
        return $due ? (int) Carbon::today()->diffInDays($due, false) : null;
    }

    /** La Sección 1 se puede editar mientras el proveedor no haya respondido */
    public function getIsEditableAttribute(): bool
    {
        return $this->status !== self::STATUS_CLOSED && !$this->response_received_at;
    }

    /** El proveedor puede enviar (o reenviar) su Sección 2 */
    public function getCanRespondAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_AWAITING_RESPONSE, self::STATUS_PLAN_RETURNED], true);
    }

    /**
     * Etapa detallada para la pantalla (complementa al estado del Excel).
     * Requiere la relación latestResponse cargada para mostrar el número de vuelta.
     */
    public function getStageLabelAttribute(): string
    {
        $round = $this->relationLoaded('latestResponse') ? $this->latestResponse?->round : null;
        $suffix = $round ? " (vuelta {$round})" : '';

        return match ($this->status) {
            self::STATUS_AWAITING_RESPONSE => 'Esperando respuesta del proveedor',
            self::STATUS_PLAN_IN_REVIEW    => 'Plan en revisión' . $suffix,
            self::STATUS_PLAN_RETURNED     => 'Plan regresado al proveedor' . $suffix,
            self::STATUS_AWAITING_EVIDENCE => ($p = $this->evidence_progress)
                ? "Evidencias: {$p['accepted']} de {$p['total']} aceptadas"
                : 'Plan aceptado · esperando evidencias',
            self::STATUS_CLOSED            => 'Cerrada',
            default                        => $this->status,
        };
    }

    /**
     * Filtra por estado mostrado (misma lógica que getDisplayStatusAttribute, en SQL).
     */
    public function scopeDisplayStatus($query, string $display)
    {
        $today = Carbon::today()->toDateString();

        return match ($display) {
            self::DISPLAY_NO_RESPONSE => $query
                ->where('status', '!=', self::STATUS_CLOSED)
                ->whereNull('response_received_at'),

            self::DISPLAY_OPEN_LATE => $query
                ->where('status', '!=', self::STATUS_CLOSED)
                ->whereNotNull('response_received_at')
                ->whereDate('evidence_due_date', '<', $today),

            self::DISPLAY_MISSING_ACTION => $query
                ->where('status', self::STATUS_PLAN_RETURNED)
                ->whereNotNull('response_received_at')
                ->where(fn ($q) => $q->whereNull('evidence_due_date')->orWhereDate('evidence_due_date', '>=', $today)),

            self::DISPLAY_MISSING_EVIDENCE => $query
                ->whereIn('status', [self::STATUS_AWAITING_EVIDENCE, self::STATUS_PLAN_IN_REVIEW])
                ->where(fn ($q) => $q->whereNull('evidence_due_date')->orWhereDate('evidence_due_date', '>=', $today)),

            self::DISPLAY_CLOSED => $query
                ->where('status', self::STATUS_CLOSED)
                ->whereRaw('DATE(closed_at) <= COALESCE(evidence_due_date, response_due_date)'),

            self::DISPLAY_CLOSED_LATE => $query
                ->where('status', self::STATUS_CLOSED)
                ->whereRaw('DATE(closed_at) > COALESCE(evidence_due_date, response_due_date)'),

            default => $query,
        };
    }

    /**
     * Siguiente folio del año: NC{consecutivo de 2 dígitos}{año}, p. ej. NC122026.
     * Debe llamarse dentro de una transacción.
     */
    public static function nextFolio(int $year): array
    {
        // PostgreSQL no permite FOR UPDATE con funciones de agregado (MAX), así que
        // se bloquea la última fila del año. Incluye NC borradas para no reutilizar folios.
        // Si dos NC se crean al mismo tiempo, el índice único (folio_year, folio_sequence)
        // evita el duplicado y el controller reintenta.
        $last = DB::table('non_conformities')
            ->where('folio_year', $year)
            ->orderByDesc('folio_sequence')
            ->lockForUpdate()
            ->value('folio_sequence');

        // Consecutivo inicial configurable por año, para continuar la numeración
        // manual que Calidad ya usaba (p. ej. NC_FOLIO_START_2026=24 → NC242026)
        $start    = (int) (config("services.non_conformities.folio_start.{$year}") ?: 1);
        $sequence = max(((int) $last) + 1, $start);

        return [
            'folio'          => 'NC' . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT) . $year,
            'folio_year'     => $year,
            'folio_sequence' => $sequence,
        ];
    }
}
<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $fillable = [
        'user_id',
        'open_for_user_id',
        'status',
        'opening_amount',
        'opened_at',
        'closed_by',
        'closed_at',
        'expected_cash',
        'counted_cash',
        'difference',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opening_amount' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::Abierta;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', CashSessionStatus::Abierta->value);
    }

    /**
     * Resumen del turno calculado desde las ventas:
     * cantidad y totales por método de pago (sin contar anuladas) y efectivo esperado.
     *
     * @return array{count: int, total: float, by_method: array<string, float>, voided_count: int, voided_total: float, expected_cash: float}
     */
    public function summary(): array
    {
        $rows = $this->sales()
            ->selectRaw('status, payment_method, COUNT(*) as n, SUM(total) as total')
            ->groupBy('status', 'payment_method')
            ->get();

        $byMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $byMethod[$method->value] = 0.0;
        }

        $count = 0;
        $total = 0.0;
        $voidedCount = 0;
        $voidedTotal = 0.0;

        foreach ($rows as $row) {
            $status = $row->status instanceof SaleStatus ? $row->status : SaleStatus::from($row->status);
            $method = $row->payment_method instanceof PaymentMethod ? $row->payment_method : PaymentMethod::from($row->payment_method);

            if ($status === SaleStatus::Anulada) {
                $voidedCount += (int) $row->n;
                $voidedTotal += (float) $row->total;

                continue;
            }

            $count += (int) $row->n;
            $total += (float) $row->total;
            $byMethod[$method->value] += (float) $row->total;
        }

        return [
            'count' => $count,
            'total' => round($total, 2),
            'by_method' => array_map(fn ($v) => round($v, 2), $byMethod),
            'voided_count' => $voidedCount,
            'voided_total' => round($voidedTotal, 2),
            'expected_cash' => round((float) $this->opening_amount + $byMethod[PaymentMethod::Efectivo->value], 2),
        ];
    }
}

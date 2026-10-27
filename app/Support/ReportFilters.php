<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Filtros comunes de los reportes. Cada reporte usa solo los que le corresponden.
 */
class ReportFilters
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public ?int $userId = null,
        public ?int $categoryId = null,
        public ?PaymentMethod $method = null,
        public string $group = 'dia',
        public string $order = 'cantidad',
        public int $top = 20,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'user' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'group' => ['nullable', Rule::in(['dia', 'semana', 'mes'])],
            'order' => ['nullable', Rule::in(['cantidad', 'ingresos', 'utilidad'])],
            'top' => ['nullable', 'integer', Rule::in([0, 10, 20, 50])],
        ];
    }

    /**
     * Aplica la validación y devuelve los filtros con sus valores por defecto
     * (del 1.º del mes actual a hoy).
     */
    public static function fromRequest(Request $request): self
    {
        $data = $request->validate(self::rules(), [
            'to.after_or_equal' => 'La fecha hasta no puede ser anterior a la fecha desde.',
        ]);

        return new self(
            from: ! empty($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth()->startOfDay(),
            to: ! empty($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay(),
            userId: ! empty($data['user']) ? (int) $data['user'] : null,
            categoryId: ! empty($data['category']) ? (int) $data['category'] : null,
            method: ! empty($data['method']) ? PaymentMethod::from($data['method']) : null,
            group: $data['group'] ?? 'dia',
            order: $data['order'] ?? 'cantidad',
            top: isset($data['top']) ? (int) $data['top'] : 20,
        );
    }

    /**
     * Descripción legible de los filtros usados, para el encabezado de pantalla y PDF.
     *
     * @param  list<string>  $used  filtros que aplican a este reporte
     */
    public function describe(array $used): string
    {
        $parts = [];

        if (in_array('date', $used, true)) {
            $parts[] = 'Del '.$this->from->format('d/m/Y').' al '.$this->to->format('d/m/Y');
        }

        if (in_array('user', $used, true) && $this->userId) {
            $parts[] = 'Usuario: '.(User::find($this->userId)?->name ?? '—');
        }

        if (in_array('category', $used, true) && $this->categoryId) {
            $parts[] = 'Categoría: '.(Category::find($this->categoryId)?->name ?? '—');
        }

        if (in_array('method', $used, true) && $this->method) {
            $parts[] = 'Pago: '.$this->method->label();
        }

        return implode(' · ', $parts);
    }

    /** Parámetros para volver a pedir el mismo reporte (enlace al PDF). */
    public function toQuery(): array
    {
        return array_filter([
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'user' => $this->userId,
            'category' => $this->categoryId,
            'method' => $this->method?->value,
            'group' => $this->group,
            'order' => $this->order,
            'top' => $this->top,
        ], fn ($v) => $v !== null);
    }
}

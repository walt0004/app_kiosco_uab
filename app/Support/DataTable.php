<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Respuesta "server-side" para DataTables (búsqueda, orden y paginación en la base de datos).
 *
 * Uso:
 *   return DataTable::of($query, $request)
 *       ->searchable(['products.name', 'products.code'])
 *       ->orderable([0 => 'products.name', 3 => 'products.sale_price'])
 *       ->defaultOrder('products.name')
 *       ->response(fn ($product) => ['name' => e($product->name), ...]);
 *
 * Las columnas de búsqueda y orden se declaran en listas blancas: lo que envía
 * el navegador nunca se usa directamente como nombre de columna.
 */
class DataTable
{
    /** @var list<string> */
    private array $searchable = [];

    /** @var array<int, string> */
    private array $orderable = [];

    private ?string $defaultOrder = null;

    private string $defaultDirection = 'asc';

    private ?Closure $extra = null;

    private function __construct(
        private readonly EloquentBuilder|QueryBuilder $query,
        private readonly Request $request,
    ) {}

    public static function of(EloquentBuilder|QueryBuilder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /** @param  list<string>  $columns */
    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    /** @param  array<int, string>  $columns  índice de columna de la tabla => columna de la base de datos */
    public function orderable(array $columns): self
    {
        $this->orderable = $columns;

        return $this;
    }

    public function defaultOrder(string $column, string $direction = 'asc'): self
    {
        $this->defaultOrder = $column;
        $this->defaultDirection = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    /**
     * Datos adicionales para la respuesta (por ejemplo totales del filtro).
     * Recibe la consulta ya filtrada y con la búsqueda aplicada.
     *
     * @param  Closure(EloquentBuilder|QueryBuilder): array<string, mixed>  $callback
     */
    public function extra(Closure $callback): self
    {
        $this->extra = $callback;

        return $this;
    }

    /**
     * @param  Closure(mixed): array<string, mixed>  $mapRow  convierte cada fila en el arreglo que usa la tabla
     */
    public function response(Closure $mapRow): JsonResponse
    {
        $query = clone $this->query;

        $recordsTotal = (clone $query)->count();

        $term = trim((string) $this->request->input('search.value', ''));

        if ($term !== '' && $this->searchable !== []) {
            $like = '%'.addcslashes($term, '\\%_').'%';

            $query->where(function ($q) use ($like) {
                foreach ($this->searchable as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }

        $recordsFiltered = (clone $query)->count();

        $extra = $this->extra ? ($this->extra)(clone $query) : [];

        $this->applyOrder($query);

        $length = (int) $this->request->input('length', 25);
        $length = ($length < 1 || $length > 100) ? 25 : $length;
        $start = max(0, (int) $this->request->input('start', 0));

        $rows = $query->offset($start)->limit($length)->get();

        return response()->json(array_merge([
            'draw' => (int) $this->request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map($mapRow)->values()->all(),
        ], $extra));
    }

    private function applyOrder(EloquentBuilder|QueryBuilder $query): void
    {
        $ordered = false;

        foreach ((array) $this->request->input('order', []) as $order) {
            $index = (int) ($order['column'] ?? -1);

            if (! isset($this->orderable[$index])) {
                continue;
            }

            $direction = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($this->orderable[$index], $direction);
            $ordered = true;
        }

        if ($this->defaultOrder !== null) {
            // Desempate estable para que la paginación no repita ni salte filas.
            $query->orderBy($this->defaultOrder, $ordered ? 'asc' : $this->defaultDirection);
        }
    }
}

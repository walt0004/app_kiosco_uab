<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\VoidSaleRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashService;
use App\Services\SaleService;
use App\Support\DataTable;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly CashService $cash,
    ) {}

    // ---- Listado --------------------------------------------------------

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('sales.index', [
            'today' => today()->toDateString(),
            'sellers' => $user->isAdmin() ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'user' => ['nullable', 'integer'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'status' => ['nullable', Rule::in(['completada', 'anulada'])],
            'session' => ['nullable', 'integer'],
        ]);

        $query = Sale::query()
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->select('sales.*', 'users.name as seller_name')
            // El vendedor solo ve sus propias ventas.
            ->when(! $user->isAdmin(), fn ($q) => $q->where('sales.user_id', $user->id))
            ->when($user->isAdmin() && ! empty($filters['user']), fn ($q) => $q->where('sales.user_id', (int) $filters['user']))
            ->when(! empty($filters['from']), fn ($q) => $q->where('sales.created_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(! empty($filters['to']), fn ($q) => $q->where('sales.created_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->when(! empty($filters['method']), fn ($q) => $q->where('sales.payment_method', $filters['method']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('sales.status', $filters['status']))
            ->when(! empty($filters['session']), fn ($q) => $q->where('sales.cash_session_id', (int) $filters['session']));

        return DataTable::of($query, $request)
            ->searchable(['users.name', 'sales.id'])
            ->orderable([
                0 => 'sales.id',
                1 => 'sales.created_at',
                2 => 'users.name',
                3 => 'sales.payment_method',
                4 => 'sales.total',
                5 => 'sales.status',
            ])
            ->defaultOrder('sales.id', 'desc')
            ->extra(function ($q) {
                $completed = (clone $q)->where('sales.status', SaleStatus::Completada->value);

                return ['summary' => [
                    'total' => Format::money((clone $completed)->sum('sales.total')),
                    'count' => (string) (clone $completed)->count(),
                ]];
            })
            ->response(fn (Sale $sale) => [
                'number' => '<a href="'.e(route('sales.show', $sale)).'" class="font-medium text-brand-700 hover:underline">'.e($sale->number()).'</a>',
                'created_at' => $sale->created_at->format('d/m/Y H:i'),
                'seller' => e($sale->seller_name),
                'method' => e($sale->payment_method->label()),
                'total' => '<span class="tabular-nums '.($sale->isVoided() ? 'text-slate-400 line-through' : 'font-medium').'">'.e(Format::money($sale->total)).'</span>',
                'status' => $sale->isVoided()
                    ? '<span class="badge bg-red-100 text-red-800">Anulada</span>'
                    : '<span class="badge bg-emerald-100 text-emerald-800">Completada</span>',
                'actions' => '<a href="'.e(route('sales.show', $sale)).'" class="text-sm font-medium text-brand-700 hover:underline">Ver</a>',
            ]);
    }

    // ---- Nueva venta ----------------------------------------------------

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->cash->currentFor($request->user()) === null) {
            return redirect()->route('cash.index')->with('info', 'Abre tu caja para empezar a vender.');
        }

        return view('sales.create', ['methods' => PaymentMethod::cases()]);
    }

    public function search(Request $request): JsonResponse
    {
        $products = Product::query()
            ->active()
            ->search($request->query('q'))
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'unit_short' => $p->unit->short(),
                'decimals' => $p->unit->allowsDecimals(),
                'price' => (float) $p->sale_price,
                'stock' => (float) $p->stock,
            ]);

        return response()->json($products);
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->sales->create(
                $request->user(),
                $request->validated('items'),
                PaymentMethod::from($request->validated('payment_method')),
                $request->validated('token'),
            );
        } catch (BusinessRuleException|InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $sale->id,
            'number' => $sale->number(),
            'redirect' => route('sales.show', $sale),
        ], 201);
    }

    // ---- Comprobante y anulación ----------------------------------------

    public function show(Request $request, Sale $sale): View
    {
        $this->authorizeSale($request, $sale);

        $sale->load(['items', 'user:id,name', 'voider:id,name', 'cashSession']);

        return view('sales.show', [
            'sale' => $sale,
            'canVoid' => $request->user()->isAdmin() && ! $sale->isVoided() && $sale->cashSession->isOpen(),
        ]);
    }

    public function void(VoidSaleRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->sales->void($sale, $request->user(), $request->validated('reason'));
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success', 'Venta anulada y stock devuelto.');
    }

    private function authorizeSale(Request $request, Sale $sale): void
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $sale->user_id === $user->id, 403, 'Esta venta fue registrada por otro usuario.');
    }
}

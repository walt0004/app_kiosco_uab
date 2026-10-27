<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\Product;
use App\Services\CashService;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly CashService $cash,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $data = [
            'activeProducts' => Product::active()->count(),
            'lowStockCount' => Product::lowStock()->where('stock', '>', 0)->count(),
            'outOfStockCount' => Product::active()->where('stock', '<=', 0)->count(),
            'lowStockProducts' => Product::lowStock()->orderBy('stock')->orderBy('name')->limit(10)->get(),
            'cashSession' => $this->cash->currentFor($user),
        ];

        if ($user->isAdmin()) {
            return view('dashboard', $data + $this->adminData());
        }

        $today = new ReportFilters(now()->startOfDay(), now()->endOfDay(), userId: $user->id);

        return view('dashboard', $data + [
            'mine' => $this->reports->totals($today),
        ]);
    }

    /** @return array<string, mixed> */
    private function adminData(): array
    {
        $today = new ReportFilters(now()->startOfDay(), now()->endOfDay());
        $week = new ReportFilters(now()->subDays(6)->startOfDay(), now()->endOfDay());
        $month = new ReportFilters(now()->startOfMonth(), now()->endOfDay());

        $daily = $this->reports->dailyTotals($week);
        $byMethod = $this->reports->totalsByMethod($month);
        $top = $this->reports->topByRevenue($month, 5);

        $methodLabels = [];
        $methodValues = [];
        foreach (PaymentMethod::cases() as $method) {
            if (($byMethod[$method->value] ?? 0) > 0) {
                $methodLabels[] = $method->label();
                $methodValues[] = $byMethod[$method->value];
            }
        }

        return [
            'isAdmin' => true,
            'todayTotals' => $this->reports->totals($today),
            'monthTotals' => $this->reports->totals($month),
            'openSessions' => CashSession::open()->with('user:id,name')->orderBy('opened_at')->get(),
            'dailyChart' => [
                'type' => 'bar',
                'labels' => array_map(fn ($d) => date('d/m', strtotime($d)), array_keys($daily)),
                'datasets' => [['label' => 'Ventas', 'data' => array_values($daily)]],
                'money' => true,
            ],
            'methodChart' => $methodValues === [] ? null : [
                'type' => 'doughnut',
                'labels' => $methodLabels,
                'datasets' => [['label' => 'Cobros', 'data' => $methodValues]],
                'money' => true,
            ],
            'topChart' => $top === [] ? null : [
                'type' => 'bar',
                'horizontal' => true,
                'labels' => array_map(fn ($r) => $r->product, $top),
                'datasets' => [['label' => 'Ingresos', 'data' => array_map(fn ($r) => round((float) $r->revenue, 2), $top)]],
                'money' => true,
            ],
        ];
    }
}

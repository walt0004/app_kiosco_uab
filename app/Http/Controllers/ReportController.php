<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\User;
use App\Services\ReportService;
use App\Support\PdfExporter;
use App\Support\ReportFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(): View
    {
        return view('reports.index', ['definitions' => ReportService::definitions()]);
    }

    public function show(Request $request, string $report): View
    {
        $filters = ReportFilters::fromRequest($request);
        $definition = ReportService::definitions()[$report];

        return view('reports.show', [
            'report' => $this->reports->build($report, $filters),
            'filters' => $filters,
            'used' => $definition['filters'],
            'users' => User::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'methods' => PaymentMethod::cases(),
            'ranges' => [
                'Hoy' => [today()->toDateString(), today()->toDateString()],
                'Esta semana' => [now()->startOfWeek()->toDateString(), today()->toDateString()],
                'Este mes' => [now()->startOfMonth()->toDateString(), today()->toDateString()],
                'Mes pasado' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            ],
            'pdfAvailable' => PdfExporter::isAvailable(),
        ]);
    }

    public function pdf(Request $request, string $report): Response|RedirectResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $data = $this->reports->build($report, $filters);

        return PdfExporter::download('reports.pdf', [
            'report' => $data,
            'generatedBy' => $request->user()->name,
        ], 'reporte-'.$report.'-'.now()->format('Ymd-His').'.pdf', $data['orientation']);
    }
}

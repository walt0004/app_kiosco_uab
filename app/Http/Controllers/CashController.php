<?php

namespace App\Http\Controllers;

use App\Enums\CashSessionStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\CloseCashRequest;
use App\Http\Requests\OpenCashRequest;
use App\Models\CashSession;
use App\Services\CashService;
use App\Support\DataTable;
use App\Support\Format;
use App\Support\PdfExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CashController extends Controller
{
    public function __construct(private readonly CashService $cash) {}

    public function index(Request $request): View
    {
        $session = $this->cash->currentFor($request->user());

        return view('cash.index', [
            'session' => $session,
            'summary' => $session?->summary(),
        ]);
    }

    public function open(OpenCashRequest $request): RedirectResponse
    {
        try {
            $this->cash->open($request->user(), (float) $request->validated('opening_amount'));
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cash.index')->with('success', 'Caja abierta. Ya puedes registrar ventas.');
    }

    public function close(CloseCashRequest $request, CashSession $cashSession): RedirectResponse
    {
        $this->authorizeSession($request, $cashSession);

        try {
            $this->cash->close(
                $cashSession,
                $request->user(),
                (float) $request->validated('counted_cash'),
                $request->validated('notes'),
            );
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('cash.show', $cashSession)->with('success', 'Caja cerrada correctamente.');
    }

    public function show(Request $request, CashSession $cashSession): View
    {
        $this->authorizeSession($request, $cashSession);

        $cashSession->load(['user:id,name', 'closer:id,name']);

        return view('cash.show', [
            'session' => $cashSession,
            'summary' => $cashSession->summary(),
        ]);
    }

    public function history(): View
    {
        return view('cash.history');
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CashSession::query()
            ->join('users', 'users.id', '=', 'cash_sessions.user_id')
            ->select('cash_sessions.*', 'users.name as user_name')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('cash_sessions.user_id', $user->id));

        return DataTable::of($query, $request)
            ->searchable(['users.name', 'cash_sessions.id'])
            ->orderable([
                0 => 'cash_sessions.id',
                1 => 'users.name',
                2 => 'cash_sessions.opened_at',
                3 => 'cash_sessions.closed_at',
                4 => 'cash_sessions.opening_amount',
                5 => 'cash_sessions.expected_cash',
                6 => 'cash_sessions.counted_cash',
                7 => 'cash_sessions.difference',
                8 => 'cash_sessions.status',
            ])
            ->defaultOrder('cash_sessions.id', 'desc')
            ->response(function (CashSession $s) {
                $difference = $s->difference;

                return [
                    'id' => '#'.$s->id,
                    'user' => e($s->user_name),
                    'opened_at' => $s->opened_at->format('d/m/Y H:i'),
                    'closed_at' => $s->closed_at?->format('d/m/Y H:i') ?? '—',
                    'opening_amount' => Format::money($s->opening_amount),
                    'expected_cash' => $s->expected_cash !== null ? Format::money($s->expected_cash) : '—',
                    'counted_cash' => $s->counted_cash !== null ? Format::money($s->counted_cash) : '—',
                    'difference' => $difference === null ? '—' : view('cash._difference', ['difference' => (float) $difference])->render(),
                    'status' => $s->status === CashSessionStatus::Abierta
                        ? '<span class="badge bg-emerald-100 text-emerald-800">Abierta</span>'
                        : '<span class="badge bg-slate-200 text-slate-700">Cerrada</span>',
                    'actions' => '<a href="'.e(route('cash.show', $s)).'" class="text-sm font-medium text-brand-700 hover:underline">Ver</a>',
                ];
            });
    }

    public function pdf(Request $request, CashSession $cashSession): Response|RedirectResponse
    {
        $this->authorizeSession($request, $cashSession);

        $cashSession->load(['user:id,name', 'closer:id,name']);

        return PdfExporter::download('cash.pdf', [
            'session' => $cashSession,
            'summary' => $cashSession->summary(),
            'generatedBy' => $request->user()->name,
        ], 'cierre-de-caja-'.$cashSession->id.'.pdf');
    }

    private function authorizeSession(Request $request, CashSession $session): void
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $session->user_id === $user->id, 403, 'Este turno de caja pertenece a otro usuario.');
    }
}

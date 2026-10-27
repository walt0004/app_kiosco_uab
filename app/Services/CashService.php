<?php

namespace App\Services;

use App\Enums\CashSessionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CashSession;
use App\Models\User;
use App\Support\Format;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CashService
{
    public function currentFor(User $user): ?CashSession
    {
        return CashSession::open()->where('user_id', $user->getKey())->first();
    }

    /**
     * Abre el turno de caja de un usuario. Solo puede tener un turno abierto.
     *
     * @throws BusinessRuleException
     */
    public function open(User $user, float $openingAmount): CashSession
    {
        if ($openingAmount < 0) {
            throw new BusinessRuleException('El monto inicial no puede ser negativo.');
        }

        try {
            return DB::transaction(function () use ($user, $openingAmount) {
                if ($this->currentFor($user) !== null) {
                    throw new BusinessRuleException('Ya tienes una caja abierta. Ciérrala antes de abrir otra.');
                }

                return CashSession::create([
                    'user_id' => $user->getKey(),
                    'open_for_user_id' => $user->getKey(),
                    'status' => CashSessionStatus::Abierta,
                    'opening_amount' => round($openingAmount, 2),
                    'opened_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Dos aperturas simultáneas: la restricción única de la base de datos gana.
            throw new BusinessRuleException('Ya tienes una caja abierta. Ciérrala antes de abrir otra.');
        }
    }

    /**
     * Cierra un turno: calcula el efectivo esperado, lo compara con lo contado
     * y guarda la diferencia. Si no cuadra, las notas son obligatorias.
     *
     * @throws BusinessRuleException
     */
    public function close(CashSession $session, User $closer, float $countedCash, ?string $notes = null): CashSession
    {
        if ($countedCash < 0) {
            throw new BusinessRuleException('El efectivo contado no puede ser negativo.');
        }

        return DB::transaction(function () use ($session, $closer, $countedCash, $notes) {
            /** @var CashSession $locked */
            $locked = CashSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new BusinessRuleException('Este turno de caja ya fue cerrado.');
            }

            $expected = $locked->summary()['expected_cash'];
            $counted = round($countedCash, 2);
            $difference = round($counted - $expected, 2);
            $notes = $notes !== null ? trim($notes) : null;

            if ($difference !== 0.0 && ($notes === null || $notes === '')) {
                $kind = $difference > 0 ? 'sobrante' : 'faltante';

                throw new BusinessRuleException(
                    'Hay un '.$kind.' de '.Format::money(abs($difference)).'. Explica el motivo en las notas para poder cerrar.'
                );
            }

            $locked->update([
                'status' => CashSessionStatus::Cerrada,
                'open_for_user_id' => null,
                'closed_by' => $closer->getKey(),
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'difference' => $difference,
                'notes' => $notes ?: null,
            ]);

            $session->refresh();

            return $locked;
        });
    }
}

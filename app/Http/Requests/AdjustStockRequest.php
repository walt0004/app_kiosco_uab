<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\Format;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => is_string($this->reason) ? trim($this->reason) : $this->reason,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(['entrada', 'salida', 'correccion'])],
            'quantity' => ['required', 'numeric', 'min:0', 'max:9999999', 'decimal:0,3'],
            'reason' => [
                Rule::requiredIf(in_array($this->input('operation'), ['salida', 'correccion'], true)),
                'nullable', 'string', 'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operation.in' => 'Selecciona una operación válida.',
            'quantity.decimal' => 'La cantidad admite como máximo 3 decimales.',
            'reason.required' => 'Indica el motivo del ajuste (merma, pérdida, conteo físico, etc.).',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('quantity')) {
                    return;
                }

                /** @var Product $product */
                $product = $this->route('product');
                $operation = $this->input('operation');
                $quantity = (float) $this->input('quantity');

                if (in_array($operation, ['entrada', 'salida'], true) && $quantity <= 0) {
                    $validator->errors()->add('quantity', 'La cantidad debe ser mayor a cero.');

                    return;
                }

                if (! $product->unit->allowsDecimals() && floor($quantity) !== $quantity) {
                    $validator->errors()->add(
                        'quantity',
                        'Este producto se maneja por '.mb_strtolower($product->unit->label()).'; la cantidad debe ser un número entero.'
                    );

                    return;
                }

                if ($operation === 'salida' && $quantity > (float) $product->stock) {
                    $validator->errors()->add(
                        'quantity',
                        'No puedes sacar más de lo que hay en stock ('.Format::qty($product->stock).').'
                    );
                }
            },
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * El precio nunca viene del navegador: solo producto y cantidad.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:99999', 'decimal:0,3'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'token' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos un producto a la venta.',
            'items.min' => 'Agrega al menos un producto a la venta.',
            'items.max' => 'Una venta no puede tener más de 60 productos distintos.',
            'items.*.product_id.exists' => 'Uno de los productos ya no existe.',
            'items.*.quantity.min' => 'Las cantidades deben ser mayores a cero.',
            'items.*.quantity.decimal' => 'Las cantidades admiten como máximo 3 decimales.',
            'payment_method.required' => 'Elige el método de pago.',
            'payment_method.enum' => 'Elige un método de pago válido.',
        ];
    }
}

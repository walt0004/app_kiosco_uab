<?php

namespace App\Http\Requests;

use App\Enums\ProductUnit;
use App\Models\Category;
use App\Models\Product;
use App\Support\Format;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea o edita un producto. El stock solo se acepta al crear (stock inicial);
 * después cambia únicamente con ajustes de stock (queda registrado).
 */
class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim(preg_replace('/\s+/', ' ', $this->name)) : $this->name,
            'code' => is_string($this->code) ? trim($this->code) : $this->code,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        $rules = [
            'name' => ['required', 'string', 'max:120', Rule::unique('products', 'name')->ignore($product)],
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_.]+$/', Rule::unique('products', 'code')->ignore($product)],
            'category_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($product) {
                $category = Category::find($value);

                if ($category === null) {
                    $fail('Selecciona una categoría válida.');
                } elseif (! $category->is_active && (int) $product?->category_id !== (int) $value) {
                    $fail('Esa categoría está desactivada. Elige otra o actívala primero.');
                }
            }],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:99999.99', 'decimal:0,2'],
            'sale_price' => ['required', 'numeric', 'min:0.01', 'max:99999.99', 'decimal:0,2'],
            'min_stock' => ['required', 'numeric', 'min:0', 'max:9999999', 'decimal:0,3'],
            'is_active' => ['boolean'],
        ];

        if ($product === null) {
            $rules['stock'] = ['required', 'numeric', 'min:0', 'max:9999999', 'decimal:0,3'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un producto con ese nombre.',
            'category_id.required' => 'Selecciona una categoría.',
            'category_id.integer' => 'Selecciona una categoría válida.',
            'code.unique' => 'Ya existe un producto con ese código.',
            'code.regex' => 'El código solo puede tener letras, números, guiones, puntos y guion bajo.',
            'purchase_price.decimal' => 'El precio de compra admite como máximo 2 decimales.',
            'sale_price.decimal' => 'El precio de venta admite como máximo 2 decimales.',
            'sale_price.min' => 'El precio de venta debe ser mayor a cero.',
            'stock.decimal' => 'El stock admite como máximo 3 decimales.',
            'min_stock.decimal' => 'El stock mínimo admite como máximo 3 decimales.',
        ];
    }

    /**
     * Las unidades que no son de peso/volumen solo admiten cantidades enteras.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $unit = ProductUnit::tryFrom((string) $this->input('unit'));

                if ($unit === null || $unit->allowsDecimals()) {
                    return;
                }

                /** @var Product|null $product */
                $product = $this->route('product');

                if ($product !== null && floor((float) $product->stock) !== (float) $product->stock) {
                    $validator->errors()->add(
                        'unit',
                        'No puedes cambiar a esta unidad porque el stock actual tiene decimales ('.Format::qty($product->stock).'). Corrige primero el stock.'
                    );
                }

                foreach (['stock', 'min_stock'] as $field) {
                    $value = $this->input($field);

                    if (is_numeric($value) && floor((float) $value) !== (float) $value && ! $validator->errors()->has($field)) {
                        $validator->errors()->add(
                            $field,
                            'Para productos por '.mb_strtolower($unit->label()).' la cantidad debe ser un número entero.'
                        );
                    }
                }
            },
        ];
    }
}

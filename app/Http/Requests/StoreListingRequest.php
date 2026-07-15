<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Rules\SellableMsisdn;
use App\Services\Search\NumberPatternQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Подача объявления. Поля — п.10 исходного ТЗ плюс те, без которых
 * объявление бесполезно покупателю: оператор, тип линии, permanencia
 * (пробелы №9–11 блюпринта).
 */
class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isBlocked();
    }

    /**
     * Номер нормализуется ДО валидации: продавец введёт его как угодно —
     * '+34 612 34 56 78', '612-34-56-78'. Хранить и сравнивать мы обязаны
     * одну форму, иначе антидубль по active_msisdn перестанет ловить дубли.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('msisdn')) {
            return;
        }

        try {
            $this->merge(['msisdn' => NumberPatternQuery::normalize((string) $this->input('msisdn'))]);
        } catch (InvalidArgumentException) {
            // Оставляем как есть — SellableMsisdn объяснит, что не так.
        }
    }

    public function rules(): array
    {
        $priceMin = (int) Setting::get('listing.price_min', 1);
        $priceMax = (int) Setting::get('listing.price_max', 50000);

        return [
            // --- Товар ---
            'msisdn' => ['required', 'string', new SellableMsisdn],

            // Цена обязательна, если не «договорная». Без этого продавцы
            // вобьют 1 или 999999, и сортировка по цене умрёт (пробел №13).
            'is_negotiable' => ['boolean'],
            'price' => [
                Rule::requiredIf(fn () => ! $this->boolean('is_negotiable')),
                'nullable', 'numeric', "min:{$priceMin}", "max:{$priceMax}",
            ],

            // --- Характеристики линии ---
            'operator_id'   => ['required', 'exists:operators,id'],
            'line_type'     => ['required', Rule::in(['prepago', 'contrato'])],
            'has_permanency' => ['boolean'],
            'permanency_until' => [
                Rule::requiredIf(fn () => $this->boolean('has_permanency')),
                'nullable', 'date', 'after:today',
            ],

            // new  — номер никогда не активировался
            // used — был в обиходе
            'condition' => ['required', Rule::in(['new', 'used'])],

            // --- География ---
            'province_id' => ['required', 'exists:provinces,id'],
            'city'        => ['nullable', 'string', 'max:120'],

            // --- Описание ---
            'description' => ['nullable', 'string', 'max:2000'],

            // --- Контакты продавца ---
            'contact_name'  => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email:rfc', 'max:190'],
            'contact_whatsapp' => ['boolean'],

            // --- Тип продавца (п.10.8 ТЗ) ---
            'seller_type' => ['required', Rule::in(['private', 'shop'])],
        ];
    }

    public function attributes(): array
    {
        return __('listing.attributes');
    }

    public function messages(): array
    {
        return __('listing.messages');
    }
}

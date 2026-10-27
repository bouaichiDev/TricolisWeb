<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCustomerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = CustomerOrderRules::rules();
        // Une commande nouvelle ne se demande pas pour hier.
        $rules['services.*.requestedDate'][] = 'after_or_equal:today';

        return $rules;
    }

    public function after(): array
    {
        return [static function (Validator $validator): void {
            foreach (CustomerOrderRules::windowErrors($validator->getData()) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }
}

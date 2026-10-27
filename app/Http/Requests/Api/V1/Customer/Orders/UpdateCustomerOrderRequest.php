<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * La commande entière, plus `updatedAt` : la version lue par le portail,
 * que le serveur compare avant d'écrire.
 */
class UpdateCustomerOrderRequest extends FormRequest
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
        return [...CustomerOrderRules::rules(), 'updatedAt' => ['required', 'date']];
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

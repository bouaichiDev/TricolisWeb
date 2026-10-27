<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Calendar;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Période et filtres du calendrier (§6).
 *
 * `from` et `to` sont obligatoires : le calendrier charge la période visible,
 * jamais toutes les commandes. `to` est exclu, comme la fin d'une plage
 * FullCalendar. La période est bornée (`calendar_max_days`) pour qu'aucune
 * requête ne devienne un export déguisé.
 */
class CalendarOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after:from'],
            'status' => ['sometimes', 'array', 'max:20'],
            'status.*' => ['string', 'max:32'],
            'service' => ['sometimes', 'array', 'max:50'],
            'service.*' => ['string', 'ulid'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $max = Config::integer('customer_portal.tracking.calendar_max_days', 100);
                $days = Carbon::parse($this->input('from'))->diffInDays(Carbon::parse($this->input('to')));

                if ($days > $max) {
                    $validator->errors()->add('to', "La période demandée ne peut pas dépasser {$max} jours.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required' => 'Le début de la période est obligatoire.',
            'to.required' => 'La fin de la période est obligatoire.',
            'to.after' => 'La fin de la période doit suivre son début.',
        ];
    }
}

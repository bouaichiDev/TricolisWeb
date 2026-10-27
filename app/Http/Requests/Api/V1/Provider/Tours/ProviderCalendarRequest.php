<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider\Tours;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Fenêtre du calendrier : bornée, pour qu'une requête ne charge pas des années
 * de tournées. 62 jours couvrent la vue mois de FullCalendar et ses débords.
 */
class ProviderCalendarRequest extends FormRequest
{
    public const int MAX_DAYS = 62;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $days = CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to')));

            if ($days > self::MAX_DAYS) {
                $validator->errors()->add('to', sprintf('La période ne peut pas dépasser %d jours.', self::MAX_DAYS));
            }
        }];
    }
}

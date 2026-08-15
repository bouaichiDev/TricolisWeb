<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Exports;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Relance d'un export.
 *
 * Le statut est fourni : le diagramme n'en énumère aucun, et le §26 interdit
 * d'en inventer.
 */
class RetryExportJobRequest extends FormRequest
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
        return ['status' => ['required', 'string', 'max:32']];
    }
}

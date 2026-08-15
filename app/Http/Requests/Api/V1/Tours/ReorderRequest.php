<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Réorganisation d'une collection séquencée.
 *
 * Les trois routes `reorder` de la phase — arrêts, services d'un arrêt,
 * périodes — attendent exactement la même entrée : la liste complète des
 * identifiants dans leur ordre cible. Une Request par route n'apporterait que
 * de la duplication.
 *
 * L'appartenance des identifiants au parent est vérifiée par
 * `SequenceReorderer`, qui compare la liste soumise à l'ensemble réel des
 * enfants : une liste partielle laisserait des lignes sans numérotation.
 */
class ReorderRequest extends FormRequest
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
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function orderedIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->validated('ids');

        return $ids;
    }
}

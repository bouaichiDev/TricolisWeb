<?php

declare(strict_types=1);

namespace App\Shared\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ListRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
            'direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
            'status' => ['sometimes', 'nullable', 'string', 'max:64'],
            'compact' => ['sometimes', 'boolean'],
            'createdFrom' => ['sometimes', 'date'],
            'createdTo' => ['sometimes', 'date', 'after_or_equal:createdFrom'],
        ];
    }

    public function getSort(string $default, array $allowed): string
    {
        $sort = $this->validated('sort') ?? $default;
        if (! in_array($sort, $allowed, true)) {
            throw ValidationException::withMessages(['sort' => ['Cette colonne ne peut pas être utilisée pour le tri.']]);
        }

        return $sort;
    }

    public function getDirection(): string
    {
        return $this->validated('direction') ?? 'asc';
    }

    public function getPerPage(): int
    {
        return (int) ($this->validated('perPage') ?? 25);
    }

    /**
     * Indique si l'appelant demande la représentation compacte, destinée aux
     * listes déroulantes du Backoffice.
     */
    public function wantsCompact(): bool
    {
        return $this->boolean('compact');
    }
}

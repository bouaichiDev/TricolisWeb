<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Integrations;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres communs aux listes d'intégration.
 *
 * Les trois configurations et les exports partagent `customerId` et `isActive` ;
 * les filtres propres à chacune s'y ajoutent. Une Request par ressource
 * n'aurait dupliqué que des lignes identiques.
 */
class ListConfigurationRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'customerId' => ['sometimes', 'ulid'],
            'isActive' => ['sometimes', 'boolean'],
            // Import
            'sourceType' => ['sometimes', 'string', 'max:64'],
            'fileFormat' => ['sometimes', 'string', 'max:32'],
            // API
            'lastUsedFrom' => ['sometimes', 'date'],
            'lastUsedTo' => ['sometimes', 'date', 'after_or_equal:lastUsedFrom'],
            // Export
            'exportType' => ['sometimes', 'string', 'max:64'],
            'format' => ['sometimes', 'string', 'max:16'],
            'transport' => ['sometimes', 'string', 'max:16'],
            'frequency' => ['sometimes', 'string', 'max:64'],
            // Export jobs
            'configurationId' => ['sometimes', 'ulid'],
            'entityType' => ['sometimes', 'string', 'max:64'],
            'entityId' => ['sometimes', 'ulid'],
            'generatedFrom' => ['sometimes', 'date'],
            'generatedTo' => ['sometimes', 'date', 'after_or_equal:generatedFrom'],
            'sentFrom' => ['sometimes', 'date'],
            'sentTo' => ['sometimes', 'date', 'after_or_equal:sentFrom'],
        ]);
    }
}

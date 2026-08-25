<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // L'audio est accepte pour les reclamations : un client decrit un
            // dommage en trente secondes de vocal la ou il n'ecrirait pas.
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt,mp3,wav,m4a,ogg,webm'],
            'referenceNumber' => ['nullable', 'string', 'max:255'],
            'documentType' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'max:20'],
            'receivedAt' => ['nullable', 'date'],
            'entityType' => ['nullable', 'required_with:entityId', 'string', 'max:64'],
            'entityId' => ['nullable', 'required_with:entityType', 'ulid'],
        ];
    }
}

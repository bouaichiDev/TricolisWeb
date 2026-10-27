<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Integrations;

use App\Modules\Integrations\Services\Import\Parsers\PayloadParsers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Dépôt d'un fichier à prévisualiser.
 *
 * Le fichier est contrôlé **deux fois** : ici par son extension, son type
 * MIME réel et sa taille ; puis par son lecteur, qui le refuse s'il n'est pas
 * ce qu'il prétend (un `.xlsx` qui n'est pas un classeur, un XML avec DTD).
 * L'extension attendue suit le format de la configuration, pas le choix de
 * l'utilisateur.
 *
 * `customerId` est interdit : le client est toujours celui de la session.
 */
class PreviewCustomerImportRequest extends FormRequest
{
    /** Extensions et types MIME acceptés, par format reconnu. */
    public const array ACCEPTED = [
        'xlsx' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream'],
        'csv' => ['csv,txt', 'text/csv,text/plain,application/csv,application/vnd.ms-excel'],
        'json' => ['json,txt', 'application/json,text/plain'],
        'xml' => ['xml', 'application/xml,text/xml,text/plain'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = intdiv((int) config('customer_integrations.files.max_bytes', 5 * 1024 * 1024), 1024);

        return [
            'customerId' => ['prohibited'],
            'configurationId' => ['required', 'string', 'ulid'],
            'file' => ['required', 'file', "max:{$maxKb}", 'extensions:xlsx,csv,txt,json,xml'],
        ];
    }

    /**
     * Le fichier est-il du format de la configuration ? Extension **et** type
     * MIME détecté sur le contenu — un exécutable renommé `.xlsx` n'a pas le
     * type d'un classeur.
     *
     * @return string|null la raison du refus, ou `null`
     */
    public static function mismatch(UploadedFile $file, string $fileFormat): ?string
    {
        $kind = PayloadParsers::kind($fileFormat);

        if ($kind === null) {
            return 'Le format de cette intégration ne se lit pas depuis un fichier déposé.';
        }

        [$extensions, $mimes] = self::ACCEPTED[$kind];
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, explode(',', $extensions), true) || ! in_array($file->getMimeType(), explode(',', $mimes), true)) {
            return "Ce fichier n’est pas au format attendu par cette intégration ({$kind}).";
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'Ce fichier dépasse la taille autorisée.',
            'file.extensions' => 'Seuls les fichiers .xlsx, .csv, .json et .xml sont acceptés.',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Exports;

use App\Modules\Exports\Models\CustomerExportConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configuration d'export.
 *
 * **`encryptedPassword` n'y figure pas**, ni sous sa forme chiffrée ni
 * déchiffrée : seul le booléen `hasPassword` indique qu'un mot de passe est
 * enregistré. Le §20 l'exige.
 *
 * @mixin CustomerExportConfiguration
 */
class ExportConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'name' => $this->name,
            'exportType' => $this->export_type,
            'format' => $this->format->value,
            'transport' => $this->transport->value,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'hasPassword' => $this->hasPassword(),
            'remoteDirectory' => $this->remote_directory,
            'fileNamePattern' => $this->file_name_pattern,
            'encoding' => $this->encoding,
            'frequency' => $this->frequency,
            'settings' => $this->settings,
            'isActive' => $this->is_active,
            'jobCount' => $this->whenCounted('jobs'),
        ];
    }
}

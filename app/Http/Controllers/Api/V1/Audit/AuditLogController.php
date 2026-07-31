<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Audit\ListAuditLogRequest;
use App\Http\Resources\Api\V1\Audit\AuditLogResource;
use App\Modules\Audit\Models\AuditLog;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    /**
     * Consulter le journal d'audit.
     *
     * Permission requise : `audit.view`. Le journal est en lecture seule : aucune
     * écriture, modification ou suppression n'est exposée par l'API.
     * Filtres : `userId`, `action`, `entityType`, `entityId`, `createdFrom`, `createdTo`.
     */
    public function index(ListAuditLogRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('viewAny', [AuditLog::class, $org]);
        $query = AuditLog::where('organization_id', $org);
        foreach (['userId' => 'user_id', 'action' => 'action', 'entityType' => 'entity_type', 'entityId' => 'entity_id'] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->input($input));
            }
        }
        if ($request->filled('createdFrom')) {
            $query->where('created_at', '>=', $request->date('createdFrom'));
        }
        if ($request->filled('createdTo')) {
            $query->where('created_at', '<=', $request->date('createdTo'));
        }

        return ApiResponse::paginated($query->latest('created_at')->paginate($request->getPerPage())->through(fn ($log) => new AuditLogResource($log)));
    }
}

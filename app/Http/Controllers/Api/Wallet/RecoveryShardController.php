<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wallet;

use App\Domain\KeyManagement\Models\RecoveryShardCloudBackup;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Recovery Shard Backup',
    description: 'Cloud backup metadata for encrypted recovery shards'
)]
class RecoveryShardController extends Controller
{
        #[OA\Post(
            path: '/api/v1/wallet/recovery-shard-backup',
            operationId: 'storeRecoveryShardBackup',
            summary: 'Register or update a recovery shard cloud backup',
            tags: ['Recovery Shard Backup'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['device_id', 'backup_provider', 'encrypted_shard_hash', 'shard_version'], properties: [
        new OA\Property(property: 'device_id', type: 'string', example: 'device_abc123'),
        new OA\Property(property: 'backup_provider', type: 'string', enum: ['icloud', 'google_drive', 'manual'], example: 'icloud'),
        new OA\Property(property: 'encrypted_shard_hash', type: 'string', example: 'sha256hash...'),
        new OA\Property(property: 'shard_version', type: 'string', example: 'v1'),
        new OA\Property(property: 'metadata', type: 'object', nullable: true),
        ]))
        )]
    #[OA\Response(
        response: 201,
        description: 'Backup registered'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'device_id'            => ['required', 'string', 'max:255'],
            'backup_provider'      => ['required', 'string', 'in:icloud,google_drive,manual'],
            'encrypted_shard_hash' => ['required', 'string', 'max:255'],
            'shard_version'        => ['required', 'string', 'max:50'],
            'encrypted_shard'      => ['nullable', 'string', 'max:65535'],
            'metadata'             => ['nullable', 'array'],
        ]);

        $updateData = [
            'encrypted_shard_hash' => $validated['encrypted_shard_hash'],
            'shard_version'        => $validated['shard_version'],
            'metadata'             => $validated['metadata'] ?? null,
        ];

        if (array_key_exists('encrypted_shard', $validated)) {
            $updateData['encrypted_shard'] = $validated['encrypted_shard'];
        }

        $backup = RecoveryShardCloudBackup::updateOrCreate(
            [
                'user_id'         => $user->id,
                'device_id'       => $validated['device_id'],
                'backup_provider' => $validated['backup_provider'],
            ],
            $updateData,
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                   => $backup->uuid,
                'device_id'            => $backup->device_id,
                'backup_provider'      => $backup->backup_provider,
                'encrypted_shard_hash' => $backup->encrypted_shard_hash,
                'shard_version'        => $backup->shard_version,
                'metadata'             => $backup->metadata,
                'created_at'           => $backup->created_at->toIso8601String(),
                'updated_at'           => $backup->updated_at->toIso8601String(),
            ],
        ], 201);
    }

        #[OA\Get(
            path: '/api/v1/wallet/recovery-shard-backup',
            operationId: 'listRecoveryShardBackups',
            summary: 'List recovery shard cloud backups for the authenticated user',
            tags: ['Recovery Shard Backup'],
            security: [['sanctum' => []]],
            parameters: [
        new OA\Parameter(name: 'device_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'backup_provider', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['icloud', 'google_drive', 'manual'])),
        ]
        )]
    #[OA\Response(
        response: 200,
        description: 'List of backups'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = RecoveryShardCloudBackup::forUser($user->id);

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->query('device_id'));
        }

        if ($request->filled('backup_provider')) {
            $query->where('backup_provider', $request->query('backup_provider'));
        }

        $backups = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $backups->map(fn (RecoveryShardCloudBackup $b) => [
                'id'                   => $b->uuid,
                'device_id'            => $b->device_id,
                'backup_provider'      => $b->backup_provider,
                'encrypted_shard_hash' => $b->encrypted_shard_hash,
                'shard_version'        => $b->shard_version,
                'metadata'             => $b->metadata,
                'created_at'           => $b->created_at->toIso8601String(),
                'updated_at'           => $b->updated_at->toIso8601String(),
            ])->all(),
        ]);
    }

        #[OA\Delete(
            path: '/api/v1/wallet/recovery-shard-backup',
            operationId: 'deleteRecoveryShardBackup',
            summary: 'Delete a recovery shard cloud backup',
            tags: ['Recovery Shard Backup'],
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['device_id', 'backup_provider'], properties: [
        new OA\Property(property: 'device_id', type: 'string', example: 'device_abc123'),
        new OA\Property(property: 'backup_provider', type: 'string', enum: ['icloud', 'google_drive', 'manual'], example: 'icloud'),
        ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Backup deleted'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 404,
        description: 'Backup not found'
    )]
    public function destroy(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'device_id'       => ['required', 'string'],
            'backup_provider' => ['required', 'string', 'in:icloud,google_drive,manual'],
        ]);

        $deleted = RecoveryShardCloudBackup::where('user_id', $user->id)
            ->where('device_id', $validated['device_id'])
            ->where('backup_provider', $validated['backup_provider'])
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'BACKUP_NOT_FOUND',
                    'message' => 'No matching recovery shard backup found.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Recovery shard backup deleted.',
        ]);
    }

        #[OA\Get(
            path: '/api/v1/wallet/recovery-shard-backup/retrieve',
            operationId: 'retrieveRecoveryShardBackup',
            summary: 'Retrieve the encrypted shard blob for a specific device and provider',
            tags: ['Recovery Shard Backup'],
            security: [['sanctum' => []]],
            parameters: [
        new OA\Parameter(name: 'device_id', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'backup_provider', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['icloud', 'google_drive', 'manual'])),
        ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Encrypted shard retrieved',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'encrypted_shard', type: 'string', description: 'Base64-encoded encrypted shard blob'),
        new OA\Property(property: 'shard_version', type: 'string', example: 'v1'),
        new OA\Property(property: 'encrypted_shard_hash', type: 'string', example: 'sha256hash...'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Backup not found'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function retrieve(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'device_id'       => ['required', 'string'],
            'backup_provider' => ['required', 'string', 'in:icloud,google_drive,manual'],
        ]);

        $backup = RecoveryShardCloudBackup::where('user_id', $user->id)
            ->where('device_id', $validated['device_id'])
            ->where('backup_provider', $validated['backup_provider'])
            ->first();

        if (! $backup || $backup->encrypted_shard === null) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'SHARD_NOT_FOUND',
                    'message' => 'No encrypted shard found for the specified device and provider.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'encrypted_shard'      => $backup->encrypted_shard,
                'shard_version'        => $backup->shard_version,
                'encrypted_shard_hash' => $backup->encrypted_shard_hash,
            ],
        ]);
    }
}

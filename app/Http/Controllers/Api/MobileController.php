<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobileDeviceSession;
use App\Domain\Mobile\Models\MobileNotificationPreference;
use App\Domain\Mobile\Models\MobilePushNotification;
use App\Domain\Mobile\Services\BiometricAuthenticationService;
use App\Domain\Mobile\Services\MobileDeviceService;
use App\Domain\Mobile\Services\MobileSessionService;
use App\Domain\Mobile\Services\NotificationPreferenceService;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Mobile API Controller.
 *
 * Handles mobile device registration, biometric authentication, and push notifications.
 *
 * @OA\Tag(
 *     name="Mobile",
 *     description="Mobile device management and authentication endpoints"
 * )
 */
class MobileController extends Controller
{
    public function __construct(
        private readonly MobileDeviceService $deviceService,
        private readonly BiometricAuthenticationService $biometricService,
        private readonly PushNotificationService $pushService,
        private readonly MobileSessionService $sessionService,
        private readonly NotificationPreferenceService $preferenceService,
    ) {
    }

    /**
     * Register a mobile device.
     *
     * @OA\Post(
     *     path="/api/mobile/devices",
     *     operationId="registerMobileDevice",
     *     tags={"Mobile"},
     *     summary="Register a mobile device",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id", "platform", "app_version"},
     *             @OA\Property(property="device_id", type="string", maxLength=100),
     *             @OA\Property(property="platform", type="string", enum={"ios", "android"}),
     *             @OA\Property(property="app_version", type="string", maxLength=20),
     *             @OA\Property(property="push_token", type="string", maxLength=500),
     *             @OA\Property(property="device_name", type="string", maxLength=100),
     *             @OA\Property(property="device_model", type="string", maxLength=100),
     *             @OA\Property(property="os_version", type="string", maxLength=50)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Device registered successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id'    => 'required|string|max:100',
            'platform'     => 'required|in:ios,android',
            'app_version'  => 'required|string|max:20',
            'push_token'   => 'nullable|string|max:500',
            'device_name'  => 'nullable|string|max:100',
            'device_model' => 'nullable|string|max:100',
            'os_version'   => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $user = $this->getAuthenticatedUser($request);
        $device = $this->deviceService->registerDevice(
            $user,
            $request->input('device_id'),
            $request->input('platform'),
            $request->input('app_version'),
            $request->input('push_token'),
            $request->input('device_name'),
            $request->input('device_model'),
            $request->input('os_version')
        );

        return response()->json([
            'data'    => $this->formatDeviceResponse($device),
            'message' => 'Device registered successfully',
        ], 201);
    }

    /**
     * List user's mobile devices.
     *
     * @OA\Get(
     *     path="/api/mobile/devices",
     *     operationId="listMobileDevices",
     *     tags={"Mobile"},
     *     summary="List user's registered mobile devices",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="List of devices")
     * )
     */
    public function listDevices(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        $devices = $this->deviceService->getUserDevices($user);

        return response()->json([
            'data' => $devices->map(fn (MobileDevice $device) => $this->formatDeviceResponse($device)),
        ]);
    }

    /**
     * Get a specific device.
     *
     * @OA\Get(
     *     path="/api/mobile/devices/{id}",
     *     operationId="getMobileDevice",
     *     tags={"Mobile"},
     *     summary="Get a specific mobile device",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Device details"),
     *     @OA\Response(response=404, description="Device not found")
     * )
     */
    public function getDevice(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        $device = $this->deviceService->findByIdForUser($id, $user);

        if (! $device) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Device not found',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $this->formatDeviceResponse($device),
        ]);
    }

    /**
     * Unregister a mobile device.
     *
     * @OA\Delete(
     *     path="/api/mobile/devices/{id}",
     *     operationId="unregisterMobileDevice",
     *     tags={"Mobile"},
     *     summary="Unregister a mobile device",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Device unregistered"),
     *     @OA\Response(response=404, description="Device not found")
     * )
     */
    public function unregisterDevice(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        $device = $this->deviceService->findByIdForUser($id, $user);

        if (! $device) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Device not found',
                ],
            ], 404);
        }

        $this->deviceService->unregisterDevice($device);

        return response()->json([
            'message' => 'Device unregistered successfully',
        ]);
    }

    /**
     * Update push token for a device.
     *
     * @OA\Patch(
     *     path="/api/mobile/devices/{id}/token",
     *     operationId="updatePushToken",
     *     tags={"Mobile"},
     *     summary="Update push notification token",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"push_token"},
     *             @OA\Property(property="push_token", type="string", maxLength=500)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Token updated"),
     *     @OA\Response(response=404, description="Device not found")
     * )
     */
    public function updatePushToken(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'push_token' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $user = $this->getAuthenticatedUser($request);
        $device = $this->deviceService->findByIdForUser($id, $user);

        if (! $device) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Device not found',
                ],
            ], 404);
        }

        $this->deviceService->updatePushToken($device, $request->input('push_token'));

        return response()->json([
            'message' => 'Push token updated successfully',
        ]);
    }

    /**
     * Enable biometric authentication.
     *
     * @OA\Post(
     *     path="/api/mobile/auth/biometric/enable",
     *     operationId="enableBiometric",
     *     tags={"Mobile"},
     *     summary="Enable biometric authentication for a device",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id", "public_key"},
     *             @OA\Property(property="device_id", type="string"),
     *             @OA\Property(property="public_key", type="string", description="Base64 encoded ECDSA P-256 public key"),
     *             @OA\Property(property="key_id", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Biometric enabled"),
     *     @OA\Response(response=400, description="Invalid public key"),
     *     @OA\Response(response=404, description="Device not found")
     * )
     */
    public function enableBiometric(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id'  => 'required|string',
            'public_key' => 'required|string',
            'key_id'     => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $user = $this->getAuthenticatedUser($request);
        $device = $this->deviceService->findByDeviceId($request->input('device_id'));

        if (! $device || $device->user_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Device not found',
                ],
            ], 404);
        }

        if ($device->is_blocked) {
            return response()->json([
                'error' => [
                    'code'    => 'DEVICE_BLOCKED',
                    'message' => 'Device is blocked',
                ],
            ], 403);
        }

        $success = $this->biometricService->enableBiometric(
            $device,
            $request->input('public_key'),
            $request->input('key_id')
        );

        if (! $success) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_PUBLIC_KEY',
                    'message' => 'Invalid public key format',
                ],
            ], 400);
        }

        return response()->json([
            'data' => [
                'enabled'   => true,
                'device_id' => $device->device_id,
            ],
            'message' => 'Biometric authentication enabled',
        ]);
    }

    /**
     * Disable biometric authentication.
     *
     * @OA\Delete(
     *     path="/api/mobile/auth/biometric/disable",
     *     operationId="disableBiometric",
     *     tags={"Mobile"},
     *     summary="Disable biometric authentication for a device",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id"},
     *             @OA\Property(property="device_id", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Biometric disabled"),
     *     @OA\Response(response=404, description="Device not found")
     * )
     */
    public function disableBiometric(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $user = $this->getAuthenticatedUser($request);
        $device = $this->deviceService->findByDeviceId($request->input('device_id'));

        if (! $device || $device->user_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Device not found',
                ],
            ], 404);
        }

        $this->biometricService->disableBiometric($device);

        return response()->json([
            'message' => 'Biometric authentication disabled',
        ]);
    }

    /**
     * Get biometric challenge for authentication.
     *
     * @OA\Post(
     *     path="/api/mobile/auth/biometric/challenge",
     *     operationId="getBiometricChallenge",
     *     tags={"Mobile"},
     *     summary="Get a challenge for biometric authentication",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id"},
     *             @OA\Property(property="device_id", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Challenge created"),
     *     @OA\Response(response=400, description="Biometric not enabled"),
     *     @OA\Response(response=404, description="Device not found")
     * )
     */
    public function getBiometricChallenge(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $device = $this->deviceService->findByDeviceId($request->input('device_id'));

        if (! $device) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Device not found',
                ],
            ], 404);
        }

        if (! $device->canUseBiometric()) {
            return response()->json([
                'error' => [
                    'code'    => 'BIOMETRIC_NOT_AVAILABLE',
                    'message' => 'Biometric authentication is not available for this device',
                ],
            ], 400);
        }

        $challenge = $this->biometricService->createChallenge($device, $request->ip());

        return response()->json([
            'data' => [
                'challenge'  => $challenge->challenge,
                'expires_at' => $challenge->expires_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Verify biometric signature and get access token.
     *
     * @OA\Post(
     *     path="/api/mobile/auth/biometric/verify",
     *     operationId="verifyBiometric",
     *     tags={"Mobile"},
     *     summary="Verify biometric signature and get access token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id", "challenge", "signature"},
     *             @OA\Property(property="device_id", type="string"),
     *             @OA\Property(property="challenge", type="string"),
     *             @OA\Property(property="signature", type="string", description="Base64 encoded signature")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Authentication successful"),
     *     @OA\Response(response=401, description="Authentication failed"),
     *     @OA\Response(response=404, description="Device not found")
     * )
     */
    public function verifyBiometric(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
            'challenge' => 'required|string',
            'signature' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $device = $this->deviceService->findByDeviceId($request->input('device_id'));

        if (! $device) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Device not found',
                ],
            ], 404);
        }

        $result = $this->biometricService->verifyAndCreateSession(
            $device,
            $request->input('challenge'),
            $request->input('signature'),
            $request->ip()
        );

        if (! $result) {
            return response()->json([
                'error' => [
                    'code'    => 'AUTHENTICATION_FAILED',
                    'message' => 'Biometric verification failed',
                ],
            ], 401);
        }

        return response()->json([
            'data' => [
                'access_token' => $result['token'],
                'token_type'   => 'Bearer',
                'expires_at'   => $result['expires_at']->toIso8601String(),
            ],
            'message' => 'Authentication successful',
        ]);
    }

    /**
     * Get notification history.
     *
     * @OA\Get(
     *     path="/api/mobile/notifications",
     *     operationId="getNotifications",
     *     tags={"Mobile"},
     *     summary="Get notification history",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=50)),
     *     @OA\Response(response=200, description="Notification list")
     * )
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        $limit = min((int) $request->input('limit', 50), 100);
        $notifications = $this->pushService->getNotificationHistory($user, $limit);

        return response()->json([
            'data' => $notifications->map(fn (MobilePushNotification $n) => [
                'id'         => $n->id,
                'type'       => $n->notification_type,
                'title'      => $n->title,
                'body'       => $n->body,
                'data'       => $n->data,
                'status'     => $n->status,
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ]),
            'unread_count' => $this->pushService->getUnreadCount($user),
        ]);
    }

    /**
     * Mark notification as read.
     *
     * @OA\Post(
     *     path="/api/mobile/notifications/{id}/read",
     *     operationId="markNotificationRead",
     *     tags={"Mobile"},
     *     summary="Mark a notification as read",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Marked as read"),
     *     @OA\Response(response=404, description="Notification not found")
     * )
     */
    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        $notification = MobilePushNotification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $notification) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Notification not found',
                ],
            ], 404);
        }

        $this->pushService->markAsRead($notification);

        return response()->json([
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read.
     *
     * @OA\Post(
     *     path="/api/mobile/notifications/read-all",
     *     operationId="markAllNotificationsRead",
     *     tags={"Mobile"},
     *     summary="Mark all notifications as read",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="All marked as read")
     * )
     */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        $count = $this->pushService->markAllAsRead($user);

        return response()->json([
            'message' => "{$count} notifications marked as read",
            'count'   => $count,
        ]);
    }

    /**
     * Get mobile app configuration.
     *
     * @OA\Get(
     *     path="/api/mobile/config",
     *     operationId="getMobileConfig",
     *     tags={"Mobile"},
     *     summary="Get mobile app configuration",
     *     @OA\Response(response=200, description="App configuration")
     * )
     */
    public function getConfig(): JsonResponse
    {
        return response()->json([
            'data' => [
                'min_app_version'    => config('mobile.min_version', '1.0.0'),
                'latest_app_version' => config('mobile.latest_version', '1.0.0'),
                'force_update'       => config('mobile.force_update', false),
                'maintenance_mode'   => app()->isDownForMaintenance(),
                'features'           => [
                    'biometric_auth'     => config('mobile.features.biometric', true),
                    'push_notifications' => config('mobile.features.push', true),
                    'gcu_trading'        => config('mobile.features.gcu_trading', true),
                    'p2p_transfers'      => config('mobile.features.p2p_transfers', true),
                ],
                'websocket' => [
                    'enabled' => config('broadcasting.default') !== 'log',
                    'host'    => config('broadcasting.connections.pusher.options.host'),
                    'port'    => config('broadcasting.connections.pusher.options.port'),
                    'key'     => config('broadcasting.connections.pusher.key'),
                ],
            ],
        ]);
    }

    /**
     * Block a mobile device.
     *
     * @OA\Post(
     *     path="/api/mobile/devices/{id}/block",
     *     operationId="blockMobileDevice",
     *     tags={"Mobile"},
     *     summary="Block a mobile device",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response="200", description="Device blocked"),
     *     @OA\Response(response="404", description="Device not found")
     * )
     */
    public function blockDevice(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        $validator = Validator::make($request->all(), [
            'reason' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422);
        }

        $device = $this->deviceService->findByIdForUser($id, $user);
        if (! $device instanceof MobileDevice) {
            return response()->json([
                'error' => [
                    'code'    => 'DEVICE_NOT_FOUND',
                    'message' => 'Device not found',
                ],
            ], 404);
        }

        $reason = $request->input('reason', 'User requested block');
        $this->deviceService->blockDevice($device, $reason);

        // Revoke all sessions for this device
        $this->sessionService->revokeDeviceSessions($device);

        /** @var MobileDevice $refreshedDevice */
        $refreshedDevice = $device->fresh();

        return response()->json([
            'message' => 'Device blocked successfully',
            'device'  => $this->formatDeviceResponse($refreshedDevice),
        ]);
    }

    /**
     * Unblock a mobile device.
     *
     * @OA\Post(
     *     path="/api/mobile/devices/{id}/unblock",
     *     operationId="unblockMobileDevice",
     *     tags={"Mobile"},
     *     summary="Unblock a mobile device",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response="200", description="Device unblocked"),
     *     @OA\Response(response="404", description="Device not found")
     * )
     */
    public function unblockDevice(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        $device = $this->deviceService->findByIdForUser($id, $user);
        if (! $device instanceof MobileDevice) {
            return response()->json([
                'error' => [
                    'code'    => 'DEVICE_NOT_FOUND',
                    'message' => 'Device not found',
                ],
            ], 404);
        }

        $this->deviceService->unblockDevice($device);

        /** @var MobileDevice $refreshedDevice */
        $refreshedDevice = $device->fresh();

        return response()->json([
            'message' => 'Device unblocked successfully',
            'device'  => $this->formatDeviceResponse($refreshedDevice),
        ]);
    }

    /**
     * Trust a mobile device.
     *
     * @OA\Post(
     *     path="/api/mobile/devices/{id}/trust",
     *     operationId="trustMobileDevice",
     *     tags={"Mobile"},
     *     summary="Mark a device as trusted",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response="200", description="Device trusted"),
     *     @OA\Response(response="404", description="Device not found")
     * )
     */
    public function trustDevice(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        $device = $this->deviceService->findByIdForUser($id, $user);
        if (! $device instanceof MobileDevice) {
            return response()->json([
                'error' => [
                    'code'    => 'DEVICE_NOT_FOUND',
                    'message' => 'Device not found',
                ],
            ], 404);
        }

        if ($device->is_blocked) {
            return response()->json([
                'error' => [
                    'code'    => 'DEVICE_BLOCKED',
                    'message' => 'Cannot trust a blocked device',
                ],
            ], 400);
        }

        $this->deviceService->trustDevice($device, (string) $user->id);

        /** @var MobileDevice $refreshedDevice */
        $refreshedDevice = $device->fresh();

        return response()->json([
            'message' => 'Device trusted successfully',
            'device'  => $this->formatDeviceResponse($refreshedDevice),
        ]);
    }

    /**
     * List active sessions for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/mobile/sessions",
     *     operationId="listMobileSessions",
     *     tags={"Mobile"},
     *     summary="List active mobile sessions",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response="200", description="List of active sessions")
     * )
     */
    public function listSessions(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        $sessions = $this->sessionService->getUserSessions($user);
        $stats = $this->sessionService->getSessionStats($user);

        return response()->json([
            'sessions' => $sessions->map(fn (MobileDeviceSession $session) => [
                'id'     => $session->id,
                'device' => $session->mobileDevice ? [
                    'id'       => $session->mobileDevice->id,
                    'name'     => $session->mobileDevice->getDisplayName(),
                    'platform' => $session->mobileDevice->platform,
                ] : null,
                'ip_address'       => $session->ip_address,
                'is_biometric'     => $session->is_biometric_session,
                'last_activity_at' => $session->last_activity_at->toIso8601String(),
                'expires_at'       => $session->expires_at->toIso8601String(),
                'created_at'       => $session->created_at->toIso8601String(),
            ]),
            'stats' => $stats,
        ]);
    }

    /**
     * Revoke a specific session.
     *
     * @OA\Delete(
     *     path="/api/mobile/sessions/{id}",
     *     operationId="revokeMobileSession",
     *     tags={"Mobile"},
     *     summary="Revoke a specific mobile session",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response="200", description="Session revoked"),
     *     @OA\Response(response="404", description="Session not found")
     * )
     */
    public function revokeSession(Request $request, string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        $session = $this->sessionService->findSessionForUser($id, $user);
        if (! $session instanceof MobileDeviceSession) {
            return response()->json([
                'error' => [
                    'code'    => 'SESSION_NOT_FOUND',
                    'message' => 'Session not found',
                ],
            ], 404);
        }

        $this->sessionService->revokeSession($session);

        return response()->json([
            'message' => 'Session revoked successfully',
        ]);
    }

    /**
     * Revoke all sessions except the current one.
     *
     * @OA\Delete(
     *     path="/api/mobile/sessions",
     *     operationId="revokeAllMobileSessions",
     *     tags={"Mobile"},
     *     summary="Revoke all mobile sessions except current",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response="200", description="Sessions revoked")
     * )
     */
    public function revokeAllSessions(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        // Get current session ID from header if available
        $currentSessionId = $request->header('X-Mobile-Session-Id');

        $count = $this->sessionService->revokeAllUserSessions($user, $currentSessionId);

        return response()->json([
            'message'       => 'Sessions revoked successfully',
            'revoked_count' => $count,
        ]);
    }

    /**
     * Refresh the authentication token.
     *
     * @OA\Post(
     *     path="/api/mobile/auth/refresh",
     *     operationId="refreshMobileToken",
     *     tags={"Mobile"},
     *     summary="Refresh the authentication token",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response="200", description="Token refreshed")
     * )
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        // Get current token and its abilities before deleting
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $currentToken */
        $currentToken = $user->currentAccessToken();
        $abilities = $currentToken !== null ? ($currentToken->abilities ?? ['*']) : ['*'];

        // Delete current token
        if ($currentToken !== null) {
            $currentToken->delete();
        }

        // Create new token with same abilities
        $newToken = $user->createToken('mobile-app', $abilities);

        return response()->json([
            'token'      => $newToken->plainTextToken,
            'expires_at' => $newToken->accessToken->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Get notification preferences.
     *
     * @OA\Get(
     *     path="/api/mobile/notifications/preferences",
     *     operationId="getNotificationPreferences",
     *     tags={"Mobile"},
     *     summary="Get notification preferences",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response="200", description="Notification preferences")
     * )
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        // Check if device-specific preferences requested
        $deviceId = $request->query('device_id');
        $device = null;
        if ($deviceId !== null) {
            $device = $this->deviceService->findByIdForUser((string) $deviceId, $user);
        }

        $preferences = $this->preferenceService->getUserPreferences($user, $device);

        return response()->json([
            'preferences'     => array_values($preferences),
            'available_types' => MobileNotificationPreference::getAvailableTypes(),
        ]);
    }

    /**
     * Update notification preferences.
     *
     * @OA\Put(
     *     path="/api/mobile/notifications/preferences",
     *     operationId="updateNotificationPreferences",
     *     tags={"Mobile"},
     *     summary="Update notification preferences",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"preferences"},
     *             @OA\Property(
     *                 property="preferences",
     *                 type="object",
     *                 additionalProperties=@OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="push_enabled", type="boolean"),
     *                     @OA\Property(property="email_enabled", type="boolean")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response="200", description="Preferences updated")
     * )
     */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        $validator = Validator::make($request->all(), [
            'preferences'                 => ['required', 'array'],
            'preferences.*'               => ['array'],
            'preferences.*.push_enabled'  => ['sometimes', 'boolean'],
            'preferences.*.email_enabled' => ['sometimes', 'boolean'],
            'device_id'                   => ['sometimes', 'uuid'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422);
        }

        $device = null;
        $deviceId = $request->input('device_id');
        if ($deviceId !== null) {
            $device = $this->deviceService->findByIdForUser($deviceId, $user);
            if (! $device instanceof MobileDevice) {
                return response()->json([
                    'error' => [
                        'code'    => 'DEVICE_NOT_FOUND',
                        'message' => 'Device not found',
                    ],
                ], 404);
            }
        }

        /** @var array<string, array{push_enabled?: bool, email_enabled?: bool}> $preferences */
        $preferences = $request->input('preferences');
        $this->preferenceService->updatePreferences($user, $preferences, $device);

        $updatedPreferences = $this->preferenceService->getUserPreferences($user, $device);

        return response()->json([
            'message'     => 'Preferences updated successfully',
            'preferences' => array_values($updatedPreferences),
        ]);
    }

    /**
     * Get the authenticated user from the request.
     *
     * @throws RuntimeException If user is not authenticated
     */
    private function getAuthenticatedUser(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new RuntimeException('User must be authenticated');
        }

        return $user;
    }

    /**
     * Format device response.
     *
     * @return array<string, mixed>
     */
    private function formatDeviceResponse(MobileDevice $device): array
    {
        return [
            'id'                => $device->id,
            'device_id'         => $device->device_id,
            'platform'          => $device->platform,
            'device_name'       => $device->getDisplayName(),
            'device_model'      => $device->device_model,
            'os_version'        => $device->os_version,
            'app_version'       => $device->app_version,
            'biometric_enabled' => $device->biometric_enabled,
            'is_trusted'        => $device->is_trusted,
            'is_blocked'        => $device->is_blocked,
            'has_push_token'    => $device->push_token !== null,
            'last_active_at'    => $device->last_active_at?->toIso8601String(),
            'created_at'        => $device->created_at->toIso8601String(),
        ];
    }
}

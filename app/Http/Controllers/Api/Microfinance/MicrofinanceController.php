<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Microfinance;

use App\Domain\Microfinance\Models\ShareAccount;
use App\Domain\Microfinance\Services\FieldOfficerService;
use App\Domain\Microfinance\Services\GroupLendingService;
use App\Domain\Microfinance\Services\LoanProvisioningService;
use App\Domain\Microfinance\Services\ShareAccountService;
use App\Domain\Microfinance\Support\MicrofinanceAccess;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class MicrofinanceController extends Controller
{
    public function __construct(
        private readonly GroupLendingService $groupLendingService,
        private readonly ShareAccountService $shareAccountService,
        private readonly LoanProvisioningService $loanProvisioningService,
        private readonly FieldOfficerService $fieldOfficerService,
    ) {
    }

    // -----------------------------------------------------------------------
    // Groups
    // -----------------------------------------------------------------------

    public function createGroup(Request $request): JsonResponse
    {
        if (! MicrofinanceAccess::isOperator($request->user())) {
            return $this->forbidden('You are not authorized to create groups.');
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'frequency'   => ['sometimes', 'string', 'in:daily,weekly,biweekly,monthly'],
            'center'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_day' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        try {
            $group = $this->groupLendingService->createGroup(
                name: $validated['name'],
                meetingFrequency: $validated['frequency'] ?? 'weekly',
                centerName: $validated['center'] ?? null,
                meetingDay: $validated['meeting_day'] ?? null,
            );

            return response()->json(['data' => $group], 201);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function activateGroup(Request $request, string $id): JsonResponse
    {
        if (! MicrofinanceAccess::isOperator($request->user())) {
            return $this->forbidden('You are not authorized to activate groups.');
        }

        try {
            $group = $this->groupLendingService->activateGroup($id);

            return response()->json(['data' => $group]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function closeGroup(Request $request, string $id): JsonResponse
    {
        if (! MicrofinanceAccess::isOperator($request->user())) {
            return $this->forbidden('You are not authorized to close groups.');
        }

        try {
            $group = $this->groupLendingService->closeGroup($id);

            return response()->json(['data' => $group]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function addMember(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'role'    => ['sometimes', 'string', 'in:member,leader,treasurer'],
        ]);

        $role = (string) ($validated['role'] ?? 'member');

        if (! MicrofinanceAccess::mayManageGroup($request->user(), $id)) {
            return $this->forbidden('You are not authorized to manage this group.');
        }

        if (! MicrofinanceAccess::mayAssignRole($request->user(), $role)) {
            return $this->forbidden('You are not authorized to assign this role.');
        }

        try {
            $member = $this->groupLendingService->addMember(
                groupId: $id,
                userId: (int) $validated['user_id'],
                role: $role,
            );

            return response()->json(['data' => $member], 201);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function removeMember(Request $request, string $id, int $userId): JsonResponse
    {
        if (! MicrofinanceAccess::mayManageGroup($request->user(), $id)) {
            return $this->forbidden('You are not authorized to manage this group.');
        }

        try {
            $this->groupLendingService->removeMember($id, $userId);

            return response()->json(['message' => 'Member removed successfully']);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function listMembers(string $id): JsonResponse
    {
        try {
            $members = $this->groupLendingService->getGroupMembers($id);

            return response()->json(['data' => $members]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function recordMeeting(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'attendees_count' => ['required', 'integer', 'min:0'],
            'minutes'         => ['sometimes', 'nullable', 'string'],
        ]);

        if (! MicrofinanceAccess::mayManageGroup($request->user(), $id)) {
            return $this->forbidden('You are not authorized to manage this group.');
        }

        try {
            $meeting = $this->groupLendingService->recordMeeting(
                groupId: $id,
                attendeesCount: (int) $validated['attendees_count'],
                minutes: $validated['minutes'] ?? null,
            );

            return response()->json(['data' => $meeting], 201);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // -----------------------------------------------------------------------
    // Share Accounts
    // -----------------------------------------------------------------------

    public function listShareAccounts(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $accounts = ShareAccount::forUser($user->id)->get();

        return response()->json(['data' => $accounts]);
    }

    public function openShareAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'group_id' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        try {
            $account = $this->shareAccountService->openAccount(
                userId: $user->id,
                groupId: $validated['group_id'] ?? null,
                currency: $validated['currency'] ?? 'USD',
            );

            return response()->json(['data' => $account], 201);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function purchaseShares(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'shares' => ['required', 'integer', 'min:1'],
        ]);

        if (! $this->mayUseShareAccount($request, $id)) {
            return $this->forbidden('You are not authorized to use this share account.');
        }

        try {
            $account = $this->shareAccountService->purchaseShares($id, (int) $validated['shares']);

            return response()->json(['data' => $account]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function redeemShares(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'shares' => ['required', 'integer', 'min:1'],
        ]);

        if (! $this->mayUseShareAccount($request, $id)) {
            return $this->forbidden('You are not authorized to use this share account.');
        }

        try {
            $account = $this->shareAccountService->redeemShares($id, (int) $validated['shares']);

            return response()->json(['data' => $account]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // -----------------------------------------------------------------------
    // Provisioning
    // -----------------------------------------------------------------------

    /**
     * Loan loss provisions cover every loan of the institution, so both
     * provisioning reads are for operators only.
     */
    public function getProvisionSummary(Request $request): JsonResponse
    {
        if (! MicrofinanceAccess::isOperator($request->user())) {
            return $this->forbidden('You are not authorized to view loan provisions.');
        }

        $totals = $this->loanProvisioningService->getTotalProvisions();

        return response()->json(['data' => $totals]);
    }

    public function getProvisionsByCategory(Request $request, string $category): JsonResponse
    {
        if (! MicrofinanceAccess::isOperator($request->user())) {
            return $this->forbidden('You are not authorized to view loan provisions.');
        }

        try {
            $provisions = $this->loanProvisioningService->getProvisionsByCategory($category);

            return response()->json(['data' => $provisions]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // -----------------------------------------------------------------------
    // Collection Sheets
    // -----------------------------------------------------------------------

    public function generateCollectionSheet(Request $request): JsonResponse
    {
        // Field-officer / operator work (the scheduled
        // mfi:generate-collection-sheets command is the other producer).
        if (! MicrofinanceAccess::isOperator($request->user())) {
            return $this->forbidden('You are not authorized to generate collection sheets.');
        }

        $validated = $request->validate([
            'officer_id'      => ['required', 'string'],
            'group_id'        => ['required', 'string'],
            'collection_date' => ['sometimes', 'date'],
            // Plain decimal with at most two decimals: rejects exponent
            // forms such as 1e3 and silently truncated amounts.
            'expected_amount' => ['required', 'decimal:0,2', 'min:0'],
        ]);

        $expectedAmount = (string) $validated['expected_amount'];
        if (! is_numeric($expectedAmount)) {
            return response()->json(['error' => 'The expected amount must be a number.'], 422);
        }

        try {
            $sheet = $this->fieldOfficerService->generateCollectionSheet(
                officerId: $validated['officer_id'],
                groupId: $validated['group_id'],
                collectionDate: $validated['collection_date'] ?? Carbon::today()->toDateString(),
                expectedAmount: bcadd($expectedAmount, '0', 2),
            );

            return response()->json(['data' => $sheet], 201);
        } catch (Throwable $e) {
            // FieldOfficerService reports its own errors (an unknown officer)
            // as a plain RuntimeException. Anything else (database, bcmath or
            // PHP errors) is logged, and the caller gets a generic message.
            if ($e::class === RuntimeException::class) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            report($e);

            return response()->json(['error' => 'The collection sheet could not be created.'], 422);
        }
    }

    // -----------------------------------------------------------------------
    // Access
    // -----------------------------------------------------------------------

    /**
     * The share account's holder or an operator. Unknown ids are left to the
     * service, which reports them as not found.
     */
    private function mayUseShareAccount(Request $request, string $id): bool
    {
        /** @var ShareAccount|null $account */
        $account = ShareAccount::query()->find($id);

        return $account === null || MicrofinanceAccess::mayUseShareAccount($request->user(), $account);
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json(['error' => $message], 403);
    }
}

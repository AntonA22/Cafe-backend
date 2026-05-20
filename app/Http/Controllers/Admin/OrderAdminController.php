<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderAdminController extends Controller
{
    /**
     * GET /admin/orders?status=...&delivery_mode=...&user_id=...&q=...&date_from=YYYY-MM-DD&date_to=YYYY-MM-DD&per_page=20
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        // Можно валидировать фильтры, чтобы не ловить мусор
        $request->validate([
            'status' => ['sometimes', 'string', Rule::in(Order::STATUSES)],
            'delivery_mode' => ['sometimes', 'string', Rule::in(Order::DELIVERY_MODES)],
            'user_id' => ['sometimes'],
            'q' => ['sometimes', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = Order::query()
            ->with(['user', 'items.dessert', 'address'])
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->query('status'));
            })
            ->when($request->filled('delivery_mode'), function ($q) use ($request) {
                $q->where('delivery_mode', $request->query('delivery_mode'));
            })
            ->when($request->filled('user_id'), function ($q) use ($request) {
                // user_id может быть int или uuid — не кастим в (int)
                $q->where('user_id', $request->query('user_id'));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = trim((string) $request->query('q'));
                $normalizedOrderNumber = preg_replace('/\D+/', '', $search);

                $q->where(function ($query) use ($search, $normalizedOrderNumber) {
                    $query->where('id', $search);

                    if ($normalizedOrderNumber !== '') {
                        $query->orWhere('order_number', $normalizedOrderNumber);
                    }
                });
            })
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->query('date_from'));
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->query('date_to'));
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $orders->setCollection($orders->getCollection()->map(
            fn (Order $order) => (new OrderResource($order))->resolve($request)
        ));

        return response()->json([
            'success' => true,
            'data' => $orders,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /admin/orders/{id}
     */
    public function show($id)
    {
        $order = Order::query()
            ->with(['user', 'items.dessert', 'address'])
            ->find($id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found',
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * PATCH /admin/orders/{id}/status  { "status": "processing" }
     */
    public function setStatus(Request $request, $id)
    {
        $order = Order::with('user')->find($id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found',
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Order::STATUSES)],
        ]);

        if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true)) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot change status of delivered/cancelled order',
            ], 409, [], JSON_UNESCAPED_UNICODE);
        }

        $newStatus = $validated['status'];

        if (! in_array($newStatus, $this->allowedTransitions()[$order->status] ?? [], true)) {
            return response()->json([
                'success' => false,
                'error' => "Invalid status transition from {$order->status} to {$newStatus}",
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        try {
            $order = DB::transaction(function () use ($order, $newStatus) {
                $lockedOrder = Order::query()
                    ->with('user')
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($lockedOrder->status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true)) {
                    throw new \RuntimeException('Cannot change status of delivered/cancelled order');
                }

                if (! in_array($newStatus, $this->allowedTransitions()[$lockedOrder->status] ?? [], true)) {
                    throw new \InvalidArgumentException("Invalid status transition from {$lockedOrder->status} to {$newStatus}");
                }

                $lockedOrder->status = $newStatus;

                if ($newStatus === Order::STATUS_CANCELLED) {
                    $this->restoreSpentBonusPoints($lockedOrder);
                }

                if ($newStatus === Order::STATUS_DELIVERED && (int) $lockedOrder->bonus_points_earned === 0) {
                    $earned = $this->bonusPointsForDeliveredOrder($lockedOrder);
                    $lockedOrder->bonus_points_earned = $earned;

                    if ($earned > 0) {
                        User::query()
                            ->whereKey($lockedOrder->user_id)
                            ->increment('bonus_points', $earned);
                    }
                }

                $lockedOrder->save();

                return $lockedOrder;
            });
        } catch (\RuntimeException $error) {
            return response()->json([
                'success' => false,
                'error' => $error->getMessage(),
            ], 409, [], JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $error) {
            return response()->json([
                'success' => false,
                'error' => $error->getMessage(),
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $fcmToken = $order->user?->fcm_token;
        if ($fcmToken) {
            app(FirebaseNotificationService::class)->sendOrderStatusUpdate(
                $fcmToken,
                $order->id,
                $newStatus,
                $order->delivery_mode
            );
        }

        $order = $order->fresh(['user', 'items.dessert', 'address']);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function bonusPointsForDeliveredOrder(Order $order): int
    {
        $rewardBase = max(0, (int) $order->subtotal_price - (int) $order->bonus_points_spent);

        return (int) floor($rewardBase * Order::BONUS_EARN_RATE);
    }

    private function allowedTransitions(): array
    {
        return [
            Order::STATUS_NEW => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
            Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
            Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED],
            Order::STATUS_DELIVERED => [],
            Order::STATUS_CANCELLED => [],
        ];
    }

    private function restoreSpentBonusPoints(Order $order): void
    {
        if ((int) $order->bonus_points_spent <= 0 || $order->bonus_points_refunded_at !== null) {
            return;
        }

        User::query()
            ->whereKey($order->user_id)
            ->increment('bonus_points', (int) $order->bonus_points_spent);

        $order->bonus_points_refunded_at = now();
    }
}

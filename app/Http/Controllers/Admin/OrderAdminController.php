<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderAdminController extends Controller
{
    /**
     * GET /admin/orders?status=...&user_id=...&q=...&per_page=20
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        // Можно валидировать фильтры, чтобы не ловить мусор
        $request->validate([
            'status' => ['sometimes', 'string', Rule::in(Order::STATUSES)],
            'user_id' => ['sometimes'],
            'q' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = Order::query()
            ->with(['user', 'items.dessert', 'address'])
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->query('status'));
            })
            ->when($request->filled('user_id'), function ($q) use ($request) {
                // user_id может быть int или uuid — не кастим в (int)
                $q->where('user_id', $request->query('user_id'));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = trim((string) $request->query('q'));

                // Если у тебя id = UUID/string — просто ищем по id как строке
                // (Если id int — тоже нормально, строка "12" найдёт 12)
                $q->where('id', $search);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

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

        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found'
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * PATCH /admin/orders/{id}/status  { "status": "processing" }
     */
    public function setStatus(Request $request, $id)
    {
        $order = Order::with('user')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found'
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Order::STATUSES)],
        ]);

        // Опционально: запрещаем менять завершённые
        if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true)) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot change status of delivered/cancelled order',
            ], 409, [], JSON_UNESCAPED_UNICODE);
        }

        // Опционально: запрещаем "откат" статусов назад (если нужно)
        // Пример порядка: new -> processing -> shipped -> delivered
        // cancelled можно из любого "актуального"
        $allowedTransitions = [
            Order::STATUS_NEW => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
            Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
            Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED],
            Order::STATUS_DELIVERED => [],
            Order::STATUS_CANCELLED => [],
        ];

        $newStatus = $validated['status'];

        if (!in_array($newStatus, $allowedTransitions[$order->status] ?? [], true)) {
            return response()->json([
                'success' => false,
                'error' => "Invalid status transition from {$order->status} to {$newStatus}",
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $order->status = $newStatus;
        $order->save();

        $fcmToken = $order->user?->fcm_token;
        if ($fcmToken) {
            app(FirebaseNotificationService::class)->sendOrderStatusUpdate(
                $fcmToken,
                $order->id,
                $newStatus
            );
        }

        return response()->json([
            'success' => true,
            'data' => $order->fresh(),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}

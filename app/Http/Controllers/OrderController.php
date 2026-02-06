<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private function userCart(Request $request): Cart
    {
        return Cart::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);
    }

    // GET /orders
    public function index(Request $request)
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.dessert', 'address'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    // GET /orders/{id}
    public function show(Request $request, string $id)
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.dessert', 'address'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    // POST /orders  (создать заказ из корзины)
    public function store(Request $request)
    {
        $data = $request->validate([
            'address_id'    => ['required'],          // если uuid: добавь 'uuid'
            'comment'       => ['nullable','string','max:500'],
            'payment_mode'  => ['nullable','string'], // card/cash
            'delivery_mode' => ['nullable','string'], // delivery/pickup
            'leave_at_door' => ['nullable','boolean'],
            'phone'         => ['nullable','string','max:50'],
        ]);

        $cart = $this->userCart($request)->load('items.dessert');

        if ($cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'Корзина пустая',
            ], 422);
        }

        return DB::transaction(function () use ($request, $data, $cart) {

            $itemsCount = 0;
            $total = 0;

            $order = Order::create([
                'user_id'     => $request->user()->id,
                'address_id'  => $data['address_id'],
                'status'      => 'new',
                'items_count' => 0,
                'total_price' => 0,
                'comment'     => $data['comment'] ?? null,

                // если в orders таблице нет этих полей — удали строки ниже
                // 'payment_mode'  => $data['payment_mode'] ?? null,
                // 'delivery_mode' => $data['delivery_mode'] ?? null,
                // 'leave_at_door' => $data['leave_at_door'] ?? null,
                // 'phone'         => $data['phone'] ?? null,
            ]);

            foreach ($cart->items as $item) {
                $qty = (int) $item->qty;

                // У тебя в cart_item хранится price (фиксируешь при добавлении)
                $price = (int) $item->price;

                // Если хочешь брать актуальную цену десерта, то:
                // $price = (int) $item->dessert->price;

                $sum = $price * $qty;

                OrderItem::create([
                    'order_id'   => $order->id,
                    'dessert_id' => $item->dessert_id,
                    'qty'        => $qty,
                    'price'      => $price,
                    'sum'        => $sum,
                ]);

                $itemsCount += $qty;
                $total += $sum;
            }

            $order->update([
                'items_count' => $itemsCount,
                'total_price' => $total,
            ]);

            // очищаем корзину
            $cart->items()->delete();

            $order->load(['items.dessert', 'address']);

            return response()->json([
                'success' => true,
                'data' => $order,
            ], 201);
        });
    }

    // POST /orders/{id}/cancel
    public function cancel(Request $request, string $id)
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (!in_array($order->status, ['new', 'paid'])) {
            return response()->json([
                'success' => false,
                'error' => 'Этот заказ уже нельзя отменить',
            ], 422);
        }

        $order->update(['status' => 'canceled']);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }
}
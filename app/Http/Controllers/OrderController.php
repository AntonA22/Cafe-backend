<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\CakeDesign;
use App\Models\Cart;
use App\Models\Dessert;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const FREE_DELIVERY_THRESHOLD = 1200;
    private const STANDARD_DELIVERY_FEE = 149;

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
            'data' => OrderResource::collection($orders),
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
            'data' => new OrderResource($order),
        ]);
    }

    // POST /orders  (создать заказ из корзины)
    public function store(Request $request)
    {
        $data = $request->validate([
            'address_id'    => ['nullable', 'uuid'],
            'comment'       => ['nullable','string','max:500'],
            'payment_mode'  => ['required', 'string', Rule::in(Order::PAYMENT_MODES)],
            'delivery_mode' => ['required', 'string', Rule::in(Order::DELIVERY_MODES)],
            'leave_at_door' => ['sometimes','boolean'],
            'phone'         => ['required','string','max:50'],
            'custom_cake' => ['sometimes', 'array'],
            'custom_cake.design_id' => ['required_with:custom_cake', 'string', 'max:255'],
            'custom_cake.design_name' => ['required_with:custom_cake', 'string', 'max:255'],
            'custom_cake.weight_title' => ['required_with:custom_cake', 'string', 'max:50'],
            'custom_cake.weight_grams' => ['required_with:custom_cake', 'integer', 'min:1'],
            'custom_cake.inscription' => ['nullable', 'string', 'max:255'],
            'custom_cake.wishes' => ['nullable', 'string', 'max:500'],
            'custom_cake.filling' => ['nullable', 'string', 'max:1000'],
            'custom_cake.accent' => ['nullable', 'string', 'max:1000'],
            'custom_cake.composition' => ['nullable', 'string', 'max:2000'],
            'custom_cake.preview_image_base64' => ['nullable', 'string'],
        ]);

        $customCake = $data['custom_cake'] ?? null;
        $cart = $customCake ? null : $this->userCart($request)->load('items.dessert');

        if (!$customCake && $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'Корзина пустая',
            ], 422);
        }

        return DB::transaction(function () use ($request, $data, $cart, $customCake) {
            $itemsCount = 0;
            $subtotal = 0;
            $deliveryMode = $data['delivery_mode'];
            $address = $this->resolveOrderAddress($request, $deliveryMode, $data['address_id'] ?? null);
            $phone = trim($data['phone']);
            $leaveAtDoor = $deliveryMode === Order::DELIVERY_MODE_DELIVERY
                ? (bool) ($data['leave_at_door'] ?? false)
                : false;

            $order = Order::create([
                'user_id'     => $request->user()->id,
                'address_id'  => $address?->id,
                'status'      => Order::STATUS_NEW,
                'items_count' => 0,
                'subtotal_price' => 0,
                'delivery_fee' => 0,
                'total_price' => 0,
                'comment'     => $data['comment'] ?? null,
                'payment_mode' => $data['payment_mode'],
                'delivery_mode' => $deliveryMode,
                'leave_at_door' => $leaveAtDoor,
                'customer_phone' => $phone,
            ]);

            if ($customCake) {
                $dessert = $this->createCustomCakeDessert($customCake);
                $price = $this->customCakePrice($customCake);

                    OrderItem::create([
                        'order_id'   => $order->id,
                        'dessert_id' => $dessert->id,
                        'qty'        => 1,
                        'price'      => $price,
                        'sum'        => $price,
                ]);

                $itemsCount = 1;
                $subtotal = $price;
            } else {
                foreach ($cart->items as $item) {
                    $qty = (int) $item->qty;
                    $dessert = $item->dessert;

                    if (!$dessert instanceof Dessert || !$dessert->available) {
                        throw new HttpResponseException(response()->json([
                            'success' => false,
                            'error' => 'Один или несколько товаров в корзине больше недоступны для заказа.',
                        ], 409));
                    }

                    $price = $this->normalizeMoney($item->price);

                    $sum = $price * $qty;

                    OrderItem::create([
                        'order_id'   => $order->id,
                        'dessert_id' => $item->dessert_id,
                        'qty'        => $qty,
                        'price'      => $price,
                        'sum'        => $sum,
                    ]);

                    $itemsCount += $qty;
                    $subtotal += $sum;
                }
            }

            $deliveryFee = $this->deliveryFeeFor($subtotal, $deliveryMode);

            $order->update([
                'items_count' => $itemsCount,
                'subtotal_price' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total_price' => $subtotal + $deliveryFee,
            ]);

            if ($cart) {
                $cart->items()->delete();
            }

            $order->load(['items.dessert', 'address']);

            $user = $request->user();
            $phoneTakenByAnotherUser = User::query()
                ->where('phone', $phone)
                ->where('id', '!=', $user->id)
                ->exists();

            if (($user->phone ?? null) !== $phone && !$phoneTakenByAnotherUser) {
                $user->forceFill(['phone' => $phone])->save();
            }

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
            ], 201);
        });
    }

    // POST /orders/{id}/cancel
    public function cancel(Request $request, string $id)
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (!in_array($order->status, [Order::STATUS_NEW, Order::STATUS_PROCESSING], true)) {
            return response()->json([
                'success' => false,
                'error' => 'Этот заказ уже нельзя отменить',
            ], 422);
        }

        $order->update(['status' => Order::STATUS_CANCELLED]);
        $order->load(['items.dessert', 'address']);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
        ]);
    }

    private function normalizeMoney(mixed $value): int
    {
        return (int) round((float) $value);
    }

    private function customCakePrice(array $customCake): int
    {
        $design = CakeDesign::query()
            ->where('slug', $customCake['design_id'])
            ->where('available', true)
            ->first();

        if ($design) {
            return $this->normalizeMoney($design->price);
        }

        $pricePerKg = 0;
        if (!empty($customCake['weight_grams'])) {
            $pricePerKg = 2500;
        }

        return (int) round($pricePerKg * ((int) $customCake['weight_grams'] / 1000));
    }

    private function createCustomCakeDessert(array $customCake): Dessert
    {
        $inscription = trim((string) ($customCake['inscription'] ?? ''));
        $wishes = trim((string) ($customCake['wishes'] ?? ''));
        $designName = trim((string) $customCake['design_name']);
        $weightTitle = trim((string) $customCake['weight_title']);
        $price = $this->customCakePrice($customCake);
        $photos = [];

        if (!empty($customCake['preview_image_base64'])) {
            $photos[] = 'data:image/jpeg;base64,' . $customCake['preview_image_base64'];
        }

        $details = [
            "Дизайн: {$designName}",
            'Надпись: ' . ($inscription !== '' ? $inscription : 'без текста'),
            'Пожелания: ' . ($wishes !== '' ? $wishes : 'не указаны'),
            "Вес: {$weightTitle}",
            'Начинка: ' . trim((string) ($customCake['filling'] ?? '')),
            'Акцент: ' . trim((string) ($customCake['accent'] ?? '')),
        ];

        return Dessert::create([
            'name' => "Торт «{$designName}»",
            'category' => 'custom_cake',
            'description' => implode("\n", array_filter($details)),
            'composition' => trim((string) ($customCake['composition'] ?? '')),
            'price' => $price,
            'photos' => $photos,
            'available' => false,
            'weight' => ((int) $customCake['weight_grams']) / 1000,
            'calories' => null,
            'proteins' => null,
            'fats' => null,
            'carbohydrates' => null,
        ]);
    }

    private function deliveryFeeFor(int $subtotal, string $deliveryMode): int
    {
        if ($deliveryMode !== Order::DELIVERY_MODE_DELIVERY) {
            return 0;
        }

        return $subtotal >= self::FREE_DELIVERY_THRESHOLD
            ? 0
            : self::STANDARD_DELIVERY_FEE;
    }

    private function resolveOrderAddress(Request $request, string $deliveryMode, ?string $addressId): ?Address
    {
        if ($deliveryMode === Order::DELIVERY_MODE_PICKUP) {
            return null;
        }

        if (!$addressId) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'error' => 'Для доставки нужен адрес.',
            ], 422));
        }

        $address = Address::query()
            ->where('user_id', $request->user()->id)
            ->find($addressId);

        if (!$address) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'error' => 'Адрес доставки не найден.',
            ], 422));
        }

        return $address;
    }
}

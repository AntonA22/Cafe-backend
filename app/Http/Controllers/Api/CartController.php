<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartAddRequest;
use App\Http\Requests\CartSetQtyRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Dessert;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private function userCart(Request $request): Cart
    {
        return Cart::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);
    }

    public function show(Request $request)
    {
        $cart = $this->userCart($request)->load('items.dessert');

        return new CartResource($cart);
    }

    public function add(CartAddRequest $request)
    {
        $cart = $this->userCart($request);

        $dessert = Dessert::findOrFail($request->dessert_id);
        if (! $dessert->available || $dessert->archived) {
            return response()->json([
                'success' => false,
                'error' => 'Этот товар сейчас недоступен для заказа.',
            ], 409);
        }

        $item = $cart->items()
            ->where('dessert_id', $dessert->id)
            ->first();

        if ($item) {
            $item->qty += $request->qty;
            // цену можно НЕ менять, чтобы сохранять "цену на момент добавления"
            $item->save();
        } else {
            $cart->items()->create([
                'dessert_id' => $dessert->id,
                'qty' => $request->qty,
                'price' => $this->normalizeMoney($dessert->price), // фиксируем цену при первом добавлении
            ]);
        }

        return new CartResource($cart->load('items.dessert'));
    }

    public function setQty(CartSetQtyRequest $request, Dessert $dessert)
    {
        $cart = $this->userCart($request);

        if ((! $dessert->available || $dessert->archived) && $request->qty > 0) {
            return response()->json([
                'success' => false,
                'error' => 'Этот товар сейчас недоступен для заказа.',
            ], 409);
        }

        $item = $cart->items()
            ->where('dessert_id', $dessert->id)
            ->first();

        if (! $item) {
            if ($request->qty <= 0) {
                return new CartResource($cart->load('items.dessert'));
            }

            // если не было — можно создать
            $cart->items()->create([
                'dessert_id' => $dessert->id,
                'qty' => $request->qty,
                'price' => $this->normalizeMoney($dessert->price),
            ]);
        } else {
            if ($request->qty <= 0) {
                $item->delete();
            } else {
                $item->qty = $request->qty;
                $item->save();
            }
        }

        return new CartResource($cart->load('items.dessert'));
    }

    public function remove(Request $request, Dessert $dessert)
    {
        $cart = $this->userCart($request);

        $cart->items()
            ->where('dessert_id', $dessert->id)
            ->delete();

        return new CartResource($cart->load('items.dessert'));
    }

    public function clear(Request $request)
    {
        $cart = $this->userCart($request);

        $cart->items()->delete();

        return new CartResource($cart->load('items.dessert'));
    }

    private function normalizeMoney(mixed $value): int
    {
        return (int) round((float) $value);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use App\Http\Resources\UserAddressResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class UserAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderBy('is_default', 'desc')->get();
        return response()->json([
            'success' => true,
            'data' => UserAddressResource::collection($addresses)
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'required|string|max:10',
            'phone' => 'required|string|max:20',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $addressData = $validator->validated();

        if ($request->is_default) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create($addressData);

        return response()->json([
            'success' => true,
            'message' => 'Address created successfully',
            'data' => new UserAddressResource($address)
        ], 201);
    }

    public function show(Request $request, UserAddress $userAddress): JsonResponse
    {
        if ($userAddress->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Address not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserAddressResource($userAddress)
        ]);
    }

    public function update(Request $request, UserAddress $userAddress): JsonResponse
    {
        if ($userAddress->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Address not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'address' => 'string|max:255',
            'city' => 'string|max:100',
            'province' => 'string|max:100',
            'postal_code' => 'string|max:10',
            'phone' => 'string|max:20',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $addressData = $validator->validated();

        if ($request->is_default) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $userAddress->update($addressData);

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'data' => new UserAddressResource($userAddress)
        ]);
    }

    public function destroy(Request $request, UserAddress $userAddress): JsonResponse
    {
        if ($userAddress->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Address not found'], 404);
        }

        if ($userAddress->is_default) {
            return response()->json(['success' => false, 'message' => 'Cannot delete default address'], 400);
        }

        $userAddress->delete();

        return response()->json(['success' => true, 'message' => 'Address deleted successfully']);
    }

    public function setDefault(Request $request, UserAddress $userAddress): JsonResponse
    {
        if ($userAddress->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Address not found'], 404);
        }

        $request->user()->addresses()->update(['is_default' => false]);
        $userAddress->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Default address set successfully',
            'data' => new UserAddressResource($userAddress)
        ]);
    }
}

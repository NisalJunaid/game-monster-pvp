<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonsterRequest;
use App\Models\Monster;
use Illuminate\Http\JsonResponse;

class MonsterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Monster::query()->latest()->get(),
        ]);
    }

    public function store(StoreMonsterRequest $request): JsonResponse
    {
        $monster = Monster::query()->create($request->validated());

        return response()->json([
            'data' => $monster,
        ], 201);
    }
}

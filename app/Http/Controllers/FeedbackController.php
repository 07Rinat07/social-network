<?php

namespace App\Http\Controllers;

use App\Http\Requests\Feedback\StoreRequest;
use App\Models\FeedbackMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class FeedbackController extends Controller
{
    public function my(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        $feedback = FeedbackMessage::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return response()->json($feedback);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $feedback = FeedbackMessage::query()->create([
                'user_id' => $request->user()?->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'message' => $validated['message'],
                'status' => FeedbackMessage::STATUS_NEW,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Сервис обратной связи временно недоступен. Попробуйте позже.',
            ], 503);
        }

        return response()->json([
            'message' => 'Спасибо! Ваше сообщение отправлено администрации.',
            'data' => [
                'id' => $feedback->id,
                'status' => $feedback->status,
            ],
        ], 201);
    }
}

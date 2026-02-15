<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Solid Social API",
 *     version="1.0.0",
 *     description="API documentation for Solid Social Network SPA"
 * )
 *
 * @OA\Server(
 *     url="/",
 *     description="Current host"
 * )
 *
 * @OA\Tag(
 *     name="Public",
 *     description="Public endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Radio",
 *     description="Radio features"
 * )
 *
 * @OA\Tag(
 *     name="Chat",
 *     description="Chat features"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctumCookie",
 *     type="apiKey",
 *     in="cookie",
 *     name="laravel_session",
 *     description="Sanctum stateful session cookie"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="xsrfHeader",
 *     type="apiKey",
 *     in="header",
 *     name="X-XSRF-TOKEN",
 *     description="CSRF header for state-changing requests"
 * )
 */
class OpenApiSpec
{
    /**
     * @OA\Get(
     *     path="/api/site/home-content",
     *     operationId="getHomeContent",
     *     tags={"Public"},
     *     summary="Get localized content for home page",
     *     @OA\Response(
     *         response=200,
     *         description="Home content payload"
     *     )
     * )
     */
    public function getHomeContent(): void
    {
    }

    /**
     * @OA\Post(
     *     path="/api/feedback",
     *     operationId="storeFeedback",
     *     tags={"Public"},
     *     summary="Create feedback message",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","message"},
     *             @OA\Property(property="name", type="string", example="Alex"),
     *             @OA\Property(property="email", type="string", format="email", example="alex@example.com"),
     *             @OA\Property(property="message", type="string", example="Great project!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Feedback stored"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function storeFeedback(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/radio/stations",
     *     operationId="getRadioStations",
     *     tags={"Radio"},
     *     summary="Search radio stations",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="country", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="language", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="tag", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Parameter(name="offset", in="query", required=false, @OA\Schema(type="integer", minimum=0)),
     *     @OA\Response(
     *         response=200,
     *         description="List of stations"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getRadioStations(): void
    {
    }

    /**
     * @OA\Get(
     *     path="/api/chats/unread-summary",
     *     operationId="getChatUnreadSummary",
     *     tags={"Chat"},
     *     summary="Get unread chat counters",
     *     security={{"sanctumCookie":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Unread counters"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getChatUnreadSummary(): void
    {
    }
}

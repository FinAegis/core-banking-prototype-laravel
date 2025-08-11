<?php

namespace App\Http\Controllers\Api\V2;

/**
 * V2 API Documentation Schemas.
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="bearerAuth"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication and authorization"
 * )
 *
 * @OA\Tag(
 *     name="Accounts",
 *     description="Bank account management"
 * )
 *
 * @OA\Tag(
 *     name="Transactions",
 *     description="Transaction operations and history"
 * )
 *
 * @OA\Tag(
 *     name="Transfers",
 *     description="Money transfers between accounts"
 * )
 *
 * @OA\Tag(
 *     name="Baskets",
 *     description="Multi-asset basket operations"
 * )
 *
 * @OA\Tag(
 *     name="GCU",
 *     description="Global Currency Unit operations"
 * )
 *
 * @OA\Tag(
 *     name="Webhooks",
 *     description="Webhook management for real-time notifications"
 * )
 *
 * @OA\Tag(
 *     name="Assets",
 *     description="Asset and currency management"
 * )
 *
 * @OA\Tag(
 *     name="Exchange Rates",
 *     description="Currency exchange rate information"
 * )
 *
 * @OA\Schema(
 *     schema="V2Error",
 *     type="object",
 *     @OA\Property(property="message", type="string", example="An error occurred"),
 *     @OA\Property(property="errors", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="V2ValidationError",
 *     type="object",
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(property="errors", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="V2Pagination",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=10),
 *     @OA\Property(property="per_page", type="integer", example=20),
 *     @OA\Property(property="total", type="integer", example=200),
 *     @OA\Property(property="from", type="integer", example=1),
 *     @OA\Property(property="to", type="integer", example=20)
 * )
 */
class V2ApiSchemas
{
    // This class exists only for OpenAPI documentation schemas
}

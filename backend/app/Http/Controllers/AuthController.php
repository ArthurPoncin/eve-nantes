<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Inscription utilisateur',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', maxLength: 50, example: 'noctambule44'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'nuit@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Compte créé, token Sanctum retourné',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'token', type: 'string', example: '1|aBcD...'),
                    new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                ]),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation échouée (email déjà pris, mot de passe trop court…)',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only('username', 'email', 'password'));

        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Connexion utilisateur',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'nuit@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token Sanctum retourné',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'token', type: 'string', example: '1|aBcD...'),
                    new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                ]),
            ),
            new OA\Response(
                response: 401,
                description: 'Identifiants invalides',
                content: new OA\JsonContent(ref: '#/components/schemas/Error'),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation échouée',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'error' => 'Identifiants invalides',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Profil de l\'utilisateur connecté',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilisateur courant',
                content: new OA\JsonContent(ref: '#/components/schemas/User'),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Déconnexion (révoque le token courant)',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token révoqué',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Déconnecté'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté']);
    }
}

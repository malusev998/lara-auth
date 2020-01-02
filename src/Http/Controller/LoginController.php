<?php

namespace UonSoftware\LaraAuth\Http\Controllers;

use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use UonSoftware\RefreshTokens\Contracts\Storage as RefreshTokensStorage;
use UonSoftware\LaraAuth\Contracts\LoginContract;
use UonSoftware\LaraAuth\Http\Requests\LoginRequest;
use UonSoftware\LaraAuth\Exceptions\PasswordUpdateException;
use UonSoftware\RefreshTokens\Exceptions\InvalidRefreshToken;
use UonSoftware\RefreshTokens\Exceptions\RefreshTokenExpired;
use UonSoftware\RefreshTokens\Exceptions\RefreshTokenNotFound;
use UonSoftware\LaraAuth\Exceptions\EmailIsNotVerifiedException;
use UonSoftware\LaraAuth\Exceptions\InvalidCredentialsException;
use UonSoftware\LaraAuth\Http\Requests\RevokeRefreshTokenRequest;

class LoginController extends Controller
{
    /**
     * Login the user and return the Json Web Token
     *
     * @param  \UonSoftware\LaraAuth\Http\Requests\LoginRequest  $request
     *
     * @param  \UonSoftware\LaraAuth\Contracts\LoginContract  $loginService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request, LoginContract $loginService): ?JsonResponse
    {
        try {
            return response()->json($loginService->login($request->validated()));
        } catch (InvalidCredentialsException | PasswordUpdateException | EmailIsNotVerifiedException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (Throwable $e) {
            return response()->json(['message' => 'An error has occurred'], 500);
        }
    }

    public function revokeRefreshToken(RevokeRefreshTokenRequest $request, RefreshTokensStorage $storage)
    {
        try {
            $userPrimaryKey = config('refresh_token.user.id');
            $userId = $request->user()->{$userPrimaryKey};
            $refreshToken = $request->input('refresh_token');
            $isDeleted = $storage->revoke($refreshToken, $userId);

            if ($isDeleted === true) {
                return response()->json(null, 204);
            }
            return response()->json(['message' => 'Refresh token doesn\'t belong to you'], 401);
        } catch (InvalidRefreshToken $e) {
            return response()->json(['message' => 'Refresh token is invalid'], 403);
        } catch (RefreshTokenExpired | RefreshTokenNotFound $e) {
            return response()->json(['message' => 'Refresh token is already deleted due to expiration'], 404);
        } catch (Throwable $e) {
            return response()->json(['message' => 'An error has occurred'], 500);
        }
    }
}

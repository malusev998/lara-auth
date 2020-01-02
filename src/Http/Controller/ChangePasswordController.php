<?php

namespace UonSoftware\LaraAuth\Http\Controllers;

use Throwable as ThrowableAlias;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Contracts\Config\Repository as Config;
use UonSoftware\LaraAuth\Events\RequestNewPasswordEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use UonSoftware\LaraAuth\Http\Requests\NewPasswordRequest;
use UonSoftware\LaraAuth\Exceptions\NullReferenceException;
use UonSoftware\LaraAuth\Http\Requests\ChangePasswordRequest;
use UonSoftware\LaraAuth\Contracts\UpdateUserPasswordContract;

class ChangePasswordController extends Controller
{
    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function requestNewPassword(NewPasswordRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $userModel = $this->config->get('lara_auth.user_model');
        try {
            $userModel::query()
                ->where('email', '=', $email)
                ->firstOrFail();

            event(new RequestNewPasswordEvent($email));
            return new JsonResponse(['message' => 'Your email has been sent'], 200);
        } catch (ModelNotFoundException $e) {
            return new JsonResponse(['message' => "User with email {$email} is not found"], 404);
        }
    }

    public function changePassword(
        ChangePasswordRequest $request,
        UpdateUserPasswordContract $updateUserPassword
    ): JsonResponse {
        try {
            $updateUserPassword->updatePassword($request->user(), $request->input('password'));
        } catch (NullReferenceException $e) {
            return new JsonResponse(['message' => 'User not found'], 404);
        } catch (ThrowableAlias $e) {
            return new JsonResponse(['message' => 'An error has occurred'], 500);
        }

        return new JsonResponse(
            [
                'message' => 'Your password has been changed successfully',
            ]
        );
    }
}

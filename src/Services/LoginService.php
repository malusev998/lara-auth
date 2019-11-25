<?php

namespace UonSoftware\LaraAuth\Services;

use Closure;
use UonSoftware\LaraAuth\Events\LoginEvent;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use UonSoftware\RefreshTokens\Contracts\RefreshTokenGenerator;
use Tymon\JWTAuth\JWTAuth;
use UonSoftware\LaraAuth\Contracts\LoginContract;
use Illuminate\Contracts\Hashing\Hasher;
use UonSoftware\LaraAuth\Exceptions\PasswordUpdateException;
use UonSoftware\LaraAuth\Contracts\UpdateUserPasswordContract;
use UonSoftware\LaraAuth\Exceptions\InvalidCredentialsException;
use UonSoftware\LaraAuth\Exceptions\EmailIsNotVerifiedException;


/**
 * Class LoginService
 *
 * @package UonSoftware\LaraAuth\Services
 */
class LoginService implements LoginContract
{
    /**
     * @var \Illuminate\Contracts\Hashing\Hasher
     */
    private $hasher;
    
    /**
     * @var \UonSoftware\LaraAuth\Contracts\UpdateUserPasswordContract
     */
    private $passwordService;
    
    /**
     * @var \Tymon\JWTAuth\JWTAuth
     */
    private $jwtAuth;
    
    /**
     * @var \UonSoftware\RefreshTokens\Contracts\RefreshTokenGenerator
     */
    private $refreshTokenGenerator;
    
    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    private $config;
    
    private $eventDispatcher;
    
    /**
     * LoginService constructor.
     *
     * @param  \Illuminate\Contracts\Hashing\Hasher  $hasher
     * @param  \UonSoftware\LaraAuth\Contracts\UpdateUserPasswordContract  $passwordContract
     * @param  \Tymon\JWTAuth\JWTAuth  $jwtAuth
     * @param  RefreshTokenGenerator  $refreshTokenGenerator
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Contracts\Events\Dispatcher  $eventDispatcher
     */
    public function __construct(
        Hasher $hasher,
        UpdateUserPasswordContract $passwordContract,
        JWTAuth $jwtAuth,
        RefreshTokenGenerator $refreshTokenGenerator,
        Config $config,
        EventDispatcher $eventDispatcher
    ) {
        $this->hasher = $hasher;
        $this->passwordService = $passwordContract;
        $this->jwtAuth = $jwtAuth;
        $this->refreshTokenGenerator = $refreshTokenGenerator;
        $this->config = $config;
        $this->eventDispatcher = $eventDispatcher;
    }
    
    /**
     * @param  array  $login
     * @param  \Closure|null  $additionalChecks
     *
     * @return array
     * @throws \Throwable
     * @throws \UonSoftware\LaraAuth\Exceptions\EmailIsNotVerifiedException
     * @throws \UonSoftware\LaraAuth\Exceptions\InvalidCredentialsException
     * @throws \UonSoftware\LaraAuth\Exceptions\PasswordUpdateException
     * @throws \UonSoftware\RsaSigner\Exceptions\SignatureCorrupted
     */
    public function login(array $login, ?Closure $additionalChecks = null): array
    {
        $config = $this->config->get('lara_auth');
        
        $where = [];
        
        $passwordOnModel = $this->config->get('lara_auth.user.email.field_on_model');
        $passwordOnRequest = $this->config->get('lara_auth.user.email.field_from_request');
        
        
        foreach ($config['user.search'] as $search) {
            ['field' => $field, 'operator' => $operator] = $search;
            $where[] = [$field, $operator, $login[$field]];
        }
        
        /** @var \Illuminate\Auth\Authenticatable&\Illuminate\Database\Eloquent\Model&\Tymon\JWTAuth\Contracts\JWTSubject $user */
        $user = $config['user_model']::query()
            ->where($where)
            ->firstOrFail();
        
        if (!$this->hasher->check($login->{$passwordOnRequest}, $user->{$passwordOnModel})) {
            throw new InvalidCredentialsException();
        }
        
        // Check if hash is still good
        if ($this->hasher->needsRehash($user->{$passwordOnModel}) && !$this->passwordService->updatePassword($user,
                $login->{$passwordOnRequest})) {
            throw new PasswordUpdateException();
        }
        
        $emailVerificationField = $config['user.email_verification.field'];
        if ($config['user.email_verification.check'] === true && $user->{$emailVerificationField} === null) {
            throw new EmailIsNotVerifiedException();
        }
        
        if ($additionalChecks !== null) {
            $additionalChecks($user);
        }
        
        [1 => $refreshToken] = $this->refreshTokenGenerator->generateNewRefreshToken(null, $user->getAuthIdentifier());
        $userResource = $config['user_resource'];
        $this->eventDispatcher->dispatch(new LoginEvent($user));
        return [
            'user' => new $userResource($user),
            'auth' => [
                'token' => $this->jwtAuth->fromSubject($user),
                'refresh' => $refreshToken,
                'type' => 'Bearer',
            ],
        ];
    }
}
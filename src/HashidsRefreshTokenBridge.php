<?php
declare(strict_types = 1);

namespace UonSoftware\LaraAuth;


use Hashids\HashidsInterface;
use UonSoftware\RefreshTokens\Service\GetUserId;
use UonSoftware\RefreshTokens\Contracts\GetUserIdFromJwt;

class HashidsRefreshTokenBridge implements GetUserIdFromJwt
{
    protected $hashids;
    protected $getIdFromJwt;

    public function __construct(HashidsInterface $hashids)
    {
        $this->hashids = $hashids;
        $this->getIdFromJwt = new GetUserId();
    }

    /**
     * @inheritDoc
     */
    public function getId(string $jwt)
    {
        $id = $this->getIdFromJwt->getId($jwt);

        return $this->hashids->decodeHex($id);
    }
}

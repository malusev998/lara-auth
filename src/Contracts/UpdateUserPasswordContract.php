<?php


namespace UonSoftware\LaraAuth\Contracts;


use UonSoftware\LaraAuth\Exceptions\NullReferenceException;

/**
 * Interface UpdateUserPasswordContract
 *
 * @package UonSoftware\LaraAuth\Contracts
 */
interface UpdateUserPasswordContract
{
    /**
     * @throws \Throwable
     * @throws NullReferenceException
     *
     * @param integer|string $user
     * @param string         $newPassword
     *
     * @return bool
     */
    public function updatePassword($user, string $newPassword): bool;
}

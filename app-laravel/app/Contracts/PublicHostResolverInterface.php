<?php

namespace App\Contracts;

interface PublicHostResolverInterface
{
    /** @return list<string> */
    public function resolve(string $host): array;
}

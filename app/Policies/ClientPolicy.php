<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function delete(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }
}

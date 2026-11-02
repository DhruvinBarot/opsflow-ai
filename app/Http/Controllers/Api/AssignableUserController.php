<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AssignableUserController extends Controller
{
    /**
     * Operations staff a request can be assigned to (mirrors AssignOperationalRequestRequest's rule).
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('operations');

        // shortcut: unpaginated, fine for an ops team of tens; paginate or add search past a few hundred staff.
        return UserResource::collection(
            User::query()->whereIn('role', [Role::Manager, Role::Admin])->orderBy('name')->get()
        );
    }
}

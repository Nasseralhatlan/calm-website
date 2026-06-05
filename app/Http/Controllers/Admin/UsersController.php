<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function __construct(private readonly UserService $service) {}

    public function index(): View
    {
        return view('admin.users.index', ['users' => $this->service->paginate()]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->service->updateAsAdmin($user, $request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('User ":name" updated.', ['name' => $user->name ?: ($user->phone ?: $user->email)]));
    }
}

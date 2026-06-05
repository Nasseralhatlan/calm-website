<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateProfileRequest;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('profile.index', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request, UserService $userService): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $userService->update($user, $request->validated());

        return back()->with('status', __('Profile updated.'));
    }
}

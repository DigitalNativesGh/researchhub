<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public const ROLES = [
        'academic' => ['Academic/Lecturer', 'history_edu', 'Supervise theses, prepare research proposals and keep your own projects organised.'],
        'independent' => ['Independent Researcher', 'biotech', 'Get support with a study built on the literature you have collected.'],
        'student' => ['Student', 'person_search', 'Get support with your thesis from our research team, in clear stages.'],
        'industry' => ['Industry Practitioner', 'business_center', 'Ground applied research and reports in the published studies you rely on.'],
    ];

    public function show(): View
    {
        return view('onboarding.role', ['roles' => self::ROLES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(User::ROLES))]], ['role.required' => 'Select the role that fits you best.']);
        $request->user()->update($data);

        return redirect()->intended(route('dashboard'));
    }
}

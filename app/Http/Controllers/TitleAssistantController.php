<?php

namespace App\Http\Controllers;

use App\Enums\DegreeLevel;
use App\Services\Titles\TitleSuggester;
use App\Services\Titles\TitleSuggestionFailed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Help me find a title": the only place the platform uses AI. The researcher describes their area of interest and
 * gets about five suggested titles; they can pick one (and edit it on the details page), ask for more, or write
 * their own. Only the final title is saved, with whether it came from a suggestion.
 */
class TitleAssistantController extends Controller
{
    private const SESSION = 'title_assistant';

    public function show(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->guardWizard($request)) {
            return $redirect;
        }

        $state = $request->session()->get(self::SESSION, []);
        $wizard = $request->session()->get('wizard', []);
        $params = $state['params'] ?? [];

        return view('projects.title-assistant', [
            'degreeLevel' => $this->degreeLevel($request),
            'disciplines' => ProjectController::DISCIPLINES,
            'approaches' => TitleSuggester::APPROACHES,
            'values' => [
                'discipline' => old('discipline', $params['discipline'] ?? ($wizard['discipline'] ?? $request->user()->field_of_study ?? ProjectController::DISCIPLINES[0])),
                'topic' => old('topic', $params['topic'] ?? ''),
                'keywords' => old('keywords', $params['keywords'] ?? ''),
                'context' => old('context', $params['context'] ?? ''),
                'approach' => old('approach', $params['approach'] ?? ($wizard['research_type'] ?? 'not_sure')),
                'background' => old('background', $params['background'] ?? ''),
            ],
            'suggestions' => $state['suggestions'] ?? [],
            'latest' => $state['latest'] ?? 0,
        ]);
    }

    public function suggest(Request $request, TitleSuggester $suggester): RedirectResponse
    {
        if ($redirect = $this->guardWizard($request)) {
            return $redirect;
        }

        $params = $request->validate([
            'discipline' => ['required', Rule::in(ProjectController::DISCIPLINES)],
            'topic' => ['required', 'string', 'min:3', 'max:300'],
            'keywords' => ['nullable', 'string', 'max:300'],
            'context' => ['nullable', 'string', 'max:300'],
            'approach' => ['required', Rule::in(array_keys(TitleSuggester::APPROACHES))],
            'background' => ['nullable', 'string', 'max:3000'],
        ], ['topic.required' => 'Describe your broad topic or area of interest.'], ['topic' => 'topic']);

        $params = array_map(fn ($v) => is_string($v) ? trim(strip_tags($v)) : $v, $params);

        try {
            $titles = $suggester->suggest($request->user(), $params + ['degree_level' => $this->degreeLevel($request)->value]);
        } catch (TitleSuggestionFailed $e) {
            $request->session()->put(self::SESSION.'.params', $params);

            return redirect()->route('title-assistant')->with('error', $e->getMessage());
        }

        $request->session()->put(self::SESSION, ['params' => $params, 'suggestions' => $titles, 'latest' => 0]);

        return redirect()->to(route('title-assistant').'#suggestions');
    }

    /** "Suggest more": same inputs, without repeating titles already shown. */
    public function more(Request $request, TitleSuggester $suggester): RedirectResponse
    {
        if ($redirect = $this->guardWizard($request)) {
            return $redirect;
        }

        $state = $request->session()->get(self::SESSION, []);
        if (empty($state['params'])) {
            return redirect()->route('title-assistant');
        }

        $shown = array_column($state['suggestions'] ?? [], 'title');

        try {
            $titles = $suggester->suggest($request->user(), $state['params'] + ['degree_level' => $this->degreeLevel($request)->value], $shown);
        } catch (TitleSuggestionFailed $e) {
            return redirect()->to(route('title-assistant').'#suggestions')->with('error', $e->getMessage());
        }

        $state['latest'] = count($state['suggestions'] ?? []);
        $state['suggestions'] = array_merge($state['suggestions'] ?? [], $titles);
        $request->session()->put(self::SESSION, $state);

        return redirect()->to(route('title-assistant').'#suggestions');
    }

    /** Take a suggestion into the details step, where the researcher can still edit it. */
    public function choose(Request $request): RedirectResponse
    {
        $suggestions = $request->session()->get(self::SESSION.'.suggestions', []);
        $data = $request->validate(['choice' => ['required', 'integer', 'min:0', 'max:'.max(0, count($suggestions) - 1)]], ['choice.required' => 'Choose one of the suggested titles.']);
        abort_if($suggestions === [], 422, 'There are no suggestions to choose from.');

        $chosen = $suggestions[$data['choice']]['title'];
        $params = $request->session()->get(self::SESSION.'.params', []);
        $wizard = $request->session()->get('wizard', []);

        $request->session()->put('wizard', array_merge($wizard, [
            'title' => Str::limit($chosen, 300, ''),
            'suggested_title' => $chosen,
            'discipline' => $params['discipline'] ?? ($wizard['discipline'] ?? null),
            'research_type' => in_array($params['approach'] ?? null, ['quantitative', 'qualitative', 'mixed'], true) ? $params['approach'] : ($wizard['research_type'] ?? null),
        ]));

        return redirect()->route('projects.create')->with('status', 'Title added. You can edit it below before continuing.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION);

        return redirect()->route('title-assistant');
    }

    /** The assistant belongs to the details step: research type chosen and paid for first. */
    private function guardWizard(Request $request): ?RedirectResponse
    {
        $level = $this->degreeLevel($request);

        return match (true) {
            ! $level => redirect()->route('projects.new'),
            ! $request->user()->canStartProject($level) => redirect()->route('projects.payment'),
            default => null,
        };
    }

    private function degreeLevel(Request $request): ?DegreeLevel
    {
        return DegreeLevel::tryFrom((string) $request->session()->get('wizard.degree_level'));
    }
}

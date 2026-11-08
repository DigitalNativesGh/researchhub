# Prompts

The AI is used for one thing only: suggesting thesis titles (`title_suggestions.md`). It does not write any part
of a thesis.

- The front matter sets `version` (bump it when you change the prompt; it is logged with every call in
  `ai_calls.prompt`), `model` (`default` = `CLAUDE_MODEL`) and `max_tokens`.
- `# System` and `# User` mark the two parts of the request.
- `{{ name }}` inserts a variable supplied by `App\Services\Titles\TitleSuggester`. Arrays are inserted as JSON.
- The output format is enforced by `App\Ai\Schemas::titleSuggestions()`.

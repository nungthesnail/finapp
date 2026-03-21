<?php

return [
    'default_model' => env('AI_DEFAULT_MODEL', 'gpt-4.1-mini'),
    'free_trial_credit' => (float) env('AI_FREE_TRIAL_CREDIT', 100.0),
    'gateway' => env('AI_GATEWAY', 'mock'), // openai | mock
    'max_tool_loops' => (int) env('AI_MAX_TOOL_LOOPS', 6),
    'system_prompt' => env(
        'AI_SYSTEM_PROMPT',
        'You are FinWiseAi assistant. Help user with personal finance. Use tools for operations that change or read structured financial data. Never fabricate tool results.'
    ),
];

<?php

return [
    'enabled' => filter_var(env('RAG_ENABLED', false), FILTER_VALIDATE_BOOL),
    'history_turns' => (int) env('RAG_HISTORY_TURNS', 6),
    'top_k' => (int) env('RAG_TOP_K', 10),
    'final_top_k' => (int) env('RAG_FINAL_TOP_K', 5),
    'min_score' => (float) env('RAG_MIN_SCORE', 0.65),
    'message_max_length' => (int) env('RAG_MESSAGE_MAX_LENGTH', 4000),
    'namespaces' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('RAG_NAMESPACES', 'medical,drugs,guidelines'))
    ))),
];

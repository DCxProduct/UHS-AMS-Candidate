@include('errors.layout', [
    'code' => 403,
    'title' => __('errors.403.title', [], 'en'),
    'message' => __('errors.403.message', [], 'en'),
])

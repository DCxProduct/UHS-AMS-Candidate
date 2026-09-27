@include('errors.layout', [
    'code' => 500,
    'title' => __('errors.500.title', [], 'en'),
    'message' => __('errors.500.message', [], 'en'),
])

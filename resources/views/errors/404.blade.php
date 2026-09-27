@include('errors.layout', [
    'code' => 404,
    'title' => __('errors.404.title', [], 'en'),
    'message' => __('errors.404.message', [], 'en'),
])

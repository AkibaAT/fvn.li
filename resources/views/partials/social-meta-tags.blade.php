@php
    $metaTags = method_exists($page, 'getMetaTags') ? $page->getMetaTags() : [];
    $title = $metaTags['title'] ?? config('app.name');
    $description = $metaTags['description'] ?? config('app.description', '');
    $image = $metaTags['image'] ?? asset('favicon.ico');
    $url = url()->current();
@endphp


<meta property="og:title" content="{{ $title }}" />
<meta property="og:description" content="{{ $description }}" />
<meta property="og:image" content="{{ $image }}" />
<meta property="og:url" content="{{ $url }}" />
<meta property="og:type" content="website" />

<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $title }}" />
<meta name="twitter:description" content="{{ $description }}" />
<meta name="twitter:image" content="{{ $image }}" />

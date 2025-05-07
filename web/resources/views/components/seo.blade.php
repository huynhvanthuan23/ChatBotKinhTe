<!-- seo -->

@if (Request::segment(1) == '')
    <meta name="description" content="{{ InfoWeb()[7] }}@yield('description')">
    <meta property="og:description" content="{{ InfoWeb()[7] }}@yield('description')">
    <meta name="twitter:description" content="{{ InfoWeb()[7] }}@yield('description')">
@endif


@if (Request::segment(1) != '')
    <meta name="description" content="@yield('description')">
    <meta property="og:description" content="@yield('description')">
    <meta name="twitter:description" content="@yield('description')">
@endif



<meta property="og:image" content="{{secure_url('/file/img/logo') . '/' . InfoWeb()[0] }}" />
<meta itemprop="image" content="{{secure_url('/file/img/logo') . '/' . InfoWeb()[0] }}" />
<meta property="og:image:secure_url" content="{{secure_url('/file/img/logo') . '/' . InfoWeb()[0] }}" />



<meta name="robots" content="all">

<meta property="og:image:alt" content="{{ InfoWeb()[2] }}" />

<meta property="og:site_name" content="{{ InfoWeb()[2] }}" />
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ Request::fullUrl() }}" />

<meta name="twitter:card" content="summary">

<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta http-equiv="x-ua-compatible" content="ie=edge">

<meta name="twitter:site" content="{{ InfoWeb()[2] }}">

<meta name="twitter:title" content="@yield('title') - {{ InfoWeb()[2] }}">
<meta property="og:title" content="@yield('title') - {{ InfoWeb()[2] }}" />

<!-- seo -->
<title>@yield('title') - {{ InfoWeb()[2] }}</title>



<link rel="icon" type="image/x-icon" href="{{secure_url('/file/img/favicon') . '/' . InfoWeb()[1] }}">

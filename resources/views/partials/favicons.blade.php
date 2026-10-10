{{-- Browser tab, home-screen and PWA icons. The .ico covers older browsers;
     everything else picks the SVG. --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#780000">

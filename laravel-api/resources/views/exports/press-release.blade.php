<!DOCTYPE html>
<html lang="{{ $release->language ?? 'fr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $release->title }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 40px; }
        h1 { font-size: 24px; margin-bottom: 10px; }
        .meta { color: #666; font-size: 14px; margin-bottom: 20px; }
        .content { font-size: 16px; }
        .content h2 { font-size: 20px; margin-top: 30px; }
        .content p { margin-bottom: 15px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <h1>{{ $release->title }}</h1>
    <div class="meta">
        @if($release->published_at)
            {{ $release->published_at->format('d/m/Y') }}
        @else
            {{ $release->created_at->format('d/m/Y') }}
        @endif
    </div>
    @if($release->excerpt)
        <p><strong>{{ $release->excerpt }}</strong></p>
    @endif
    <div class="content">
        {!! $release->content_html !!}
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} SOS-Expat. Tous droits réservés.
    </div>
</body>
</html>

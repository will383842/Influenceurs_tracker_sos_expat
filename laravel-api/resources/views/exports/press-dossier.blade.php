<!DOCTYPE html>
<html lang="{{ $dossier->language ?? 'fr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $dossier->name }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 40px; }
        h1 { font-size: 28px; margin-bottom: 10px; }
        .description { font-size: 16px; color: #666; margin-bottom: 30px; }
        .item { margin-bottom: 40px; page-break-inside: avoid; }
        .item h2 { font-size: 22px; border-bottom: 2px solid #333; padding-bottom: 5px; }
        .item .content { font-size: 15px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <h1>{{ $dossier->name }}</h1>
    @if($dossier->description)
        <div class="description">{{ $dossier->description }}</div>
    @endif
    @foreach($items as $item)
        <div class="item">
            <h2>{{ $item->itemable->title ?? 'Sans titre' }}</h2>
            @if($item->itemable && $item->itemable->excerpt)
                <p><em>{{ $item->itemable->excerpt }}</em></p>
            @endif
            @if($item->itemable && $item->itemable->content_html)
                <div class="content">{!! $item->itemable->content_html !!}</div>
            @endif
        </div>
    @endforeach
    <div class="footer">
        &copy; {{ date('Y') }} SOS-Expat. Tous droits réservés.
    </div>
</body>
</html>

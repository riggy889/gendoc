{{--
    The HTML page sent to the PDF renderer. $styles is our own stylesheet and font CSS, never user
    input, so it is output unescaped; all document text is escaped inside documents.render.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document->type->title() }} {{ $document->number }}</title>
    <style>{!! $styles !!}</style>
</head>
<body style="margin: 0">
    @include('documents.render', ['isPreview' => false])
</body>
</html>

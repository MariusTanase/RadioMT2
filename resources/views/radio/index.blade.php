<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MT Radio</title>
</head>
<body>
    <ul>
        @foreach ($radios as $radio)
            <li>{{ $radio->title }}</li>
        @endforeach
    </ul>
</body>
</html>

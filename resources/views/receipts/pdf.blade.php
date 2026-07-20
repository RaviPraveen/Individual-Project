<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $data['invoice_no'] }}</title>
</head>
<body>
    @include('receipts._body', ['settings' => $settings, 'data' => $data])
</body>
</html>

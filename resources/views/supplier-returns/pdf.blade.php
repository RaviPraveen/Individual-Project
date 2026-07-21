<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Supplier Return #{{ $supplierReturn->id }}</title>
</head>
<body>
    @include('supplier-returns._body', ['settings' => $settings, 'supplierReturn' => $supplierReturn])
</body>
</html>

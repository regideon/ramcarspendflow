<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f6f7f8;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .email-wrapper {
            background: #fff;
            border-radius: 6px;
            padding: 20px;
            max-width: 700px;
            margin: 0 auto;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            font-size: 14px;
            line-height: 1.5;
        }

        p {
            margin: 10px 0;
        }

        .section {
            margin-top: 20px;
        }

        .section-title {
            font-weight: bold;
            font-size: 15px;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 10px;
            padding-bottom: 5px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .info-table td {
            padding: 6px 4px;
            vertical-align: top;
        }

        .info-table td:first-child {
            width: 35%;
            color: #6b7280;
            font-weight: 600;
        }

        .highlight {
            color: #dc2626;
            font-weight: bold;
        }

        .remarks {
            background: #fff8e1;
            border-left: 4px solid #fbbf24;
            padding: 10px;
            border-radius: 4px;
            font-size: 13px;
        }

        .cta-container {
            text-align: center;
            margin: 25px 0 15px;
        }

        .cta-button {
            background-color: #2563eb;
            color: #fff !important;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
        }

        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
    </style>
</head>

<body>
    @yield('content')
</body>

</html>

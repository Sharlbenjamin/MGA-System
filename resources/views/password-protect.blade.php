<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex justify-center items-center h-screen bg-gray-100">
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-bold mb-4">Enter Password to Access Filament</h2>

        @if ($errors->any())
            <p class="text-red-500">{{ $errors->first('password') }}</p>
        @endif

        <form method="POST" action="{{ route('password.submit') }}" class="space-y-4">
            @csrf
            <input type="password" name="password" placeholder="Enter Password"
                class="border p-2 w-full rounded">
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Submit</button>
        </form>
    </div>
</body>
</html>
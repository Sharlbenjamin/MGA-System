<x-filament-widgets::widget>
    <x-filament::card>
        <div class="space-y-4">
            @foreach($record->comments as $comment)
                <div class="border-b pb-4">
                    <div class="font-bold text-primary-600">{{ $comment->user->name }}</div>
                    <div class="prose max-w-none">{{ $comment->content }}</div>
                    <div class="text-sm text-gray-500">{{ $comment->created_at->format('M d, Y H:i') }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::card>
</x-filament-widgets::widget>

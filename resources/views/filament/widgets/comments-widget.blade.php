<x-filament::widget>
    <x-filament::card>
        @if($record)
            <div class="space-y-4">
                @forelse($this->getComments() as $comment)
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="font-medium text-gray-900">
                            {{ $comment->user->name }}
                        </div>
                        <div class="mt-1 text-sm text-gray-500">
                            {{ $comment->created_at->diffForHumans() }}
                        </div>
                        <div class="mt-2 text-gray-700">
                            {{ $comment->content }}
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500">
                        No comments yet.
                    </div>
                @endforelse
            </div>
        @else
            <div class="text-sm text-gray-500">
                Select a file to view comments.
            </div>
        @endif
    </x-filament::card>
</x-filament::widget>

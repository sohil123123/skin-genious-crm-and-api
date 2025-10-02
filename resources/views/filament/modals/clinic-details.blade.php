<div class="space-y-6">
    <!-- Header Section -->
    <div class="bg-gray-50 p-4 rounded-lg">
        <h3 class="text-lg font-semibold text-gray-900">Basic Information</h3>
    </div>

    <!-- Details Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Name -->
        <div>
            <label class="text-sm font-medium text-gray-500">Name</label>
            <p class="mt-1 text-sm text-gray-900">{{ $record->name ?? 'N/A' }}</p>
        </div>

        <!-- Email -->
        <div>
            <label class="text-sm font-medium text-gray-500">Email</label>
            <p class="mt-1 text-sm text-gray-900">{{ $record->email ?? 'N/A' }}</p>
        </div>

        <!-- Created At -->
        <div>
            <label class="text-sm font-medium text-gray-500">Created At</label>
            <p class="mt-1 text-sm text-gray-900">
                {{ $record->created_at?->format('M d, Y h:i A') ?? 'N/A' }}
            </p>
        </div>

        <!-- Updated At -->
        <div>
            <label class="text-sm font-medium text-gray-500">Updated At</label>
            <p class="mt-1 text-sm text-gray-900">
                {{ $record->updated_at?->format('M d, Y h:i A') ?? 'N/A' }}
            </p>
        </div>

        <!-- Add more fields as needed -->
        @if(isset($record->phone))
        <div>
            <label class="text-sm font-medium text-gray-500">Phone</label>
            <p class="mt-1 text-sm text-gray-900">{{ $record->phone }}</p>
        </div>
        @endif

        @if(isset($record->address))
        <div class="md:col-span-2">
            <label class="text-sm font-medium text-gray-500">Address</label>
            <p class="mt-1 text-sm text-gray-900">{{ $record->address }}</p>
        </div>
        @endif
    </div>

    <!-- Additional Sections -->
    @if(isset($record->description))
    <div class="bg-gray-50 p-4 rounded-lg">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Description</h3>
        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $record->description }}</p>
    </div>
    @endif
</div>

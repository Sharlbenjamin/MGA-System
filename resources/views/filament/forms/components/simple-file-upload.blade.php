@php
    $componentId = 'file-upload-' . uniqid();
    $componentName = 'file_upload';
@endphp

<div class="space-y-4">
    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors">
        <input 
            type="file" 
            id="{{ $componentId }}" 
            name="{{ $componentName }}"
            accept="{{ implode(',', $getAcceptedFileTypes()) }}"
            class="hidden"
            onchange="handleFileSelect(this)"
        >
        
        <div class="space-y-2">
            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            
            <div class="text-sm text-gray-600">
                <label for="{{ $componentId }}" class="cursor-pointer">
                    <span class="font-medium text-primary-600 hover:text-primary-500">Click to upload</span>
                    or drag and drop
                </label>
            </div>
            
            <p class="text-xs text-gray-500">
                Accepted file types: {{ implode(', ', $getAcceptedFileTypes()) }}<br>
                Maximum file size: {{ $getMaxFileSize() }}KB
            </p>
        </div>
    </div>
    
    <div id="{{ $componentId }}-progress" class="hidden">
        <div class="bg-gray-200 rounded-full h-2">
            <div class="bg-primary-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <p class="text-sm text-gray-600 mt-2">Uploading...</p>
    </div>
    
    <div id="{{ $componentId }}-success" class="hidden">
        <div class="bg-green-50 border border-green-200 rounded-md p-3">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">File uploaded successfully!</p>
                </div>
            </div>
        </div>
    </div>
    
    <div id="{{ $componentId }}-error" class="hidden">
        <div class="bg-red-50 border border-red-200 rounded-md p-3">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800" id="{{ $componentId }}-error-message">Upload failed!</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function handleFileSelect(input) {
    const file = input.files[0];
    if (!file) return;
    
    const componentId = '{{ $componentId }}';
    const progressDiv = document.getElementById(componentId + '-progress');
    const successDiv = document.getElementById(componentId + '-success');
    const errorDiv = document.getElementById(componentId + '-error');
    const progressBar = progressDiv.querySelector('.bg-primary-600');
    
    // Hide previous messages
    successDiv.classList.add('hidden');
    errorDiv.classList.add('hidden');
    
    // Show progress
    progressDiv.classList.remove('hidden');
    progressBar.style.width = '0%';
    
    // Simulate progress
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += Math.random() * 30;
        if (progress > 90) progress = 90;
        progressBar.style.width = progress + '%';
    }, 200);
    
    // Create FormData
    const formData = new FormData();
    formData.append('file', file);
    formData.append('document_type', '{{ $getDocumentType() }}');
    formData.append('directory', '{{ $getDirectory() }}');
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    
    // Upload file
    fetch('{{ route("filament.admin.resources.files.upload-document", $getRecord()->id) }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        }
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(progressInterval);
        progressBar.style.width = '100%';
        
        setTimeout(() => {
            progressDiv.classList.add('hidden');
            
            if (data.success) {
                successDiv.classList.remove('hidden');
                // Trigger a page refresh or update the document list
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                errorDiv.classList.remove('hidden');
                document.getElementById(componentId + '-error-message').textContent = data.error || 'Upload failed!';
            }
        }, 500);
    })
    .catch(error => {
        clearInterval(progressInterval);
        progressDiv.classList.add('hidden');
        errorDiv.classList.remove('hidden');
        document.getElementById(componentId + '-error-message').textContent = 'Upload failed: ' + error.message;
    });
}
</script>

@props(['inputName', 'label', 'value' => null, 'required' => false, 'accept' => '*/*'])

<div class="mb-3">
    <label for="{{ $inputName }}" class="form-label">{{ $label }}</label>
    
    <div class="input-group">
        <input type="text" class="form-control" id="{{ $inputName }}_display" 
               placeholder="Chọn file..." value="{{ $value ? basename($value) : '' }}" readonly>
        <input type="hidden" name="{{ $inputName }}" id="{{ $inputName }}" value="{{ $value }}">
        <button class="btn btn-outline-secondary" type="button" id="{{ $inputName }}_browse">Chọn</button>
        <button class="btn btn-outline-secondary" type="button" id="{{ $inputName }}_upload">Tải lên</button>
        @if($value)
            <button class="btn btn-outline-danger" type="button" id="{{ $inputName }}_clear">Xóa</button>
        @endif
    </div>
    
    @if($value)
        <div class="mt-2" id="{{ $inputName }}_preview">
            @if(Str::contains($value, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                <img src="{{ $value }}" class="img-thumbnail" style="max-height: 150px;" alt="Preview">
            @else
                <div class="alert alert-info">
                    <i class="fas fa-file"></i> {{ basename($value) }}
                </div>
            @endif
        </div>
    @else
        <div class="mt-2" id="{{ $inputName }}_preview" style="display: none;"></div>
    @endif
    
    @error($inputName)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

@once
@push('scripts')
<script>
    function setupMediaUploader(inputName) {
        const browseBtn = document.getElementById(`${inputName}_browse`);
        const uploadBtn = document.getElementById(`${inputName}_upload`);
        const clearBtn = document.getElementById(`${inputName}_clear`);
        const displayInput = document.getElementById(`${inputName}_display`);
        const hiddenInput = document.getElementById(`${inputName}`);
        const previewContainer = document.getElementById(`${inputName}_preview`);
        
        // Nút Chọn file từ media manager
        browseBtn.addEventListener('click', function() {
            openMediaManager(function(mediaId, mediaUrl) {
                displayInput.value = mediaUrl.split('/').pop();
                hiddenInput.value = mediaUrl;
                
                // Hiển thị preview
                if (mediaUrl.match(/\.(jpeg|jpg|gif|png|webp)$/i)) {
                    previewContainer.innerHTML = `<img src="${mediaUrl}" class="img-thumbnail" style="max-height: 150px;" alt="Preview">`;
                } else {
                    previewContainer.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-file"></i> ${mediaUrl.split('/').pop()}
                        </div>
                    `;
                }
                previewContainer.style.display = 'block';
                
                // Thêm nút xóa nếu chưa có
                if (!clearBtn) {
                    const newClearBtn = document.createElement('button');
                    newClearBtn.className = 'btn btn-outline-danger';
                    newClearBtn.type = 'button';
                    newClearBtn.id = `${inputName}_clear`;
                    newClearBtn.innerText = 'Xóa';
                    uploadBtn.after(newClearBtn);
                    
                    newClearBtn.addEventListener('click', function() {
                        displayInput.value = '';
                        hiddenInput.value = '';
                        previewContainer.innerHTML = '';
                        previewContainer.style.display = 'none';
                        this.remove();
                    });
                }
            });
        });
        
        // Nút Tải lên file mới
        uploadBtn.addEventListener('click', function() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '{{ $accept }}';
            
            input.onchange = function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('name', file.name.split('.')[0]);
                    formData.append('_token', '{{ csrf_token() }}');
                    
                    // Hiển thị loading
                    previewContainer.innerHTML = `
                        <div class="alert alert-info">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            Đang tải lên...
                        </div>
                    `;
                    previewContainer.style.display = 'block';
                    
                    // Upload file
                    fetch('{{ route("admin.media.store") }}', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayInput.value = data.media.file_name;
                            hiddenInput.value = data.url;
                            
                            // Hiển thị preview
                            if (data.url.match(/\.(jpeg|jpg|gif|png|webp)$/i)) {
                                previewContainer.innerHTML = `<img src="${data.url}" class="img-thumbnail" style="max-height: 150px;" alt="Preview">`;
                            } else {
                                previewContainer.innerHTML = `
                                    <div class="alert alert-info">
                                        <i class="fas fa-file"></i> ${data.media.file_name}
                                    </div>
                                `;
                            }
                            
                            // Thêm nút xóa nếu chưa có
                            if (!clearBtn) {
                                const newClearBtn = document.createElement('button');
                                newClearBtn.className = 'btn btn-outline-danger';
                                newClearBtn.type = 'button';
                                newClearBtn.id = `${inputName}_clear`;
                                newClearBtn.innerText = 'Xóa';
                                uploadBtn.after(newClearBtn);
                                
                                newClearBtn.addEventListener('click', function() {
                                    displayInput.value = '';
                                    hiddenInput.value = '';
                                    previewContainer.innerHTML = '';
                                    previewContainer.style.display = 'none';
                                    this.remove();
                                });
                            }
                        } else {
                            previewContainer.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle"></i> Lỗi: ${data.message || 'Không thể tải lên file.'}
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        previewContainer.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> Lỗi khi tải lên file. Vui lòng thử lại sau.
                            </div>
                        `;
                    });
                }
            };
            
            input.click();
        });
        
        // Nút Xóa (nếu có)
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                displayInput.value = '';
                hiddenInput.value = '';
                previewContainer.innerHTML = '';
                previewContainer.style.display = 'none';
                this.remove();
            });
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        setupMediaUploader('{{ $inputName }}');
    });
</script>
@endpush
@endonce

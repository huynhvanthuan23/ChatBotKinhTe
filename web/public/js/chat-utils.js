/**
 * Utility functions for chat application
 */

// Lấy danh sách document IDs từ URL parameter
function getDocIdsFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const docIds = urlParams.get('doc_ids');
    return docIds ? docIds.split(',').map(id => id.trim()) : [];
}

// Kiểm tra nếu đang chat với tài liệu
function isChatWithDocuments() {
    return getDocIdsFromUrl().length > 0;
}

// Tạo URL chat với tài liệu
function createChatWithDocumentsUrl(docIds) {
    if (!docIds || !docIds.length) return '/chat';
    return `/chat?doc_ids=${docIds.join(',')}`;
}

// Xử lý lỗi khi gọi API
function handleApiError(error, errorCallback) {
    console.error('API Error:', error);
    
    if (typeof errorCallback === 'function') {
        let errorMessage = 'Có lỗi xảy ra khi kết nối đến máy chủ';
        
        if (error.response) {
            // Lỗi từ server với status code
            if (error.response.status === 401 || error.response.status === 403) {
                errorMessage = 'Phiên làm việc đã hết hạn, vui lòng đăng nhập lại';
                // Redirect to login page after 2 seconds
                setTimeout(() => {
                    window.location.href = '/login';
                }, 2000);
            } else if (error.response.status === 429) {
                errorMessage = 'Quá nhiều yêu cầu, vui lòng thử lại sau ít phút';
            } else if (error.response.status >= 500) {
                errorMessage = 'Máy chủ gặp sự cố, vui lòng thử lại sau';
            }
        } else if (error.request) {
            // Không nhận được response
            errorMessage = 'Không thể kết nối đến máy chủ, vui lòng kiểm tra kết nối mạng';
        }
        
        errorCallback(errorMessage);
    }
} 
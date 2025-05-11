/**
 * Citation Highlight Plugin
 * Tự động highlight đoạn trích dẫn khi xem tài liệu text
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('[CITATION-HIGHLIGHT] Script loaded');
    
    // Kiểm tra xem đây có phải là trang xem tài liệu TXT không
    // Nếu có thẻ embed PDF hoặc đây là iframe cho DOCX, không xử lý
    if (document.querySelector('embed[type="application/pdf"]') || 
        window.location.pathname.includes('/view-word') ||
        document.querySelector('.phpword-content')) {
        console.log('[CITATION-HIGHLIGHT] Không phải là tài liệu văn bản, bỏ qua');
        return;
    }
    
    // Lấy tham số từ URL
    const urlParams = new URLSearchParams(window.location.search);
    const highlightIndex = urlParams.get('highlight');
    const page = urlParams.get('page');
    
    if (highlightIndex !== null) {
        console.log('[CITATION-HIGHLIGHT] Xử lý tham số trích dẫn: page=' + page + ', highlight=' + highlightIndex);
        
        // Tạo thông báo trích dẫn
        createCitationBanner(page, highlightIndex);
        
        // Đối với file .txt, hiện tại không xử lý theo page mà chỉ quan tâm đến đoạn văn
        processTextCitation(highlightIndex);
    }
    
    /**
     * Tạo banner thông báo đang xem trích dẫn
     * @param {string} page - Số trang (hoặc vị trí) của trích dẫn
     * @param {string} highlightIndex - Chỉ số đoạn cần highlight
     */
    function createCitationBanner(page, highlightIndex) {
        // Tìm container một cách linh hoạt hơn
        const contentContainer = document.querySelector('.card-body') || document.querySelector('.container') || document.body;
        if (!contentContainer) {
            console.error('[CITATION-HIGHLIGHT] Không tìm thấy container phù hợp để chèn banner');
            return;
        }
        
        // Chỉ thêm thông báo nếu chưa có
        if (document.querySelector('.citation-banner')) return;
        
        const citationBanner = document.createElement('div');
        citationBanner.className = 'alert alert-primary alert-dismissible fade show mb-3 citation-banner';
        citationBanner.role = 'alert';
        
        const message = `
            <div class="d-flex align-items-center">
                <i class="fas fa-quote-left fs-4 me-3 text-primary"></i>
                <div>
                    <h5 class="mb-1 fw-bold">Đang xem trích dẫn từ tài liệu văn bản</h5>
                    <p class="mb-0">Đoạn văn số ${highlightIndex} đã được đánh dấu trong tài liệu.</p>
                </div>
            </div>
        `;
        
        citationBanner.innerHTML = message + `
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Đảm bảo insertBefore không bị lỗi
        try {
            if (contentContainer.firstChild) {
                contentContainer.insertBefore(citationBanner, contentContainer.firstChild);
            } else {
                contentContainer.appendChild(citationBanner);
            }
            console.log('[CITATION-HIGHLIGHT] Đã thêm banner thông báo trích dẫn');
        } catch (error) {
            console.error('[CITATION-HIGHLIGHT] Lỗi khi thêm banner: ', error);
            // Fallback method
            try {
                contentContainer.prepend(citationBanner);
            } catch (e) {
                console.error('[CITATION-HIGHLIGHT] Cả insertBefore và prepend đều lỗi: ', e);
            }
        }
    }
    
    /**
     * Xử lý highlight cho file .txt
     * @param {number} paragraphIndex - Chỉ số đoạn văn cần highlight
     */
    function processTextCitation(paragraphIndex) {
        // Tìm container chứa nội dung text
        const textContainer = document.querySelector('.document-content');
        
        if (!textContainer) {
            console.error('[CITATION-HIGHLIGHT] Không tìm thấy container nội dung văn bản');
            return;
        }
        
        // Tìm tất cả các đoạn văn trong container
        let paragraphs = textContainer.querySelectorAll('p');
        
        // Nếu không có thẻ p, thử tìm các đoạn được ngăn cách bằng thẻ div hoặc br
        if (paragraphs.length === 0) {
            // Phân tách nội dung thành các đoạn văn dựa trên thẻ div
            paragraphs = textContainer.querySelectorAll('div');
            
            // Nếu vẫn không có div, xử lý toàn bộ text như một đối tượng
            if (paragraphs.length === 0) {
                console.log('[CITATION-HIGHLIGHT] Đã tìm thấy nội dung văn bản');
                
                try {
                    // Phương án cuối cùng: Tách văn bản dựa trên các dòng trống
                    const content = textContainer.textContent || textContainer.innerText || '';
                    const paragraphsArray = content.split(/\n\s*\n/);
                    
                    if (paragraphsArray.length === 0) {
                        console.warn('[CITATION-HIGHLIGHT] Không thể tách nội dung thành các đoạn');
                        return;
                    }
                    
                    // Xóa nội dung hiện tại
                    const originalContent = textContainer.innerHTML;
                    textContainer.innerHTML = '';
                    
                    // Tạo lại các đoạn văn
                    let highlightedElement = null;
                    
                    paragraphsArray.forEach((text, index) => {
                        const p = document.createElement('p');
                        p.textContent = text.trim();
                        
                        // Đánh dấu đoạn cần highlight
                        if (index === parseInt(paragraphIndex)) {
                            p.classList.add('citation-highlight');
                            highlightedElement = p;
                        }
                        
                        textContainer.appendChild(p);
                    });
                    
                    // Cuộn đến đoạn được highlight
                    if (highlightedElement) {
                        setTimeout(() => {
                            try {
                                highlightedElement.scrollIntoView({ 
                                    behavior: 'smooth', 
                                    block: 'center' 
                                });
                                console.log('[CITATION-HIGHLIGHT] Đã cuộn đến đoạn văn được highlight');
                            } catch (error) {
                                console.error('[CITATION-HIGHLIGHT] Lỗi khi cuộn đến đoạn highlight: ', error);
                            }
                        }, 500);
                    }
                } catch (error) {
                    console.error('[CITATION-HIGHLIGHT] Lỗi khi xử lý nội dung văn bản: ', error);
                    // Khôi phục nội dung gốc nếu có lỗi
                    if (typeof originalContent !== 'undefined') {
                        textContainer.innerHTML = originalContent;
                    }
                }
                
                return;
            }
        }
        
        console.log('[CITATION-HIGHLIGHT] Tìm thấy ' + paragraphs.length + ' đoạn văn bản');
        
        try {
            // Convert indexes to integers
            const index = parseInt(paragraphIndex);
            
            // Highlight đoạn văn tương ứng
            if (index >= 0 && index < paragraphs.length) {
                paragraphs[index].classList.add('citation-highlight');
                
                // Cuộn đến đoạn được highlight
                setTimeout(() => {
                    try {
                        paragraphs[index].scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        console.log('[CITATION-HIGHLIGHT] Đã highlight và cuộn đến đoạn văn thứ ' + index);
                    } catch (error) {
                        console.error('[CITATION-HIGHLIGHT] Lỗi khi cuộn đến đoạn highlight: ', error);
                    }
                }, 500);
            } else {
                console.warn('[CITATION-HIGHLIGHT] Chỉ số đoạn văn không hợp lệ: ' + index + ', tổng số đoạn: ' + paragraphs.length);
            }
        } catch (error) {
            console.error('[CITATION-HIGHLIGHT] Lỗi khi xử lý đoạn văn: ', error);
        }
    }
});

// Thêm CSS cho highlight
(function() {
    // Kiểm tra xem đây có phải là trang xem tài liệu TXT không
    if (document.querySelector('embed[type="application/pdf"]') || 
        window.location.pathname.includes('/view-word') ||
        document.querySelector('.phpword-content')) {
        // Không thêm CSS nếu không phải tài liệu TXT
        return;
    }
    
    try {
        const style = document.createElement('style');
        style.textContent = `
            .citation-highlight {
                background-color: #ffff90 !important;
                padding: 10px !important;
                border-radius: 4px !important;
                border-left: 4px solid #ffd700 !important;
                margin: 10px 0 !important;
                transition: background-color 0.3s ease !important;
                animation: highlight-pulse 2s infinite !important;
            }
            
            @keyframes highlight-pulse {
                0% { background-color: #ffff90; }
                50% { background-color: #ffffb8; }
                100% { background-color: #ffff90; }
            }
        `;
        document.head.appendChild(style);
        console.log('[CITATION-HIGHLIGHT] Đã thêm CSS styles');
    } catch (error) {
        console.error('[CITATION-HIGHLIGHT] Lỗi khi thêm CSS: ', error);
    }
})(); 
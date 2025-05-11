/**
 * PDF Citation Handler
 * Xử lý điều hướng đến trang PDF khi click vào trích dẫn
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('[PDF-CITATION] Script loaded');
    
    // Kiểm tra xem có phải là trang xem PDF không
    const pdfEmbed = document.querySelector('embed[type="application/pdf"]');
    if (!pdfEmbed) {
        console.log('[PDF-CITATION] Không phải trang PDF, bỏ qua');
        return;
    }
    
    // Lấy tham số từ URL
    const urlParams = new URLSearchParams(window.location.search);
    const page = urlParams.get('page');
    
    if (page) {
        console.log('[PDF-CITATION] Xử lý tham số trích dẫn: page=' + page);
        
        // Đợi một chút để PDF được tải
        setTimeout(function() {
            // Xử lý PDF embed để đi đến trang
            processPdfCitation(pdfEmbed, page);
            
            // Hiển thị thông báo trích dẫn
            createCitationBanner(page);
        }, 300);
    }
    
    /**
     * Xử lý citation cho PDF
     */
    function processPdfCitation(embedElement, page) {
        try {
            // Đảm bảo PDF hiển thị đúng trang
            let currentSrc = embedElement.src;
            
            // Loại bỏ #page=X cũ nếu có
            if (currentSrc.includes('#page=')) {
                currentSrc = currentSrc.split('#')[0];
            }
            
            // Thêm #page=X mới
            embedElement.src = currentSrc + '#page=' + page;
            console.log('[PDF-CITATION] Đã chuyển PDF đến trang ' + page);
        } catch (error) {
            console.error('[PDF-CITATION] Lỗi khi chuyển trang PDF:', error);
        }
    }
    
    /**
     * Tạo banner thông báo citation
     */
    function createCitationBanner(page) {
        try {
            // Tìm container phù hợp
            const contentContainer = document.querySelector('.card-body') || 
                                   document.querySelector('.container');
            
            if (!contentContainer) {
                console.error('[PDF-CITATION] Không tìm thấy container phù hợp');
                return;
            }
            
            // Kiểm tra nếu banner đã tồn tại
            if (document.querySelector('.citation-banner')) return;
            
            const citationBanner = document.createElement('div');
            citationBanner.className = 'alert alert-info alert-dismissible fade show mb-3 citation-banner';
            citationBanner.role = 'alert';
            
            const message = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-file-pdf fs-4 me-3 text-danger"></i>
                    <div>
                        <h5 class="mb-1 fw-bold">Đang xem trích dẫn từ tài liệu PDF</h5>
                        <p class="mb-0">Đã chuyển đến trang ${page} của tài liệu.</p>
                    </div>
                </div>
            `;
            
            citationBanner.innerHTML = message + `
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            // Thêm banner vào DOM
            // Phương thức 1: insertAdjacentElement
            try {
                contentContainer.insertAdjacentElement('afterbegin', citationBanner);
                console.log('[PDF-CITATION] Đã thêm banner với insertAdjacentElement');
                return;
            } catch (error) {
                console.error('[PDF-CITATION] Lỗi khi thêm banner với insertAdjacentElement:', error);
            }
            
            // Phương thức 2: prepend
            try {
                contentContainer.prepend(citationBanner);
                console.log('[PDF-CITATION] Đã thêm banner với prepend');
                return;
            } catch (error) {
                console.error('[PDF-CITATION] Lỗi khi thêm banner với prepend:', error);
            }
            
            // Phương thức 3: insertBefore
            try {
                if (contentContainer.firstChild) {
                    contentContainer.insertBefore(citationBanner, contentContainer.firstChild);
                    console.log('[PDF-CITATION] Đã thêm banner với insertBefore');
                    return;
                }
            } catch (error) {
                console.error('[PDF-CITATION] Lỗi khi thêm banner với insertBefore:', error);
            }
            
            // Phương thức 4: appendChild
            try {
                contentContainer.appendChild(citationBanner);
                console.log('[PDF-CITATION] Đã thêm banner với appendChild');
                return;
            } catch (error) {
                console.error('[PDF-CITATION] Lỗi khi thêm banner với appendChild:', error);
            }
        } catch (mainError) {
            console.error('[PDF-CITATION] Lỗi chính khi tạo banner:', mainError);
        }
    }
}); 
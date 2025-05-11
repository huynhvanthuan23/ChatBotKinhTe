/**
 * DOCX Citation Highlight Plugin
 * Tự động highlight đoạn trích dẫn khi xem tài liệu DOCX đã chuyển sang HTML
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('[DOCX-CITATION] Script loaded');
    
    // Lấy tham số từ URL
    const urlParams = new URLSearchParams(window.location.search);
    const highlightIndex = urlParams.get('highlight');
    const page = urlParams.get('page');
    const citationText = urlParams.get('citation_text');
    
    if (highlightIndex !== null || citationText) {
        console.log('[DOCX-CITATION] Xử lý tham số trích dẫn: page=' + page + ', highlight=' + highlightIndex + ', text=' + (citationText ? 'có' : 'không'));
        
        // Xử lý trích dẫn DOCX
        setTimeout(function() {
            // Tạo thông báo trích dẫn (đợi DOM tải xong)
            createCitationBanner(page, highlightIndex);
            
            // Xử lý highlight (đợi nội dung tài liệu tải xong)
            if (citationText) {
                processDocxCitationByText(decodeURIComponent(citationText));
            } else {
                processDocxCitation(highlightIndex);
            }
        }, 500);
    }
    
    /**
     * Tạo banner thông báo đang xem trích dẫn
     * @param {string} page - Số trang (hoặc vị trí) của trích dẫn
     * @param {string} highlightIndex - Chỉ số đoạn cần highlight
     */
    function createCitationBanner(page, highlightIndex) {
        // Tìm container một cách linh hoạt hơn
        const contentContainer = document.querySelector('.card-body') || document.querySelector('.container');
        if (!contentContainer) {
            console.error('[DOCX-CITATION] Không tìm thấy container phù hợp để chèn banner');
            return;
        }
        
        // Chỉ thêm thông báo nếu chưa có
        if (document.querySelector('.citation-banner')) return;
        
        const citationBanner = document.createElement('div');
        citationBanner.className = 'alert alert-primary alert-dismissible fade show mb-3 citation-banner';
        citationBanner.role = 'alert';
        
        const message = `
            <div class="d-flex align-items-center">
                <i class="fas fa-file-word fs-4 me-3 text-primary"></i>
                <div>
                    <h5 class="mb-1 fw-bold">Đang xem trích dẫn từ tài liệu Word</h5>
                    <p class="mb-0">${highlightIndex !== null ? `Đoạn văn số ${highlightIndex}` : 'Đoạn văn bản'} đã được đánh dấu trong tài liệu.</p>
                </div>
            </div>
        `;
        
        citationBanner.innerHTML = message + `
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Thêm vào DOM
        try {
            contentContainer.insertAdjacentElement('afterbegin', citationBanner);
            console.log('[DOCX-CITATION] Đã thêm banner thông báo trích dẫn');
        } catch (error) {
            console.error('[DOCX-CITATION] Lỗi khi thêm banner với insertAdjacentElement: ', error);
            
            // Fallback 1: prepend
            try {
                contentContainer.prepend(citationBanner);
                console.log('[DOCX-CITATION] Đã thêm banner với prepend');
            } catch (error2) {
                console.error('[DOCX-CITATION] Lỗi khi thêm banner với prepend: ', error2);
                
                // Fallback 2: appendChild
                try {
                    contentContainer.appendChild(citationBanner);
                    console.log('[DOCX-CITATION] Đã thêm banner với appendChild');
                } catch (error3) {
                    console.error('[DOCX-CITATION] Không thể thêm banner: ', error3);
                }
            }
        }
    }
    
    /**
     * Xử lý highlight cho file DOCX theo chỉ số đoạn văn
     * @param {number} paragraphIndex - Chỉ số đoạn văn cần highlight
     */
    function processDocxCitation(paragraphIndex) {
        // Tìm container chứa nội dung DOCX
        const docxContainer = findDocxContainer();
        
        if (!docxContainer) {
            console.error('[DOCX-CITATION] Không tìm thấy container nội dung văn bản');
            return;
        }
        
        // Tìm tất cả các đoạn văn trong container (thường là các thẻ p hoặc div)
        let paragraphs = docxContainer.querySelectorAll('p');
        
        // Nếu không có thẻ p, thử tìm các đoạn được ngăn cách bằng thẻ div
        if (paragraphs.length === 0) {
            paragraphs = docxContainer.querySelectorAll('div');
        }
        
        console.log('[DOCX-CITATION] Tìm thấy ' + paragraphs.length + ' đoạn văn bản');
        
        try {
            // Convert indexe sang số
            const index = parseInt(paragraphIndex);
            
            // Highlight đoạn văn tương ứng
            if (index >= 0 && index < paragraphs.length) {
                // Thêm class highlight
                paragraphs[index].classList.add('docx-citation-highlight');
                
                // Cuộn đến đoạn được highlight
                setTimeout(() => {
                    try {
                        paragraphs[index].scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        console.log('[DOCX-CITATION] Đã highlight và cuộn đến đoạn văn thứ ' + index);
                    } catch (error) {
                        console.error('[DOCX-CITATION] Lỗi khi cuộn đến đoạn highlight: ', error);
                    }
                }, 500);
            } else {
                console.warn('[DOCX-CITATION] Chỉ số đoạn văn không hợp lệ: ' + index + ', tổng số đoạn: ' + paragraphs.length);
            }
        } catch (error) {
            console.error('[DOCX-CITATION] Lỗi khi xử lý đoạn văn: ', error);
        }
    }
    
    /**
     * Xử lý highlight cho file DOCX theo nội dung văn bản
     * @param {string} text - Đoạn văn bản cần tìm và highlight
     */
    function processDocxCitationByText(text) {
        if (!text || text.length < 10) {
            console.error('[DOCX-CITATION] Văn bản tìm kiếm quá ngắn');
            return;
        }
        
        // Tìm container chứa nội dung DOCX
        const docxContainer = findDocxContainer();
        
        if (!docxContainer) {
            console.error('[DOCX-CITATION] Không tìm thấy container nội dung văn bản');
            return;
        }
        
        console.log('[DOCX-CITATION] Tìm kiếm văn bản: ' + text.substring(0, 50) + '...');
        
        try {
            // Tìm phần tử chứa đoạn văn bản cần highlight
            const allElements = Array.from(docxContainer.querySelectorAll('p, div, span'));
            let bestMatch = null;
            let highestScore = 0;
            
            // Đối với từng phần tử, tính toán độ tương đồng với đoạn văn bản cần tìm
            for (const element of allElements) {
                const elementText = element.textContent || element.innerText;
                
                // Nếu phần tử không có văn bản hoặc quá ngắn, bỏ qua
                if (!elementText || elementText.length < 10) continue;
                
                // Tính độ tương đồng giữa đoạn văn bản cần tìm và văn bản của phần tử
                const similarity = calculateSimilarity(text, elementText);
                
                // Nếu độ tương đồng cao hơn ngưỡng và cao hơn phần tử tốt nhất hiện tại, cập nhật
                if (similarity > 0.5 && similarity > highestScore) {
                    highestScore = similarity;
                    bestMatch = element;
                }
                
                // Nếu đoạn văn bản chứa chính xác chuỗi cần tìm, ưu tiên chọn nó
                if (elementText.includes(text)) {
                    bestMatch = element;
                    break;
                }
            }
            
            // Nếu tìm thấy phần tử phù hợp, highlight và cuộn đến nó
            if (bestMatch) {
                console.log('[DOCX-CITATION] Đã tìm thấy phần tử phù hợp với độ tương đồng: ' + highestScore);
                
                // Thêm class highlight
                bestMatch.classList.add('docx-citation-highlight');
                
                // Cuộn đến phần tử được highlight
                setTimeout(() => {
                    try {
                        bestMatch.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        console.log('[DOCX-CITATION] Đã highlight và cuộn đến phần tử phù hợp');
                    } catch (error) {
                        console.error('[DOCX-CITATION] Lỗi khi cuộn đến phần tử highlight: ', error);
                    }
                }, 500);
            } else {
                console.warn('[DOCX-CITATION] Không tìm thấy phần tử phù hợp với đoạn văn bản');
                
                // Nếu không tìm thấy phần tử phù hợp, tìm kiếm đoạn văn ngắn hơn
                const shorterText = text.split(' ').slice(0, 10).join(' ');
                console.log('[DOCX-CITATION] Thử tìm kiếm với đoạn văn ngắn hơn: ' + shorterText);
                
                // Tìm phần tử chứa đoạn văn bản ngắn hơn
                for (const element of allElements) {
                    const elementText = element.textContent || element.innerText;
                    
                    // Nếu phần tử chứa đoạn văn bản ngắn hơn, highlight và cuộn đến nó
                    if (elementText.includes(shorterText)) {
                        console.log('[DOCX-CITATION] Đã tìm thấy phần tử phù hợp với đoạn văn ngắn hơn');
                        
                        // Thêm class highlight
                        element.classList.add('docx-citation-highlight');
                        
                        // Cuộn đến phần tử được highlight
                        setTimeout(() => {
                            try {
                                element.scrollIntoView({ 
                                    behavior: 'smooth', 
                                    block: 'center' 
                                });
                                console.log('[DOCX-CITATION] Đã highlight và cuộn đến phần tử phù hợp');
                            } catch (error) {
                                console.error('[DOCX-CITATION] Lỗi khi cuộn đến phần tử highlight: ', error);
                            }
                        }, 500);
                        return;
                    }
                }
                
                console.error('[DOCX-CITATION] Không tìm thấy phần tử phù hợp với đoạn văn bản');
            }
        } catch (error) {
            console.error('[DOCX-CITATION] Lỗi khi xử lý trích dẫn theo văn bản: ', error);
        }
    }
    
    /**
     * Tìm container chứa nội dung DOCX
     * @returns {Element|null} Container chứa nội dung DOCX
     */
    function findDocxContainer() {
        // Thử các selector phổ biến
        const selectors = [
            '.document-content',
            '.docx-content',
            '.word-content',
            '.phpword-content',
            '.word-document',
            '.p-4.p-lg-5'
        ];
        
        for (const selector of selectors) {
            const container = document.querySelector(selector);
            if (container) {
                console.log('[DOCX-CITATION] Đã tìm thấy container nội dung DOCX với selector: ' + selector);
                return container;
            }
        }
        
        // Không tìm thấy container phù hợp, thử tìm div chứa nhiều thẻ p
        const divs = document.querySelectorAll('div');
        let bestContainer = null;
        let maxPCount = 0;
        
        for (const div of divs) {
            const pCount = div.querySelectorAll('p').length;
            if (pCount > maxPCount) {
                maxPCount = pCount;
                bestContainer = div;
            }
        }
        
        if (bestContainer && maxPCount > 5) {
            console.log('[DOCX-CITATION] Đã tìm thấy container nội dung DOCX dựa vào số lượng đoạn văn: ' + maxPCount);
            return bestContainer;
        }
        
        // Không tìm thấy container phù hợp
        console.error('[DOCX-CITATION] Không tìm thấy container nội dung DOCX');
        return null;
    }
    
    /**
     * Tính toán độ tương đồng giữa hai chuỗi
     * @param {string} a - Chuỗi thứ nhất
     * @param {string} b - Chuỗi thứ hai
     * @returns {number} - Độ tương đồng từ 0 đến 1
     */
    function calculateSimilarity(a, b) {
        // Đổi cả hai chuỗi về chữ thường và loại bỏ các ký tự đặc biệt
        a = a.toLowerCase().replace(/[^\w\s]/g, '');
        b = b.toLowerCase().replace(/[^\w\s]/g, '');
        
        // Nếu một trong hai chuỗi rỗng, trả về 0
        if (!a || !b) return 0;
        
        // Nếu một chuỗi chứa chuỗi còn lại, trả về 1
        if (a.includes(b) || b.includes(a)) return 1;
        
        // Tính số từ chung giữa hai chuỗi
        const aWords = a.split(/\s+/);
        const bWords = b.split(/\s+/);
        
        // Đếm số từ chung
        let commonWords = 0;
        for (const word of aWords) {
            if (bWords.includes(word)) {
                commonWords++;
            }
        }
        
        // Tính độ tương đồng dựa trên số từ chung
        return commonWords / Math.max(aWords.length, bWords.length);
    }
});

// Thêm CSS cho highlight
(function() {
    try {
        const style = document.createElement('style');
        style.textContent = `
            .docx-citation-highlight {
                background-color: #ffff90 !important;
                padding: 10px !important;
                border-radius: 4px !important;
                border-left: 4px solid #4285f4 !important;
                margin: 10px 0 !important;
                transition: background-color 0.3s ease !important;
                animation: docx-highlight-pulse 2s infinite !important;
            }
            
            @keyframes docx-highlight-pulse {
                0% { background-color: #ffff90; }
                50% { background-color: #ffffb8; }
                100% { background-color: #ffff90; }
            }
        `;
        document.head.appendChild(style);
        console.log('[DOCX-CITATION] Đã thêm CSS styles');
    } catch (error) {
        console.error('[DOCX-CITATION] Lỗi khi thêm CSS: ', error);
    }
})(); 
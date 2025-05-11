/**
 * PDF Viewer với chức năng tìm kiếm và highlight
 * Sử dụng thư viện PDF.js
 */

// Đường dẫn đến thư viện PDF.js
const PDFJS_PATH = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174';

// Tải script PDF.js từ CDN
(function loadPDFJS() {
    // Tải thư viện chính
    const scriptPDF = document.createElement('script');
    scriptPDF.src = `${PDFJS_PATH}/pdf.min.js`;
    scriptPDF.onload = function() {
        // Sau khi tải thư viện chính, tải worker
        const scriptWorker = document.createElement('script');
        scriptWorker.textContent = `
            // Định nghĩa worker path
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = '${PDFJS_PATH}/pdf.worker.min.js';
            
            // Khởi tạo viewer khi worker đã sẵn sàng
            document.dispatchEvent(new Event('pdfjsReady'));
        `;
        document.head.appendChild(scriptWorker);
    };
    document.head.appendChild(scriptPDF);
    
    // Thêm CSS cho PDF Viewer
    const style = document.createElement('style');
    style.textContent = `
        .pdf-viewer-container {
            position: relative;
            width: 100%;
            height: 700px;
            background-color: #525659;
            overflow: hidden;
        }
        
        .pdf-viewer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
        }
        
        .pdf-page {
            position: relative;
            margin: 10px auto;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }
        
        .pdf-search-container {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            padding: 8px;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            z-index: 100;
            display: flex;
            gap: 5px;
        }
        
        .pdf-search-input {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 200px;
        }
        
        .pdf-search-btn {
            background: #4098e5;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            cursor: pointer;
        }
        
        .pdf-search-count {
            display: inline-block;
            padding: 6px 0;
            color: #555;
            font-size: 0.9em;
        }
        
        .pdf-nav-btn {
            background: transparent;
            color: #777;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 6px;
            cursor: pointer;
        }
        
        .pdf-highlight {
            position: absolute;
            background-color: rgba(255, 255, 0, 0.4);
            border-radius: 2px;
            mix-blend-mode: multiply;
        }
        
        .pdf-current-highlight {
            background-color: rgba(255, 165, 0, 0.6);
            border: 2px solid #ff8c00;
        }
        
        .pdf-toolbar {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.9);
            padding: 8px;
            border-radius: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            gap: 10px;
            z-index: 100;
        }
        
        .pdf-page-info {
            padding: 6px 10px;
        }
        
        .pdf-citation-info {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(255, 255, 255, 0.95);
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            border-left: 4px solid #4098e5;
            max-width: 50%;
            z-index: 100;
        }
        
        @keyframes pulse-highlight {
            0%, 100% { box-shadow: 0 0 5px rgba(255, 165, 0, 0.5); }
            50% { box-shadow: 0 0 20px rgba(255, 165, 0, 0.8); }
        }
        
        .pdf-init-highlight {
            animation: pulse-highlight 2s infinite;
        }
    `;
    document.head.appendChild(style);
})();

// Khởi tạo trình xem PDF khi trang đã tải xong
document.addEventListener('DOMContentLoaded', function() {
    // Đợi PDF.js tải xong
    document.addEventListener('pdfjsReady', initPDFViewer);
    
    // Lấy tham số URL
    const urlParams = new URLSearchParams(window.location.search);
    const pageNumber = parseInt(urlParams.get('page')) || 1;
    const citationText = urlParams.get('citation') || '';
    
    // Biến toàn cục
    let pdfDoc = null;
    let currentPage = pageNumber;
    let pageRendering = false;
    let pageNumPending = null;
    let scale = 1.2;
    let container = null;
    let searchMatches = [];
    let currentMatch = -1;
    
    // Khởi tạo PDF Viewer
    function initPDFViewer() {
        // Tìm container cho embed PDF
        const pdfEmbed = document.querySelector('embed[type="application/pdf"]');
        if (!pdfEmbed) {
            console.error('Không tìm thấy embed PDF');
            return;
        }
        
        // Lấy URL của PDF
        const pdfUrl = pdfEmbed.src.split('#')[0];
        
        // Thay thế embed bằng container mới
        container = document.createElement('div');
        container.className = 'pdf-viewer-container';
        container.innerHTML = `
            <div class="pdf-search-container">
                <input type="text" class="pdf-search-input" placeholder="Tìm kiếm...">
                <button class="pdf-search-btn"><i class="fas fa-search"></i></button>
                <span class="pdf-search-count"></span>
                <button class="pdf-nav-btn" id="prev-match" title="Kết quả trước"><i class="fas fa-arrow-up"></i></button>
                <button class="pdf-nav-btn" id="next-match" title="Kết quả sau"><i class="fas fa-arrow-down"></i></button>
            </div>
            
            <div class="pdf-citation-info" style="display: none;">
                <h6 class="mb-1 fw-bold"><i class="fas fa-quote-right me-2"></i>Trích dẫn</h6>
                <p class="mb-0 citation-text" style="font-size: 0.9em;"></p>
            </div>
            
            <div class="pdf-viewer"></div>
            
            <div class="pdf-toolbar">
                <button class="pdf-nav-btn" id="prev-page" title="Trang trước"><i class="fas fa-chevron-left"></i></button>
                <span class="pdf-page-info">Trang <span id="page-num"></span> / <span id="page-count"></span></span>
                <button class="pdf-nav-btn" id="next-page" title="Trang sau"><i class="fas fa-chevron-right"></i></button>
                <button class="pdf-nav-btn" id="zoom-out" title="Thu nhỏ"><i class="fas fa-search-minus"></i></button>
                <button class="pdf-nav-btn" id="zoom-in" title="Phóng to"><i class="fas fa-search-plus"></i></button>
            </div>
        `;
        
        // Thay thế embed
        pdfEmbed.parentNode.replaceChild(container, pdfEmbed);
        
        // Khởi tạo PDF.js
        const loadingTask = pdfjsLib.getDocument(pdfUrl);
        loadingTask.promise.then(function(pdf) {
            pdfDoc = pdf;
            document.getElementById('page-count').textContent = pdf.numPages;
            
            // Tải trang đầu tiên
            renderPage(currentPage);
            
            // Sự kiện cho các nút điều hướng
            document.getElementById('prev-page').addEventListener('click', onPrevPage);
            document.getElementById('next-page').addEventListener('click', onNextPage);
            document.getElementById('zoom-in').addEventListener('click', onZoomIn);
            document.getElementById('zoom-out').addEventListener('click', onZoomOut);
            
            // Sự kiện tìm kiếm
            const searchInput = container.querySelector('.pdf-search-input');
            const searchBtn = container.querySelector('.pdf-search-btn');
            const prevMatchBtn = document.getElementById('prev-match');
            const nextMatchBtn = document.getElementById('next-match');
            
            searchBtn.addEventListener('click', function() { performSearch(searchInput.value); });
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') performSearch(searchInput.value);
            });
            prevMatchBtn.addEventListener('click', showPreviousMatch);
            nextMatchBtn.addEventListener('click', showNextMatch);
            
            // Nếu có tham số citation, tự động tìm kiếm
            if (citationText) {
                loadCitationContent(citationText);
            }
        });
    }
    
    // Render một trang PDF
    function renderPage(num) {
        pageRendering = true;
        
        // Cập nhật UI
        document.getElementById('page-num').textContent = num;
        
        // Lấy trang từ PDF
        pdfDoc.getPage(num).then(function(page) {
            const viewer = container.querySelector('.pdf-viewer');
            
            // Tạo canvas cho trang
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const viewport = page.getViewport({ scale: scale });
            
            // Thiết lập kích thước canvas
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            
            // Tạo div chứa trang
            const pageDiv = document.createElement('div');
            pageDiv.className = 'pdf-page';
            pageDiv.style.width = `${viewport.width}px`;
            pageDiv.style.height = `${viewport.height}px`;
            pageDiv.setAttribute('data-page-number', num);
            
            // Thêm canvas vào trang
            pageDiv.appendChild(canvas);
            
            // Xóa các trang hiện tại
            viewer.innerHTML = '';
            viewer.appendChild(pageDiv);
            
            // Render trang
            const renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            
            page.render(renderContext).promise.then(function() {
                pageRendering = false;
                
                // Sau khi render trang, trích xuất văn bản
                return page.getTextContent();
            }).then(function(textContent) {
                // Lưu trữ thông tin văn bản
                const textLayer = document.createElement('div');
                textLayer.className = 'textLayer';
                textLayer.style.width = `${viewport.width}px`;
                textLayer.style.height = `${viewport.height}px`;
                pageDiv.appendChild(textLayer);
                
                pdfjsLib.renderTextLayer({
                    textContent: textContent,
                    container: textLayer,
                    viewport: viewport,
                    textDivs: []
                });
                
                // Nếu có từ khóa tìm kiếm, thực hiện lại việc tìm kiếm
                const searchInput = container.querySelector('.pdf-search-input');
                if (searchInput.value.trim()) {
                    performSearch(searchInput.value);
                }
                
                if (pageNumPending !== null) {
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }
            });
        });
    }
    
    // Chuyển đến trang trước
    function onPrevPage() {
        if (currentPage <= 1) return;
        currentPage--;
        queueRenderPage(currentPage);
    }
    
    // Chuyển đến trang sau
    function onNextMatch() {
        if (searchMatches.length === 0) return;
        
        currentMatch = (currentMatch + 1) % searchMatches.length;
        highlightCurrentMatch();
    }
    
    // Chuyển đến trang sau
    function onNextPage() {
        if (currentPage >= pdfDoc.numPages) return;
        currentPage++;
        queueRenderPage(currentPage);
    }
    
    // Phóng to
    function onZoomIn() {
        scale *= 1.2;
        queueRenderPage(currentPage);
    }
    
    // Thu nhỏ
    function onZoomOut() {
        scale /= 1.2;
        queueRenderPage(currentPage);
    }
    
    // Đặt trang vào hàng đợi render
    function queueRenderPage(num) {
        if (pageRendering) {
            pageNumPending = num;
        } else {
            renderPage(num);
        }
    }
    
    // Thực hiện tìm kiếm
    function performSearch(query) {
        if (!query || query.trim() === '') return;
        
        // Xóa các highlight hiện tại
        clearHighlights();
        
        // Reset matches
        searchMatches = [];
        currentMatch = -1;
        
        // Hiển thị thông báo đang tìm kiếm
        const searchCount = container.querySelector('.pdf-search-count');
        searchCount.textContent = 'Đang tìm...';
        
        // Thực hiện tìm kiếm
        let matchesCount = 0;
        let pendingFindText = pdfDoc.numPages;
        
        for (let i = 1; i <= pdfDoc.numPages; i++) {
            pdfDoc.getPage(i).then(function(page) {
                return page.getTextContent();
            }).then(function(textContent) {
                const text = textContent.items.map(item => item.str).join(' ');
                
                // Tìm tất cả vị trí match trong trang
                const regex = new RegExp(escapeRegExp(query), 'gi');
                let match;
                while ((match = regex.exec(text)) !== null) {
                    searchMatches.push({
                        pageNum: i,
                        matchIndex: matchesCount++,
                        position: match.index,
                        text: match[0]
                    });
                }
                
                // Kiểm tra nếu đã tìm xong tất cả các trang
                pendingFindText--;
                if (pendingFindText === 0) {
                    // Hiển thị số lượng kết quả tìm thấy
                    searchCount.textContent = `${searchMatches.length} kết quả`;
                    
                    // Nếu có kết quả, highlight kết quả đầu tiên
                    if (searchMatches.length > 0) {
                        currentMatch = 0;
                        
                        // Nếu kết quả ở trang khác, chuyển đến trang đó
                        if (searchMatches[0].pageNum !== currentPage) {
                            currentPage = searchMatches[0].pageNum;
                            queueRenderPage(currentPage);
                        } else {
                            highlightCurrentMatch();
                        }
                    }
                }
            });
        }
    }
    
    // Xóa tất cả highlight
    function clearHighlights() {
        const highlights = container.querySelectorAll('.pdf-highlight');
        highlights.forEach(h => h.remove());
    }
    
    // Highlight kết quả hiện tại
    function highlightCurrentMatch() {
        if (currentMatch < 0 || currentMatch >= searchMatches.length) return;
        
        // Xóa highlight hiện tại
        const currentHighlight = container.querySelector('.pdf-current-highlight');
        if (currentHighlight) {
            currentHighlight.classList.remove('pdf-current-highlight');
            currentHighlight.classList.remove('pdf-init-highlight');
        }
        
        const match = searchMatches[currentMatch];
        
        // Nếu kết quả ở trang khác, chuyển đến trang đó
        if (match.pageNum !== currentPage) {
            currentPage = match.pageNum;
            queueRenderPage(currentPage);
            return;
        }
        
        // Tìm text layer của trang hiện tại
        const pageDiv = container.querySelector(`.pdf-page[data-page-number="${currentPage}"]`);
        if (!pageDiv) return;
        
        const textLayer = pageDiv.querySelector('.textLayer');
        if (!textLayer) return;
        
        // Tìm vị trí text trong DOM
        const textDivs = Array.from(textLayer.querySelectorAll('span'));
        let textPos = 0;
        let matchStart = -1;
        let matchEnd = -1;
        
        for (let i = 0; i < textDivs.length; i++) {
            const textLength = textDivs[i].textContent.length;
            
            if (matchStart < 0 && textPos + textLength > match.position) {
                matchStart = i;
            }
            
            if (matchEnd < 0 && textPos + textLength >= match.position + match.text.length) {
                matchEnd = i;
                break;
            }
            
            textPos += textLength;
        }
        
        // Nếu tìm thấy vị trí, thêm highlight
        if (matchStart >= 0 && matchEnd >= 0) {
            for (let i = matchStart; i <= matchEnd; i++) {
                const textDiv = textDivs[i];
                const rect = textDiv.getBoundingClientRect();
                const parentRect = pageDiv.getBoundingClientRect();
                
                const highlight = document.createElement('div');
                highlight.className = 'pdf-highlight';
                if (i === matchStart) highlight.classList.add('pdf-current-highlight', 'pdf-init-highlight');
                
                highlight.style.left = (rect.left - parentRect.left) + 'px';
                highlight.style.top = (rect.top - parentRect.top) + 'px';
                highlight.style.width = rect.width + 'px';
                highlight.style.height = rect.height + 'px';
                
                pageDiv.appendChild(highlight);
            }
            
            // Cuộn đến vị trí highlight
            const highlight = container.querySelector('.pdf-current-highlight');
            if (highlight) {
                highlight.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }
    }
    
    // Hiển thị kết quả tìm kiếm trước đó
    function showPreviousMatch() {
        if (searchMatches.length === 0) return;
        
        currentMatch = (currentMatch - 1 + searchMatches.length) % searchMatches.length;
        highlightCurrentMatch();
    }
    
    // Hiển thị kết quả tìm kiếm tiếp theo
    function showNextMatch() {
        if (searchMatches.length === 0) return;
        
        currentMatch = (currentMatch + 1) % searchMatches.length;
        highlightCurrentMatch();
    }
    
    // Tải nội dung trích dẫn từ API
    function loadCitationContent(initialSearchText) {
        const docIdMatch = window.location.pathname.match(/\/documents\/(\d+)/);
        if (!docIdMatch) return;
        
        const docId = docIdMatch[1];
        const page = urlParams.get('page') || '1';
        const highlight = urlParams.get('highlight');
        
        // Nếu đã có text để tìm kiếm, sử dụng luôn
        if (initialSearchText && initialSearchText.length > 5) {
            container.querySelector('.pdf-search-input').value = initialSearchText;
            performSearch(initialSearchText);
            
            // Hiển thị thông tin trích dẫn
            const citationInfo = container.querySelector('.pdf-citation-info');
            const citationText = container.querySelector('.citation-text');
            citationText.textContent = initialSearchText;
            citationInfo.style.display = 'block';
            return;
        }
        
        // Gọi API để lấy nội dung trích dẫn
        fetch(`/api/citation/${docId}/${page}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success && data.content) {
                    // Chuyển từ HTML sang text thuần túy
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data.content;
                    const plainText = tempDiv.textContent || tempDiv.innerText;
                    
                    // Lấy 50 ký tự đầu tiên làm từ khóa tìm kiếm
                    const searchKeyword = plainText.substring(0, 50).trim();
                    
                    if (searchKeyword && searchKeyword.length > 5) {
                        // Thiết lập từ khóa tìm kiếm
                        container.querySelector('.pdf-search-input').value = searchKeyword;
                        performSearch(searchKeyword);
                        
                        // Hiển thị thông tin trích dẫn
                        const citationInfo = container.querySelector('.pdf-citation-info');
                        const citationText = container.querySelector('.citation-text');
                        citationText.textContent = plainText.substring(0, 200) + (plainText.length > 200 ? '...' : '');
                        citationInfo.style.display = 'block';
                    }
                }
            })
            .catch(error => {
                console.error('[PDF-VIEWER] Lỗi khi tải thông tin trích dẫn:', error);
            });
    }
    
    // Hàm escape cho regex
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
}); 
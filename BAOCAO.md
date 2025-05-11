# BÁO CÁO CHATBOT KINH TẾ

## 1. Giới thiệu ứng dụng và các tính năng

### 1.1 Tổng quan

ChatBot Kinh Tế là một ứng dụng trợ lý thông minh chuyên về lĩnh vực kinh tế, được phát triển với mục tiêu cung cấp thông tin kinh tế một cách chính xác và nhanh chóng. Ứng dụng kết hợp giữa công nghệ xử lý ngôn ngữ tự nhiên (NLP) tiên tiến và cơ sở dữ liệu kinh tế được cập nhật thường xuyên để cung cấp trải nghiệm tương tác hiệu quả cho người dùng.

### 1.2 Các tính năng chính

#### 1.2.1 Chat với kiến thức kinh tế tổng quát
- Tương tác với chatbot qua giao diện đơn giản, thân thiện
- Trả lời các câu hỏi liên quan đến kinh tế Việt Nam
- Sử dụng vector database để truy xuất thông tin chính xác
- Khả năng mở rộng kiến thức liên tục thông qua cập nhật dữ liệu

#### 1.2.2 Chat với tài liệu cụ thể
- Tải lên và truy vấn thông tin từ tài liệu PDF, DOCX, TXT
- Hỗ trợ trích dẫn nguồn tài liệu cụ thể
- Khả năng chọn nhiều tài liệu để hỏi đáp đồng thời
- Xem trực tiếp nội dung tài liệu gốc và định vị thông tin trong tài liệu

#### 1.2.3 Quản lý tài liệu
- Tải lên tài liệu PDF, Word, và các định dạng văn bản phổ biến
- Phân loại và quản lý tài liệu theo danh mục
- Xem trước nội dung tài liệu
- Xử lý tự động tài liệu và tạo vector embedding

#### 1.2.4 Lưu trữ và tìm kiếm lịch sử trò chuyện
- Tự động lưu các cuộc hội thoại
- Tìm kiếm trong lịch sử trò chuyện
- Tiếp tục cuộc trò chuyện từ điểm dừng trước đó

#### 1.2.5 Quản trị hệ thống
- Giao diện quản trị cho người quản lý
- Cấu hình API (Google Gemini/OpenAI)
- Theo dõi thống kê sử dụng
- Quản lý người dùng và phân quyền
- Quản lý và cập nhật cơ sở dữ liệu vector
- Thêm và quản lý danh mục tài liệu
- Phê duyệt và kiểm soát tài liệu được tải lên
- Phân tích xu hướng và báo cáo thống kê sử dụng
- Cấu hình thông số hệ thống (chunk size, overlap, embedding model)
- Giám sát và khắc phục sự cố hệ thống
- Quản lý lưu trữ và tối ưu hóa hiệu suất
- Cập nhật và tùy chỉnh prompt template

#### 1.2.6 Tính năng bảo mật
- Xác thực người dùng an toàn
- Mã hóa dữ liệu nhạy cảm
- Quản lý phiên làm việc
- Kiểm soát truy cập dựa trên vai trò

## 2. Cách hoạt động

### 2.1 Kiến trúc hệ thống

Ứng dụng ChatBot Kinh Tế được xây dựng với kiến trúc microservice gồm 2 thành phần chính:

1. **Frontend và API Gateway**: Xây dựng bằng Laravel, chịu trách nhiệm xử lý giao diện người dùng, quản lý người dùng, lưu trữ hội thoại.
2. **Backend AI**: Xây dựng bằng Python/FastAPI, chịu trách nhiệm xử lý ngôn ngữ tự nhiên, tương tác với LLM API và quản lý vector database.

### 2.2 Chat với kinh tế (Chat thông thường)

Khi người dùng gửi câu hỏi thông thường về kinh tế, quy trình xử lý diễn ra như sau:

1. **Tiếp nhận câu hỏi**: Người dùng nhập câu hỏi qua giao diện web.
2. **Xử lý đầu vào**: Laravel controller tiếp nhận yêu cầu và chuyển tiếp đến FastAPI backend.
3. **Tìm kiếm ngữ cảnh**: Backend tạo embedding cho câu hỏi và tìm kiếm thông tin liên quan trong vector database core.
4. **Tổng hợp ngữ cảnh**: Các đoạn thông tin liên quan nhất được tổng hợp thành prompt có cấu trúc.
5. **Gọi API LLM**: Hệ thống gửi prompt đến API của Google Gemini hoặc OpenAI tùy theo cấu hình.
6. **Xử lý phản hồi**: Phản hồi từ API được xử lý, định dạng và gửi về cho người dùng.
7. **Lưu trữ hội thoại**: Câu hỏi và phản hồi được lưu vào cơ sở dữ liệu để tham khảo sau.

### 2.3 Chat với tài liệu

Tính năng chat với tài liệu cho phép người dùng truy vấn thông tin từ các tài liệu cụ thể:

1. **Chọn tài liệu**: Người dùng chọn một hoặc nhiều tài liệu từ thư viện cá nhân.
2. **Đặt câu hỏi**: Người dùng nhập câu hỏi liên quan đến nội dung tài liệu.
3. **Truy xuất thông tin**:
   - Backend tạo embedding cho câu hỏi
   - Tìm kiếm trong vector database của tài liệu đã chọn
   - Trích xuất các đoạn văn bản liên quan nhất từ tài liệu
4. **Tạo phản hồi có trích dẫn**:
   - LLM tổng hợp thông tin từ các đoạn văn bản liên quan
   - Tạo ra phản hồi với trích dẫn cụ thể
   - Bao gồm thông tin nguồn (tên tài liệu, trang, vị trí)
5. **Hiển thị kết quả**: 
   - Hiển thị phản hồi cùng với danh sách trích dẫn có thể nhấp để xem
   - Khi nhấp vào trích dẫn, người dùng có thể xem đoạn văn bản gốc trong tài liệu

### 2.4 Xử lý tài liệu

Quy trình xử lý khi người dùng tải lên tài liệu mới:

1. **Tải lên**: Người dùng tải lên tài liệu qua giao diện web.
2. **Phân tích tài liệu**: Hệ thống tự động phát hiện định dạng (PDF, DOCX, TXT) và sử dụng công cụ phù hợp để trích xuất văn bản.
3. **Phân đoạn**: Nội dung tài liệu được chia thành các đoạn văn bản nhỏ (chunks) có kích thước phù hợp.
4. **Tạo embedding**: Mỗi đoạn văn bản được chuyển thành vector embedding bằng mô hình Sentence Transformer.
5. **Lưu trữ**: Các vector embedding được lưu vào FAISS vector database của riêng tài liệu đó.

### 2.5 Cơ chế lưu trữ trích dẫn

Hệ thống đã được cải tiến để lưu trữ thông tin trích dẫn trong cơ sở dữ liệu:

1. **Lưu trữ**: Khi chatbot trả lời với các trích dẫn, thông tin trích dẫn được lưu dưới dạng JSON trong cột 'citations' của bảng messages.
2. **Định dạng**: Mỗi trích dẫn bao gồm doc_id, page, title, url, và chunk_index để xác định vị trí chính xác trong tài liệu.
3. **Hiển thị**: Khi người dùng xem lại lịch sử trò chuyện, các trích dẫn được hiển thị lại đúng như khi họ nhận được ban đầu.
4. **Tương tác**: Người dùng có thể nhấp vào trích dẫn để xem tài liệu gốc tại vị trí cụ thể được đề cập.

### 2.6 Luồng hoạt động chi tiết

#### 2.6.1 Luồng chat với kiến thức kinh tế

Luồng hoạt động của chat với kiến thức kinh tế được mô tả chi tiết theo các bước sau:

1. **Nhập câu hỏi**:
   - Người dùng truy cập giao diện chat
   - Nhập câu hỏi liên quan đến kinh tế vào ô chat
   - Nhấn nút gửi hoặc nhấn Enter

2. **Xử lý frontend**:
   - JavaScript bắt sự kiện gửi tin nhắn
   - Hiển thị tin nhắn người dùng trong giao diện
   - Hiển thị trạng thái "đang nhập..." để biểu thị đang xử lý
   - Gửi yêu cầu AJAX đến Laravel controller

3. **Controller xử lý**:
   - `ChatController@sendMessage` nhận yêu cầu
   - Lưu tin nhắn người dùng vào database (bảng `messages`)
   - Tạo/cập nhật cuộc trò chuyện (bảng `conversations`)
   - Gửi yêu cầu HTTP đến FastAPI backend

4. **Backend xử lý ngữ cảnh**:
   - Nhận yêu cầu từ Laravel tại endpoint `/api/v1/chat/chat-direct`
   - Tạo embedding vector cho câu hỏi sử dụng `sentence-transformers/all-MiniLM-L6-v2`
   - Thực hiện tìm kiếm trong vector database FAISS sử dụng phương pháp similarity search
   - Lọc và xếp hạng các kết quả theo mức độ liên quan
   - Tổng hợp 5-10 đoạn văn bản có liên quan nhất làm ngữ cảnh

5. **Tạo prompt và gọi API LLM**:
   - Tạo prompt có cấu trúc bao gồm ngữ cảnh và câu hỏi
   - Thêm các hướng dẫn cụ thể cho LLM (cách trả lời, cách dẫn nguồn)
   - Gọi API LLM (Google Gemini hoặc OpenAI) qua API service
   - Nhận kết quả từ API và xử lý định dạng

6. **Xử lý phản hồi**:
   - Backend trả kết quả về cho Laravel controller
   - Controller lưu phản hồi chatbot vào database
   - Phản hồi được gửi về frontend qua AJAX

7. **Hiển thị kết quả**:
   - JavaScript nhận phản hồi và ẩn trạng thái "đang nhập..."
   - Hiển thị phản hồi từ chatbot trong giao diện
   - Chuyển đổi định dạng text sang HTML nếu cần thiết (như Markdown)
   - Tự động cuộn xuống để hiện phản hồi mới

8. **Lưu trữ và phân tích**:
   - Hệ thống lưu toàn bộ cuộc trò chuyện vào cơ sở dữ liệu
   - Dữ liệu được sử dụng để cải thiện chất lượng phản hồi
   - Phục vụ cho việc phân tích xu hướng câu hỏi

#### 2.6.2 Luồng chat với tài liệu

Luồng chat với tài liệu có một số bước đặc biệt hơn so với chat thông thường:

1. **Chọn tài liệu**:
   - Người dùng truy cập trang quản lý tài liệu
   - Chọn một hoặc nhiều tài liệu cần truy vấn
   - Hệ thống chuyển sang giao diện chat với thông báo "Đang chat với X tài liệu"
   - Lưu thông tin tài liệu đã chọn vào session

2. **Đặt câu hỏi**:
   - Người dùng nhập câu hỏi liên quan đến nội dung tài liệu
   - Frontend gửi yêu cầu đến Laravel controller kèm theo ID của các tài liệu đã chọn

3. **Xử lý controller tài liệu**:
   - `ChatController@sendMessage` nhận yêu cầu với tham số `document_ids`
   - Lưu tin nhắn người dùng vào database
   - Gọi API endpoint `/api/v1/chat/document-chat` thay vì endpoint chat thông thường

4. **Truy vấn vector database của tài liệu**:
   - Backend tạo embedding cho câu hỏi
   - Thực hiện tìm kiếm đa vector store dựa trên các document_ids được cung cấp
   - Sử dụng thuật toán MMR (Maximum Marginal Relevance) để tìm đoạn văn đa dạng và liên quan
   - Trích xuất thông tin metadata của mỗi chunk (document_id, page_number, title, etc.)

5. **Tạo phản hồi với trích dẫn**:
   - Tạo prompt đặc biệt cho LLM yêu cầu sử dụng thông tin từ tài liệu
   - Gửi prompt đến LLM API
   - Nhận phản hồi và kết hợp với metadata để tạo các trích dẫn
   - Đóng gói phản hồi và danh sách trích dẫn trong cấu trúc JSON

6. **Lưu trữ phản hồi có trích dẫn**:
   - Controller nhận phản hồi từ Python backend
   - Lưu cả nội dung phản hồi và trích dẫn vào bảng `messages`
   - Cột `citations` JSON lưu trữ tất cả thông tin trích dẫn

7. **Hiển thị kết quả với trích dẫn**:
   - Frontend hiển thị phản hồi của chatbot
   - Hiển thị danh sách trích dẫn có thể nhấp vào bên dưới phản hồi
   - Tạo liên kết cho từng trích dẫn với thông tin document_id, page, etc.

8. **Xem chi tiết trích dẫn**:
   - Khi người dùng nhấp vào trích dẫn, hiển thị modal hoặc cửa sổ mới
   - Load nội dung tài liệu gốc từ vị trí được trích dẫn
   - Đối với PDF: hiển thị trang cụ thể, với DOCX/TXT: cuộn đến đoạn chính xác
   - Highlight đoạn văn bản được trích dẫn

#### 2.6.3 Luồng tải lên và xử lý tài liệu

1. **Tải lên tài liệu**:
   - Người dùng truy cập trang quản lý tài liệu
   - Chọn tập tin từ máy tính (PDF, DOCX, TXT)
   - Nhập metadata: tiêu đề, danh mục, mô tả
   - Nhấn nút tải lên

2. **Xử lý ban đầu**:
   - Laravel controller `DocumentController@store` nhận file
   - Xác thực loại file và kích thước
   - Lưu file vào thư mục storage/app/documents
   - Tạo bản ghi trong bảng `documents` với metadata và đường dẫn file

3. **Gửi yêu cầu xử lý đến Python backend**:
   - Controller gọi API endpoint `/api/v1/documents/process`
   - Gửi thông tin document_id, file_path, metadata
   - Backend nhận yêu cầu và bắt đầu xử lý bất đồng bộ

4. **Trích xuất nội dung**:
   - Backend phát hiện loại file (PDF, DOCX, TXT)
   - Sử dụng thư viện phù hợp để trích xuất văn bản
     - PDF: PyPDFLoader, pdfplumber
     - DOCX: Docx2txtLoader
     - TXT: TextLoader
   - Bảo toàn thông tin trang và cấu trúc

5. **Phân đoạn nội dung**:
   - Sử dụng RecursiveCharacterTextSplitter
   - Chia văn bản thành các chunks nhỏ (mặc định 1000 ký tự)
   - Đảm bảo overlap (mặc định 200 ký tự) để giữ ngữ cảnh
   - Gắn metadata cho mỗi chunk (document_id, page, position)

6. **Tạo embedding**:
   - Tải mô hình embedding HuggingFace
   - Tạo vector embedding cho mỗi chunk
   - Tạo FAISS vector store riêng cho tài liệu
   - Lưu vector store vào vector_db/uploads/doc_{id}

7. **Cập nhật trạng thái**:
   - Backend gửi thông báo hoàn thành về Laravel
   - Controller cập nhật trạng thái tài liệu trong database
   - Frontend hiển thị thông báo thành công cho người dùng

8. **Tích hợp (tùy chọn)**:
   - Admin có thể chọn tích hợp tài liệu vào vector database chung
   - Gọi API endpoint `/api/v1/documents/integrate`
   - Backend thực hiện sao chép và merge vectors vào vector store chung

#### 2.6.4 Luồng quản lý hội thoại

1. **Xem lịch sử hội thoại**:
   - Người dùng truy cập trang chat hoặc lịch sử
   - Hệ thống hiển thị danh sách các cuộc trò chuyện
   - Người dùng chọn một cuộc trò chuyện để xem

2. **Tải lịch sử tin nhắn**:
   - Frontend gọi API endpoint `chat/conversations/{id}/messages`
   - Controller `ChatController@getMessages` truy vấn database
   - Truy vấn bảng `messages` kèm theo thông tin trích dẫn
   - Trả về dữ liệu JSON bao gồm tất cả tin nhắn và trích dẫn

3. **Hiển thị hội thoại**:
   - Frontend render lại toàn bộ cuộc trò chuyện
   - Hiển thị tin nhắn người dùng và chatbot theo thứ tự thời gian
   - Hiển thị lại các trích dẫn nếu có
   - Khôi phục khả năng tương tác với trích dẫn

4. **Tiếp tục cuộc trò chuyện**:
   - Người dùng gõ tin nhắn mới trong cùng cuộc trò chuyện
   - Frontend gửi conversation_id cùng với tin nhắn mới
   - Hệ thống xử lý và trả về phản hồi như bình thường
   - Tin nhắn được thêm vào cùng conversation_id

5. **Xóa cuộc trò chuyện**:
   - Người dùng có thể xóa cuộc trò chuyện từ sidebar
   - Frontend gửi yêu cầu xóa đến `ChatController@deleteConversation`
   - Controller xóa tất cả tin nhắn liên quan trước
   - Sau đó xóa bản ghi conversation

### 2.7 Tích hợp và mở rộng hệ thống

#### 2.7.1 Tích hợp API bên ngoài

Hệ thống được thiết kế để dễ dàng tích hợp với các API LLM khác ngoài Google Gemini và OpenAI:
- Kiến trúc API Service cho phép thêm các adapter mới
- Hỗ trợ cấu hình qua file .env hoặc giao diện admin
- Khả năng chuyển đổi giữa các API động khi cần thiết

#### 2.7.2 Mở rộng vector database

Hệ thống cho phép mở rộng liên tục cơ sở dữ liệu:
- Thêm dữ liệu core mới từ nguồn chính thức
- Tích hợp tài liệu người dùng vào cơ sở dữ liệu chung
- Cập nhật và làm mới vector database định kỳ
- Hỗ trợ nhiều loại nguồn dữ liệu khác nhau

## 4. Chức năng quản trị chi tiết

### 4.1 Quản lý người dùng

Giao diện quản trị cung cấp các công cụ toàn diện để quản lý người dùng:

1. **Quản lý tài khoản**:
   - Tạo, chỉnh sửa và vô hiệu hóa tài khoản người dùng
   - Phân quyền người dùng (Admin, Moderator, User)
   - Đặt lại mật khẩu và quản lý thông tin cá nhân
   - Xem lịch sử hoạt động của người dùng

2. **Quản lý phiên làm việc**:
   - Theo dõi các phiên làm việc hiện tại
   - Đăng xuất người dùng từ xa khi cần thiết
   - Xem thông tin phiên như IP, thiết bị, thời gian

3. **Phân tích hành vi**:
   - Thống kê số lượng truy vấn theo người dùng
   - Xác định các chủ đề và từ khóa phổ biến
   - Tạo báo cáo sử dụng cho từng người dùng

### 4.2 Quản lý nội dung và tài liệu

1. **Quản lý tài liệu**:
   - Duyệt và phê duyệt tài liệu do người dùng tải lên
   - Chỉnh sửa metadata và phân loại tài liệu
   - Xóa hoặc vô hiệu hóa tài liệu không phù hợp
   - Tổ chức tài liệu theo danh mục và thẻ

2. **Quản lý vector database**:
   - Xem thông tin chi tiết về vector database
   - Tích hợp tài liệu vào cơ sở dữ liệu chung
   - Xóa hoặc cập nhật vectors của tài liệu cụ thể
   - Theo dõi kích thước và hiệu suất vector store

3. **Quản lý dữ liệu core**:
   - Tải lên dữ liệu core mới từ nguồn chính thức
   - Cập nhật dữ liệu hiện có khi có thông tin mới
   - Tạo vector embeddings cho dữ liệu core
   - Sao lưu và khôi phục vector database

### 4.3 Cấu hình và tối ưu hóa hệ thống

1. **Cấu hình API**:
   - Chọn nhà cung cấp API (Google Gemini/OpenAI)
   - Cấu hình API keys và endpoints
   - Chọn models và cấu hình parameters
   - Theo dõi sử dụng API và chi phí

2. **Cấu hình vector database**:
   - Điều chỉnh kích thước chunk và overlap
   - Chọn mô hình embedding
   - Cấu hình thuật toán tìm kiếm và parameters
   - Tối ưu hóa hiệu suất tìm kiếm

3. **Tùy chỉnh prompt templates**:
   - Chỉnh sửa các template prompt cho các tình huống khác nhau
   - Tùy chỉnh hướng dẫn cho LLM
   - Điều chỉnh cách thức hiển thị trích dẫn
   - Tùy chỉnh thông điệp hệ thống

4. **Giám sát hiệu suất**:
   - Theo dõi thời gian phản hồi API
   - Giám sát sử dụng bộ nhớ và CPU
   - Phát hiện và khắc phục lỗi
   - Tối ưu hóa hiệu suất truy vấn vector database

### 4.4 Báo cáo và phân tích

1. **Báo cáo sử dụng**:
   - Báo cáo số lượng truy vấn theo ngày/tuần/tháng
   - Thống kê sử dụng theo người dùng
   - Theo dõi sử dụng API và chi phí
   - Báo cáo tài liệu được truy vấn nhiều nhất

2. **Phân tích nội dung**:
   - Xác định chủ đề và từ khóa phổ biến
   - Phân tích sentiment từ câu hỏi người dùng
   - Xác định khoảng trống kiến thức trong database
   - Tạo đề xuất cải thiện nội dung

3. **Xuất báo cáo**:
   - Xuất báo cáo dạng CSV, PDF
   - Tạo dashboard tùy chỉnh
   - Lên lịch báo cáo tự động
   - Tích hợp với các công cụ phân tích khác

### 4.5 Bảo mật và sao lưu

1. **Quản lý bảo mật**:
   - Cấu hình xác thực hai yếu tố
   - Thiết lập chính sách mật khẩu
   - Giám sát đăng nhập bất thường
   - Quản lý phiên và tokens

2. **Sao lưu và khôi phục**:
   - Lên lịch sao lưu tự động
   - Sao lưu cơ sở dữ liệu MySQL
   - Sao lưu vector database
   - Khôi phục từ bản sao lưu

---

Báo cáo này cung cấp tổng quan về ứng dụng ChatBot Kinh Tế, cách hoạt động của các tính năng chính, và hướng dẫn cài đặt chi tiết. Để biết thêm thông tin và tài liệu kỹ thuật chi tiết, vui lòng tham khảo tài liệu API và mã nguồn.

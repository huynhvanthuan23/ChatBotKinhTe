# Tính năng Trích dẫn (Citations) trong ChatBot Kinh Tế

Tài liệu này hướng dẫn cách triển khai và sử dụng tính năng trích dẫn (citations) trong hệ thống ChatBot Kinh Tế. Tính năng này cho phép hệ thống trả lời câu hỏi với các trích dẫn chính xác từ tài liệu gốc.

## 1. Tổng quan

Khi người dùng đặt câu hỏi và chọn một tài liệu cụ thể (hoặc nhiều tài liệu), hệ thống sẽ:

1. Tìm kiếm trong vector database của các tài liệu đã chọn (`vector_db/doc_{id}`)
2. Thu thập các đoạn văn bản liên quan nhất
3. Gửi các đoạn này tới LLM để tổng hợp câu trả lời
4. Đánh số và trích dẫn nguồn cụ thể trong câu trả lời

## 2. Đã triển khai

Chúng tôi đã cải tiến các thành phần sau:

### 2.1. Backend (Python/FastAPI)

Trong `app/services/chatbot.py`, phương thức `get_simple_retrieval` đã được cải tiến để:

- Thêm tham số `allow_dangerous_deserialization=True` khi tải FAISS vector database
- Định dạng trích dẫn để hiển thị độ dài, nguồn và nội dung
- Tạo prompt cho LLM với yêu cầu cụ thể về cách trích dẫn
- Tạo phản hồi tổng hợp có chứa số đánh dấu trích dẫn (ví dụ: [1], [2])
- Tạo phần "Tài liệu tham khảo" ở cuối phản hồi

### 2.2. Tools (Python scripts)

Chúng tôi đã tạo các script sau để kiểm tra tính năng:

1. `query_openai_doc.py` - Sử dụng OpenAI API để tạo phản hồi tổng hợp
2. `simple_openai_query.py` - Phiên bản đơn giản không cần API key
3. `chatbot_integration.py` - Tích hợp trực tiếp với ChatbotService

## 3. Hướng dẫn sử dụng

### 3.1. Kiểm tra với simple_openai_query.py

Script này đơn giản hóa việc test mà không cần API key:

```bash
python simple_openai_query.py
```

Nhập câu hỏi hoặc để trống để sử dụng câu hỏi mẫu. Script sẽ tìm kiếm trong vector database của tài liệu ID 23 và hiển thị:
- Danh sách các kết quả tìm thấy với độ dài và metadata
- Phản hồi mẫu minh họa cách LLM kết hợp các kết quả

### 3.2. Kiểm tra với query_openai_doc.py

Cho phép tạo phản hồi thật bằng OpenAI API:

```bash
python query_openai_doc.py
```

Bạn sẽ cần nhập OpenAI API key khi được nhắc.

### 3.3. Kiểm tra tích hợp với chatbot_integration.py

Kiểm tra tính năng trên ChatbotService thực tế:

```bash
python chatbot_integration.py "Câu hỏi của bạn" 23
```

Trong đó:
- "Câu hỏi của bạn" là câu hỏi bạn muốn hỏi
- 23 là ID của tài liệu bạn muốn tìm kiếm (có thể thay đổi hoặc thêm nhiều ID, ví dụ: 23,24,25)

## 4. Định dạng phản hồi

Phản hồi từ endpoint `/api/v1/chat/simple-chat` sẽ có cấu trúc như sau:

```json
{
  "success": true,
  "response": "Nội dung phản hồi tổng hợp với các trích dẫn [1], [2], [3]...",
  "query": "Câu hỏi ban đầu",
  "doc_count": 5,
  "citations": [
    {
      "number": 1,
      "content": "Nội dung đoạn 1",
      "source": "/app/storage/documents/3/file.docx",
      "document_id": 23,
      "length": 100
    },
    ...
  ]
}
```

## 5. Để triển khai trong môi trường Docker

1. Đảm bảo vector database được tạo đúng cách trong container:

```bash
docker cp fix_vector_db.py hvt_2110359:/app/
docker exec -it hvt_2110359 python /app/fix_vector_db.py
```

2. Khởi động lại container:

```bash
docker-compose restart
```

3. Kiểm tra trạng thái:

```bash
docker exec -it hvt_2110359 python /app/docker_fix_vector.py
```

## 6. Khắc phục sự cố

Nếu gặp vấn đề "Vector database not initialized" hoặc lỗi `allow_dangerous_deserialization=True`:

1. Kiểm tra thư mục vector_db trong Docker container:

```bash
docker exec -it hvt_2110359 ls -la /app/vector_db
```

2. Tạo lại vector database nếu cần:

```bash
docker exec -it hvt_2110359 python /app/fix_vector_db.py
```

## 7. Ví dụ kết quả

### 7.1. Mẫu kết quả tìm kiếm trực tiếp từ FAISS

```
Kết quả 1:
Độ dài nội dung: 98 ký tự
Metadata: {'source': '/app/storage/documents/3/1746255925_openai.txt', 'document_id': 23}
Nội dung: OpenAI là một tổ chức nghiên cứu và phát triển trí tuệ nhân tạo (AI) hàng đầu thế giới, được thành
```

### 7.2. Mẫu phản hồi tổng hợp từ LLM

```
OpenAI là một tổ chức nghiên cứu và phát triển trí tuệ nhân tạo (AI) hàng đầu thế giới, được thành lập vào năm 2015 bởi Elon Musk, Sam Altman và một nhóm nhà khoa học công nghệ khác [1]. Tổ chức này nổi bật với các sản phẩm như ChatGPT, một chatbot có khả năng hiểu và tạo ra văn bản tự nhiên như con người, cũng như các mô hình hình ảnh như DALL·E và Codex [1].

OpenAI theo đuổi các giá trị như sự minh bạch, an toàn và trách nhiệm trong phát triển AI, đồng thời thúc đẩy cộng đồng khoa học mở [2]. Ban đầu họ hoạt động như một tổ chức phi lợi nhuận, sau đó chuyển sang mô hình "có lợi nhuận giới hạn" để thu hút đầu tư lớn hơn [3].

Tài liệu tham khảo:

[1] OpenAI là một tổ chức nghiên cứu và phát triển trí tuệ nhân tạo (AI) hàng đầu thế giới, được thành lập vào năm 2015...
[2] nghệ khác, OpenAI đã và đang dẫn đầu xu hướng trong việc áp dụng AI vào đời sống, giáo dục, y tế...
[3] OpenAI sau đó chuyển sang mô hình "có lợi nhuận giới hạn" để thu hút nguồn vốn đầu tư lớn hơn nhưng...
``` 
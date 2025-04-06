"""
Module quản lý các template prompt cho LLM để tối ưu câu trả lời.
"""

class PromptTemplates:
    """Các template prompt cho việc tạo câu trả lời chính xác và đúng trọng tâm."""
    
    @staticmethod
    def focus_answer_template(context, question):
        """
        Template giúp LLM tập trung vào trọng tâm của câu hỏi.
        
        Args:
            context: Ngữ cảnh/thông tin từ tài liệu tìm được
            question: Câu hỏi của người dùng
            
        Returns:
            Prompt có cấu trúc giúp LLM tập trung vào trọng tâm
        """
        return f"""<s>[INST] Bạn là một chuyên gia kinh tế thông minh và chính xác. 
Dưới đây là một số thông tin từ cơ sở dữ liệu của bạn:

{context}

Bước 1: Phân tích câu hỏi để hiểu được TRỌNG TÂM chính mà người dùng đang hỏi:
Câu hỏi: {question}

Bước 2: Tìm thông tin liên quan TRỰC TIẾP đến trọng tâm câu hỏi trong ngữ cảnh được cung cấp.

Bước 3: Tạo câu trả lời ngắn gọn, súc tích, đúng trọng tâm dựa trên thông tin đã tìm được.
- KHÔNG thêm thông tin ngoài ngữ cảnh được cung cấp
- KHÔNG thêm giới thiệu dài dòng
- KHÔNG bao gồm "Dựa trên thông tin được cung cấp..." hoặc các cụm tương tự
- CHỈ trả lời những gì bạn biết từ ngữ cảnh, nếu không có đủ thông tin, hãy nói vậy
- Tối đa 5 câu (trừ khi câu hỏi yêu cầu chi tiết hơn)

Trả lời: [/INST]</s>"""

    @staticmethod
    def document_assessment_template(documents, question):
        """
        Template để đánh giá độ liên quan và hữu ích của tài liệu.
        
        Args:
            documents: Danh sách tài liệu tìm thấy
            question: Câu hỏi của người dùng
            
        Returns:
            Prompt để LLM đánh giá tài liệu
        """
        docs_text = "\n\n---DOCUMENT {0}---\n{1}"
        formatted_docs = "\n".join([docs_text.format(i+1, doc) for i, doc in enumerate(documents)])
        
        return f"""<s>[INST] Đánh giá độ liên quan và hữu ích của các tài liệu sau đối với câu hỏi.

Câu hỏi: {question}

{formatted_docs}

Nhiệm vụ của bạn:
1. Đánh giá từng tài liệu dựa trên độ liên quan đến trọng tâm câu hỏi.
2. Xếp hạng các tài liệu từ hữu ích nhất đến ít hữu ích nhất.
3. Trích xuất các phần quan trọng nhất liên quan đến câu hỏi, bỏ qua thông tin không liên quan.
4. Tổng hợp một ngữ cảnh ngắn gọn và súc tích chỉ từ thông tin liên quan nhất.

Trả về:
1. Đánh giá: Điểm đánh giá cho từng tài liệu (0-10)
2. Ngữ cảnh tối ưu: Chỉ bao gồm những phần quan trọng nhất liên quan đến câu hỏi [/INST]</s>"""

    @staticmethod
    def extract_focused_context(documents, question):
        """
        Template để trích xuất ngữ cảnh tập trung vào trọng tâm câu hỏi.
        
        Args:
            documents: Danh sách tài liệu tìm thấy
            question: Câu hỏi của người dùng
            
        Returns:
            Prompt để LLM trích xuất thông tin quan trọng
        """
        combined_docs = "\n\n".join([doc for doc in documents])
        
        return f"""<s>[INST] Phân tích câu hỏi và trích xuất thông tin chính xác từ tài liệu.

Câu hỏi: {question}

Tài liệu:
{combined_docs}

1. Phân tích trọng tâm câu hỏi. Câu hỏi đang yêu cầu thông tin gì chính xác?
2. Đọc kỹ tài liệu và tìm những phần liên quan trực tiếp đến trọng tâm đó.
3. Trích xuất CHÍNH XÁC các phần văn bản liên quan, loại bỏ thông tin thừa.
4. Tạo một ngữ cảnh cô đọng (tối đa 3 đoạn) chỉ chứa thông tin liên quan nhất.

Trả về NGẮN GỌN:
Ngữ cảnh liên quan: [Chỉ các phần văn bản liên quan trực tiếp, không thêm nhận xét] [/INST]</s>"""

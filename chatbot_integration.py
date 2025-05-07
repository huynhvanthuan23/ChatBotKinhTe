import os
import json
import asyncio
import sys
from typing import List, Dict, Any

# Thêm thư mục gốc vào sys.path để import từ app/services
sys.path.append(".")

# Import ChatbotService từ hệ thống
try:
    from app.services.chatbot import ChatbotService
    print("Đã import thành công ChatbotService từ app/services")
except ImportError as e:
    print(f"Không thể import ChatbotService: {e}")
    print("Script này cần được chạy từ thư mục gốc dự án và app/services/chatbot.py phải tồn tại.")
    sys.exit(1)

async def test_chatbot_retrieval(query: str, doc_ids: List[int] = None):
    """
    Test chức năng trích dẫn từ ChatbotService
    """
    print("\n=== TEST CHATBOTSERVICE ===")
    print(f"Query: {query}")
    if doc_ids:
        print(f"Document IDs: {doc_ids}")
    
    # Khởi tạo ChatbotService
    try:
        chatbot_service = ChatbotService()
        print("Đã khởi tạo ChatbotService thành công")
    except Exception as e:
        print(f"Lỗi khởi tạo ChatbotService: {e}")
        return
    
    # Gọi phương thức get_simple_retrieval
    try:
        print("Đang gọi get_simple_retrieval...")
        response = await chatbot_service.get_simple_retrieval(query, doc_ids)
        
        print("\n--- KẾT QUẢ TRẢ VỀ ---")
        print(f"Success: {response.get('success', False)}")
        print(f"Số lượng tài liệu tìm thấy: {response.get('doc_count', 0)}")
        
        # Hiển thị các trích dẫn
        citations = response.get('citations', [])
        print(f"\n--- CITATIONS ({len(citations)}) ---")
        
        for i, citation in enumerate(citations):
            print(f"\nCitation #{i+1}:")
            print(f"  Number: {citation.get('number', 'N/A')}")
            print(f"  Document ID: {citation.get('document_id', 'N/A')}")
            print(f"  Source: {citation.get('source', 'N/A')}")
            print(f"  Length: {citation.get('length', 0)} characters")
            content = citation.get('content', '')
            print(f"  Content: {content[:100]}..." if len(content) > 100 else f"  Content: {content}")
        
        # Hiển thị phản hồi cuối cùng
        print("\n--- PHẢN HỒI TỪ LLM ---")
        print(response.get('response', 'Không có phản hồi'))
        
        # Lưu kết quả vào file JSON
        output_file = "chatbot_response.json"
        with open(output_file, "w", encoding="utf-8") as f:
            json.dump(response, f, ensure_ascii=False, indent=2)
        print(f"\nĐã lưu phản hồi đầy đủ vào: {output_file}")
        
    except Exception as e:
        print(f"Lỗi khi gọi get_simple_retrieval: {e}")
        import traceback
        traceback.print_exc()

def show_usage():
    """
    Hiển thị hướng dẫn sử dụng
    """
    print(f"""
Sử dụng: python {sys.argv[0]} [query] [doc_id1,doc_id2,...]

Ví dụ:
  python {sys.argv[0]} "OpenAI là gì?" 23
  python {sys.argv[0]} "Kinh tế học là gì?" 14,25
  python {sys.argv[0]} "Trí tuệ nhân tạo" # Tìm trong tất cả tài liệu
    """)

async def main():
    """
    Hàm main chạy khi script được thực thi
    """
    # Kiểm tra tham số dòng lệnh
    if len(sys.argv) == 1 or "-h" in sys.argv or "--help" in sys.argv:
        show_usage()
        return
    
    query = ""
    doc_ids = None
    
    # Xử lý tham số
    if len(sys.argv) >= 2:
        query = sys.argv[1]
    
    if len(sys.argv) >= 3:
        try:
            # Xử lý danh sách document IDs
            doc_ids_str = sys.argv[2].split(",")
            doc_ids = [int(doc_id.strip()) for doc_id in doc_ids_str]
        except Exception as e:
            print(f"Lỗi khi xử lý Document IDs: {e}")
            show_usage()
            return
    
    # Chạy test
    await test_chatbot_retrieval(query, doc_ids)

# Chạy async main function
if __name__ == "__main__":
    asyncio.run(main()) 
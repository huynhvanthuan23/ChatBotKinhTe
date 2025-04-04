import asyncio
import os
import time
from dotenv import load_dotenv
from app.services.chatbot import ChatbotService

async def main():
    # Hiển thị thông tin cấu hình
    load_dotenv()
    print(f"MODEL_PATH: {os.getenv('MODEL_PATH')}")
    print(f"N_GPU_LAYERS: {os.getenv('N_GPU_LAYERS')}")
    print(f"N_CTX: {os.getenv('N_CTX')}")
    print(f"MAX_TOKENS: {os.getenv('MAX_TOKENS')}")
    
    # Khởi tạo chatbot service
    print("\nKhởi tạo ChatbotService...")
    chatbot = ChatbotService()
    
    # Test với câu hỏi về kinh tế
    test_questions = [
        "Kinh tế học là gì?",
        "Giải thích về lạm phát và ảnh hưởng của nó?",
        "Thế nào là tăng trưởng kinh tế bền vững?"
    ]
    
    # Test các câu hỏi
    for question in test_questions:
        print(f"\n----- Test câu hỏi: '{question}' -----")
        
        # Đo thời gian xử lý
        start_time = time.time()
        
        # Lấy câu trả lời
        response = await chatbot.get_answer(question)
        
        # Tính thời gian hoàn thành
        end_time = time.time()
        process_time = end_time - start_time
        
        # Hiển thị kết quả
        print(f"Câu hỏi: {question}")
        print(f"Câu trả lời: {response['answer']}")
        print(f"Thời gian xử lý: {process_time:.2f} giây")
        
        # Chờ 2 giây trước khi xử lý câu hỏi tiếp theo
        await asyncio.sleep(2)
    
    print("\nKết thúc test!")

if __name__ == "__main__":
    # Chạy test bất đồng bộ
    asyncio.run(main()) 
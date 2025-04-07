import asyncio
from app.services.chatbot import ChatbotService
import time

async def test_chatbot():
    print("Đang kiểm tra chatbot với GPU...")
    chatbot = ChatbotService()
    
    # Câu hỏi test
    query = "Ai là Chủ tịch Hồ Chí Minh?"
    
    start_time = time.time()
    result = await chatbot.get_answer(query)
    end_time = time.time()
    
    print(f"Thời gian xử lý: {result.get('processing_time', end_time - start_time):.2f} giây")
    print(f"Thời gian inference: {result.get('inference_time', 'không có')}")
    print("\nKết quả:")
    print(result.get("answer")[:200] + "...")
    
    # Thông tin thêm về GPU
    import torch
    if torch.cuda.is_available():
        print(f"\nThông tin GPU sau khi xử lý:")
        print(f"Bộ nhớ đã cấp phát: {torch.cuda.memory_allocated() / 1024**2:.2f} MB")
        print(f"Bộ nhớ đã dự trữ: {torch.cuda.memory_reserved() / 1024**2:.2f} MB")

if __name__ == "__main__":
    asyncio.run(test_chatbot()) 
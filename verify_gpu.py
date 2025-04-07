import os
import torch
import time
import gc
from app.core.config import settings

def check_gpu_usage():
    """Kiểm tra xem GPU có được sử dụng đúng cách không"""
    print("\n===== KIỂM TRA GPU =====")
    
    # Kiểm tra PyTorch CUDA
    if torch.cuda.is_available():
        print(f"PyTorch nhận diện CUDA: Có")
        print(f"Phiên bản CUDA: {torch.version.cuda}")
        print(f"Số lượng GPU: {torch.cuda.device_count()}")
        print(f"Tên GPU: {torch.cuda.get_device_name(0)}")
        print(f"Bộ nhớ đã cấp phát: {torch.cuda.memory_allocated(0) / 1024**2:.2f} MB")
        print(f"Bộ nhớ đã dự trữ: {torch.cuda.memory_reserved(0) / 1024**2:.2f} MB")
        
        # Kiểm tra xem CUDA có thực sự hoạt động không
        print("\nĐang kiểm tra GPU với PyTorch...")
        x = torch.rand(10000, 10000).cuda()
        start_time = time.time()
        result = torch.matmul(x, x.T)
        end_time = time.time()
        
        # Thêm một số thao tác để chắc chắn computation diễn ra
        result_sum = result.sum().item()
        
        print(f"Thời gian tính toán: {end_time - start_time:.4f} giây")
        print(f"Tổng kết quả: {result_sum}")
        
        # Giải phóng bộ nhớ
        del x, result
        torch.cuda.empty_cache()
        gc.collect()
        
        print(f"Bộ nhớ sau khi giải phóng: {torch.cuda.memory_allocated(0) / 1024**2:.2f} MB")
    else:
        print("PyTorch không nhận diện CUDA. Kiểm tra lại driver và cài đặt CUDA.")
        return

def test_llama_cpp():
    try:
        # Import LlamaCpp ở đây để tránh lỗi nếu chưa cài đặt
        from langchain_community.llms import LlamaCpp
        
        print("\n===== KIỂM TRA LLAMA-CPP GPU =====")
        
        # Đường dẫn đến model
        model_path = settings.MODEL_PATH
        
        if not os.path.exists(model_path):
            print(f"Không tìm thấy model tại {model_path}")
            return
        
        # Kiểm tra bộ nhớ GPU trước khi nạp model
        print(f"Bộ nhớ GPU trước khi nạp model: {torch.cuda.memory_allocated(0) / 1024**2:.2f} MB")
        
        # Nạp model với n_gpu_layers=32
        print(f"Đang nạp model {model_path} với 32 GPU layers...")
        llm = LlamaCpp(
            model_path=model_path,
            n_gpu_layers=32,
            n_ctx=2048,
            n_batch=512,
            f16_kv=True,
            verbose=True
        )
        
        # Kiểm tra bộ nhớ GPU sau khi nạp model
        gpu_mem_after = torch.cuda.memory_allocated(0) / 1024**2
        print(f"Bộ nhớ GPU sau khi nạp model: {gpu_mem_after:.2f} MB")
        
        # Nếu bộ nhớ GPU tăng, model đang sử dụng GPU
        if gpu_mem_after > 10:  # Thường model sẽ chiếm ít nhất vài MB
            print("✅ Model đang sử dụng GPU!")
        else:
            print("❌ Model KHÔNG sử dụng GPU! Có thể cần cài đặt lại llama-cpp với CUDA support")
        
        # Thực hiện test inference
        print("\nĐang chạy test inference...")
        start_time = time.time()
        output = llm("Trả lời ngắn gọn: Ai là chủ tịch Hồ Chí Minh?")
        end_time = time.time()
        
        print(f"Kết quả: {output}")
        print(f"Thời gian inference: {end_time - start_time:.4f} giây")
        
    except Exception as e:
        print(f"Lỗi khi kiểm tra llama-cpp: {str(e)}")

if __name__ == "__main__":
    # Kiểm tra cấu hình GPU
    check_gpu_usage()
    
    # Kiểm tra llama-cpp với GPU
    test_llama_cpp() 
import os
import torch
import subprocess
import time
from langchain_community.llms import LlamaCpp

def check_torch_cuda():
    """Kiểm tra CUDA có hoạt động với PyTorch không"""
    print("\n===== KIỂM TRA PYTORCH + CUDA =====")
    if torch.cuda.is_available():
        print(f"✅ CUDA available: {torch.cuda.is_available()}")
        print(f"✅ CUDA version: {torch.version.cuda}")
        print(f"✅ Device count: {torch.cuda.device_count()}")
        for i in range(torch.cuda.device_count()):
            print(f"✅ Device {i}: {torch.cuda.get_device_name(i)}")
            print(f"   - Memory: {torch.cuda.get_device_properties(i).total_memory / 1024**3:.2f} GB")
            
        # Kiểm tra tensor có thể tạo trên GPU
        try:
            x = torch.rand(1000, 1000).cuda()
            print(f"✅ Successfully created tensor on GPU with shape {x.shape}")
            del x
            torch.cuda.empty_cache()
        except Exception as e:
            print(f"❌ Failed to create tensor on GPU: {e}")
        
        return True
    else:
        print("❌ CUDA not available")
        return False

def check_llama_cpp_python():
    """Kiểm tra cài đặt llama-cpp-python"""
    print("\n===== KIỂM TRA LLAMA-CPP-PYTHON =====")
    try:
        import langchain_community.llms.llamacpp
        print("✅ LlamaCpp module available")
        
        # Kiểm tra LlamaCpp có được cài đặt với CUDA không
        try:
            result = subprocess.run(["pip", "show", "llama-cpp-python"], capture_output=True, text=True)
            print(result.stdout)
            if "cuda" in result.stdout.lower():
                print("✅ llama-cpp-python likely compiled with CUDA support")
            else:
                print("❌ llama-cpp-python may NOT be compiled with CUDA support")
                return False
        except Exception as e:
            print(f"❌ Error checking llama-cpp-python installation: {e}")
        
        return True
    except ImportError:
        print("❌ llama-cpp-python not installed")
        return False

def reinstall_llama_cpp_python():
    """Cài đặt lại llama-cpp-python với hỗ trợ CUDA"""
    print("\n===== CÀI ĐẶT LẠI LLAMA-CPP-PYTHON =====")
    
    choice = input("Bạn có muốn gỡ cài đặt và cài đặt lại llama-cpp-python với hỗ trợ CUDA? (y/n): ")
    if choice.lower() != 'y':
        print("Đã hủy cài đặt lại.")
        return
    
    try:
        print("Gỡ cài đặt llama-cpp-python...")
        subprocess.run(["pip", "uninstall", "-y", "llama-cpp-python"], check=True)
        
        print("Cài đặt lại llama-cpp-python với hỗ trợ CUDA...")
        env = os.environ.copy()
        env["CMAKE_ARGS"] = "-DLLAMA_CUBLAS=on"
        env["FORCE_CMAKE"] = "1"
        
        # Windows cần setting set CMAKE_ARGS
        print("Lưu ý: Nếu đây là Windows và lệnh không hoạt động, mở PowerShell và chạy:")
        print("set CMAKE_ARGS=-DLLAMA_CUBLAS=on")
        print("pip install llama-cpp-python --force-reinstall --no-cache-dir")
        
        subprocess.run(["pip", "install", "llama-cpp-python", "--force-reinstall", "--no-cache-dir"], 
                      env=env, check=True)
        
        print("✅ Đã cài đặt lại llama-cpp-python với hỗ trợ CUDA")
    except Exception as e:
        print(f"❌ Lỗi khi cài đặt lại: {e}")

def test_llamacpp_model():
    """Kiểm tra LlamaCpp có thể chạy model trên GPU không"""
    print("\n===== KIỂM TRA LLAMACPP MODEL =====")
    
    model_path = input("Nhập đường dẫn đến model GGUF (mặc định: models/mistral-7b-instruct-v0.1.Q2_K.gguf): ")
    if not model_path:
        model_path = "models/mistral-7b-instruct-v0.1.Q2_K.gguf"
    
    if not os.path.exists(model_path):
        print(f"❌ Không tìm thấy file model: {model_path}")
        return
    
    try:
        print(f"Tải model từ {model_path}...")
        
        # Ghi nhớ bộ nhớ GPU trước khi tải model
        before_load = torch.cuda.memory_allocated() / 1024**2 if torch.cuda.is_available() else 0
        
        model = LlamaCpp(
            model_path=model_path,
            temperature=0.1,
            max_tokens=10,
            n_ctx=512,
            n_gpu_layers=32,  # Sử dụng tất cả các layers trên GPU
            n_batch=512,
            f16_kv=True,
            use_mlock=False,
            use_mmap=False,
            verbose=True,
            seed=42
        )
        
        # Ghi nhớ bộ nhớ GPU sau khi tải model
        after_load = torch.cuda.memory_allocated() / 1024**2 if torch.cuda.is_available() else 0
        
        print("Mô hình đã được tải thành công! Thử nghiệm tạo văn bản...")
        print(f"Bộ nhớ GPU sau khi tải model: {after_load:.2f}MB (tăng {after_load - before_load:.2f}MB)")
        
        if after_load - before_load > 50:
            print("✅ Model đã được tải lên GPU (bộ nhớ GPU tăng > 50MB)")
        else:
            print("⚠️ Model có thể chưa được tải lên GPU (bộ nhớ GPU tăng < 50MB)")
        
        # Suy luận
        start_time = time.time()
        output = model("2+2=")
        end_time = time.time()
        
        # Ghi nhớ bộ nhớ GPU sau khi suy luận
        after_inference = torch.cuda.memory_allocated() / 1024**2 if torch.cuda.is_available() else 0
        
        print(f"Kết quả: {output}")
        print(f"Thời gian suy luận: {end_time - start_time:.4f} giây")
        print(f"Bộ nhớ GPU sau suy luận: {after_inference:.2f}MB (delta vs load: {after_inference - after_load:.2f}MB)")
        
        if end_time - start_time < 1.0:
            print("✅ Thời gian suy luận nhanh -> GPU đang hoạt động tốt!")
        else:
            print("⚠️ Thời gian suy luận chậm -> GPU có thể không được sử dụng hiệu quả")
        
    except Exception as e:
        print(f"❌ Lỗi khi kiểm tra model: {e}")

def update_env_config():
    """Cập nhật file .env với cấu hình tối ưu cho GPU"""
    print("\n===== CẬP NHẬT FILE .ENV =====")
    
    if not os.path.exists(".env"):
        print("❌ Không tìm thấy file .env")
        return
    
    try:
        # Đọc file .env
        with open(".env", "r", encoding="utf-8") as f:
            content = f.read()
            
        # Cập nhật các tham số chính
        updates = {
            "N_GPU_LAYERS": "32",
            "N_BATCH": "512", 
            "F16_KV": "true",
            "USE_MMAP": "false",
            "USE_MLOCK": "false"
        }
        
        # Tạo file .env mới
        new_content = []
        updated = set()
        
        for line in content.splitlines():
            line_processed = False
            
            for key, value in updates.items():
                if line.strip().startswith(f"{key}="):
                    new_content.append(f"{key}={value}")
                    updated.add(key)
                    line_processed = True
                    break
                    
            if not line_processed:
                new_content.append(line)
        
        # Thêm các tham số chưa có
        for key, value in updates.items():
            if key not in updated:
                new_content.append(f"{key}={value}")
        
        # Ghi file mới
        with open(".env", "w", encoding="utf-8") as f:
            f.write("\n".join(new_content))
        
        print("✅ Đã cập nhật file .env với cấu hình tối ưu cho GPU")
        print("Các tham số đã được cập nhật:")
        for key, value in updates.items():
            print(f"  - {key}={value}")
    except Exception as e:
        print(f"❌ Lỗi khi cập nhật file .env: {e}")

def update_chatbot_gpu_code():
    """Cập nhật code để buộc sử dụng GPU"""
    print("\n===== CẬP NHẬT CODE CHATBOT.PY =====")
    
    file_path = "app/services/chatbot.py"
    if not os.path.exists(file_path):
        print(f"❌ Không tìm thấy file {file_path}")
        return
    
    try:
        # Tạo file backup
        backup_path = f"{file_path}.bak"
        subprocess.run(["copy", file_path, backup_path], shell=True, check=True)
        print(f"✅ Đã tạo backup tại {backup_path}")
        
        # Đọc file
        with open(file_path, "r", encoding="utf-8") as f:
            content = f.read()
        
        # Tìm phần khởi tạo LLM
        if "self._llm = LlamaCpp(" in content:
            # Chuẩn bị code mới
            new_code = """
                # Cấu hình LLM tối ưu cho GPU
                self._llm = LlamaCpp(
                    model_path=settings.MODEL_PATH,
                    temperature=0.1,
                    max_tokens=settings.MAX_TOKENS,
                    n_ctx=settings.N_CTX,
                    n_gpu_layers=32,  # Force tất cả layers lên GPU
                    n_batch=512,  # Batch size lớn cho GPU
                    f16_kv=True,  # Luôn bật f16_kv khi có GPU
                    use_mlock=False,
                    use_mmap=False,
                    verbose=True,
                    seed=42
                )
            """
            
            # Thay thế đoạn cấu hình LLM
            import re
            new_content = re.sub(
                r'self\._llm = LlamaCpp\([^)]+\)',
                new_code.strip(),
                content,
                flags=re.DOTALL
            )
            
            # Ghi file mới
            with open(file_path, "w", encoding="utf-8") as f:
                f.write(new_content)
                
            print("✅ Đã cập nhật cấu hình LLM trong file chatbot.py")
        else:
            print("❌ Không tìm thấy phần khởi tạo LLM trong file")
            
    except Exception as e:
        print(f"❌ Lỗi khi cập nhật code: {e}")

if __name__ == "__main__":
    print("===== KIỂM TRA VÀ SỬA LỖI GPU =====")
    
    has_cuda = check_torch_cuda()
    if not has_cuda:
        print("⚠️ CUDA không khả dụng - Không thể sử dụng GPU")
        exit(1)
    
    has_llama_cpp = check_llama_cpp_python()
    if not has_llama_cpp:
        reinstall_llama_cpp_python()
    
    update_env_config()
    update_chatbot_gpu_code()
    
    choice = input("\nBạn có muốn kiểm tra model với GPU không? (y/n): ")
    if choice.lower() == 'y':
        test_llamacpp_model()
    
    print("\n===== HƯỚNG DẪN TIẾP THEO =====")
    print("1. Khởi động lại server: python main.py")
    print("2. Kiểm tra tình trạng GPU: http://localhost:8080/api/v1/acceleration")
    print("3. Test chatbot: http://localhost:8080/api/v1/chat?query=xin chào") 
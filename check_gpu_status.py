import torch
import platform
import subprocess
import os

def check_gpu_status():
    """Kiểm tra tình trạng và loại GPU hiện có"""
    print("\n===== KIỂM TRA GPU =====")
    
    if not torch.cuda.is_available():
        print("❌ CUDA không khả dụng - Sẽ sử dụng CPU")
        return False, None
    
    print("✅ CUDA có sẵn!")
    
    device_count = torch.cuda.device_count()
    print(f"- Số lượng GPU: {device_count}")
    
    gpu_info = {}
    
    for i in range(device_count):
        device_name = torch.cuda.get_device_name(i)
        properties = torch.cuda.get_device_properties(i)
        
        gpu_info[i] = {
            "name": device_name,
            "total_memory_gb": round(properties.total_memory / (1024**3), 2),
            "compute_capability": f"{properties.major}.{properties.minor}",
            "is_gtx": "GTX" in device_name.upper()
        }
        
        print(f"\nGPU #{i}: {device_name}")
        print(f"- Total memory: {gpu_info[i]['total_memory_gb']:.2f} GB")
        print(f"- Compute capability: {gpu_info[i]['compute_capability']}")
        print(f"- Type: {'GTX GPU' if gpu_info[i]['is_gtx'] else 'Other GPU'}")
    
    # Kiểm tra thông tin chi tiết với nvidia-smi
    try:
        if platform.system() == "Windows":
            print("\n===== THÔNG TIN NVIDIA-SMI =====")
            nvidia_smi = subprocess.check_output("nvidia-smi", shell=True).decode('utf-8')
            print(nvidia_smi)
    except:
        print("Không thể chạy nvidia-smi")
    
    # Kiểm tra memory và hỗ trợ CUDA
    print("\n===== KIỂM TRA MEMORY =====")
    torch.cuda.empty_cache()
    
    # Tạo tensor nhỏ trên GPU để kiểm tra
    try:
        test_tensor = torch.zeros(100, 100).cuda()
        print("✅ Tạo tensor thành công trên GPU")
        
        allocated = torch.cuda.memory_allocated(0) / (1024**2)
        reserved = torch.cuda.memory_reserved(0) / (1024**2)
        
        print(f"- Bộ nhớ đã cấp phát: {allocated:.2f} MB")
        print(f"- Bộ nhớ đã đặt trước: {reserved:.2f} MB")
        
        del test_tensor
        torch.cuda.empty_cache()
    except Exception as e:
        print(f"❌ Lỗi khi tạo tensor trên GPU: {e}")
    
    return True, gpu_info

if __name__ == "__main__":
    has_gpu, gpu_info = check_gpu_status()
    
    print("\n===== KHUYẾN NGHỊ =====")
    if has_gpu:
        is_gtx = any(info.get("is_gtx", False) for info in gpu_info.values())
        
        if is_gtx:
            print("✅ Phát hiện GTX GPU - Model sẽ ưu tiên chạy trên GPU")
            print("Khuyến nghị: n_gpu_layers=24, n_batch=512, f16_kv=true")
        else:
            print("✅ Phát hiện GPU thông thường - Model sẽ chạy trên GPU")
            print("Khuyến nghị: n_gpu_layers=32, n_batch=512, f16_kv=true")
    else:
        print("❌ Không phát hiện GPU - Model sẽ chạy trên CPU")
        print("Khuyến nghị: n_gpu_layers=0, f16_kv=false")
    
    # Kiểm tra file .env để đảm bảo cấu hình đúng
    env_file = ".env"
    if os.path.exists(env_file):
        print("\n===== KIỂM TRA FILE .ENV =====")
        with open(env_file, "r") as f:
            env_content = f.read()
            
            # Kiểm tra N_GPU_LAYERS
            if "N_GPU_LAYERS" in env_content:
                print("✅ Đã tìm thấy tham số N_GPU_LAYERS")
            else:
                print("❌ Không tìm thấy tham số N_GPU_LAYERS")
            
            # Kiểm tra F16_KV
            if "F16_KV" in env_content:
                print("✅ Đã tìm thấy tham số F16_KV")
            else:
                print("❌ Không tìm thấy tham số F16_KV") 
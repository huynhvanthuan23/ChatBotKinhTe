import os
import torch
import time
import sys
import argparse
from pathlib import Path

def print_gpu_info():
    """In thông tin GPU chi tiết"""
    if not torch.cuda.is_available():
        print("Không tìm thấy GPU hỗ trợ CUDA")
        return False
        
    print("\n===== THÔNG TIN GPU =====")
    print(f"CUDA available: {torch.cuda.is_available()}")
    print(f"CUDA version: {torch.version.cuda}")
    print(f"PyTorch version: {torch.__version__}")
    
    device_count = torch.cuda.device_count()
    print(f"Số lượng GPU: {device_count}")
    
    for i in range(device_count):
        print(f"\nGPU {i}: {torch.cuda.get_device_name(i)}")
        props = torch.cuda.get_device_properties(i)
        print(f"  Compute capability: {props.major}.{props.minor}")
        print(f"  Tổng bộ nhớ: {props.total_memory / 1024**3:.2f} GB")
        print(f"  Multi-processors: {props.multi_processor_count}")
        
    # Kiểm tra memory
    print(f"\nBộ nhớ đã cấp phát: {torch.cuda.memory_allocated() / 1024**2:.2f} MB")
    print(f"Bộ nhớ đã dự trữ: {torch.cuda.memory_reserved() / 1024**2:.2f} MB")
    
    return True

def optimize_for_inference():
    """Tối ưu hóa PyTorch cho inference"""
    print("\n===== TỐI ƯU HÓA GPU CHO INFERENCE =====")
    
    # Tắt gradient calculation cho inference
    torch.set_grad_enabled(False)
    print("✓ Đã tắt gradient calculation")
    
    # Sử dụng TF32 nếu có thể (chỉ cho GPU compute capability 8.0+)
    if torch.cuda.is_available():
        props = torch.cuda.get_device_properties(0)
        if props.major >= 8:
            torch.backends.cuda.matmul.allow_tf32 = True
            torch.backends.cudnn.allow_tf32 = True
            print("✓ Đã bật TF32 (Tensor Float 32)")
    
    # Đặt cudnn benchmark mode
    torch.backends.cudnn.benchmark = True
    print("✓ Đã bật cuDNN benchmark mode")
    
    # Đặt cudnn deterministic mode về False để tăng tốc độ
    torch.backends.cudnn.deterministic = False
    print("✓ Đã tắt cuDNN deterministic mode")
    
    print("\nTối ưu hóa GPU hoàn tất!")

def tune_gpu_memory():
    """Tinh chỉnh và giải phóng bộ nhớ GPU"""
    if not torch.cuda.is_available():
        return
        
    print("\n===== GIẢI PHÓNG BỘ NHỚ GPU =====")
    
    # Thông tin memory trước khi giải phóng
    allocated_before = torch.cuda.memory_allocated() / 1024**2
    reserved_before = torch.cuda.memory_reserved() / 1024**2
    print(f"Trước khi giải phóng:")
    print(f"  Bộ nhớ đã cấp phát: {allocated_before:.2f} MB")
    print(f"  Bộ nhớ đã dự trữ: {reserved_before:.2f} MB")
    
    # Giải phóng cache
    torch.cuda.empty_cache()
    
    # Thông tin memory sau khi giải phóng
    allocated_after = torch.cuda.memory_allocated() / 1024**2
    reserved_after = torch.cuda.memory_reserved() / 1024**2
    print(f"Sau khi giải phóng:")
    print(f"  Bộ nhớ đã cấp phát: {allocated_after:.2f} MB")
    print(f"  Bộ nhớ đã dự trữ: {reserved_after:.2f} MB")
    
    # Tổng kết
    print(f"Đã giải phóng:")
    print(f"  Bộ nhớ cấp phát: {allocated_before - allocated_after:.2f} MB")
    print(f"  Bộ nhớ dự trữ: {reserved_before - reserved_after:.2f} MB")

def check_llama_cpp():
    """Kiểm tra cài đặt llama-cpp-python với CUDA"""
    print("\n===== KIỂM TRA LLAMA-CPP-PYTHON =====")
    
    # Kiểm tra phiên bản
    try:
        import llama_cpp
        print(f"Phiên bản llama-cpp-python: {llama_cpp.__version__}")
        
        # Kiểm tra CUDA support
        if hasattr(llama_cpp, "_LIB") and hasattr(llama_cpp._LIB, "llama_backend_cuda"):
            is_cuda = llama_cpp._LIB.llama_backend_cuda()
            print(f"CUDA support: {'Có' if is_cuda else 'Không'}")
        else:
            print("Không thể kiểm tra CUDA support trực tiếp")
            
        print("Kiểm tra chi tiết llama_cpp...")
        # Lấy thông tin chi tiết về llama-cpp
        import subprocess
        pip_show = subprocess.check_output(["pip", "show", "llama-cpp-python"], text=True)
        print(pip_show)
        
    except ImportError:
        print("Không thể import llama_cpp")
    except Exception as e:
        print(f"Lỗi khi kiểm tra llama-cpp-python: {str(e)}")

def update_env_file(n_gpu_layers=None, n_ctx=None, n_batch=None):
    """Cập nhật file .env với cấu hình mới"""
    env_path = Path(".env")
    if not env_path.exists():
        print("Không tìm thấy file .env")
        return
        
    print("\n===== CẬP NHẬT CẤU HÌNH .ENV =====")
    
    with open(env_path, "r", encoding="utf-8") as f:
        lines = f.readlines()
    
    updated_lines = []
    updated = set()
    
    for line in lines:
        if n_gpu_layers is not None and line.startswith("N_GPU_LAYERS="):
            updated_lines.append(f"N_GPU_LAYERS={n_gpu_layers}\n")
            updated.add("N_GPU_LAYERS")
        elif n_ctx is not None and line.startswith("N_CTX="):
            updated_lines.append(f"N_CTX={n_ctx}\n")
            updated.add("N_CTX")
        elif n_batch is not None and line.startswith("N_BATCH="):
            updated_lines.append(f"N_BATCH={n_batch}\n")
            updated.add("N_BATCH")
        else:
            updated_lines.append(line)
    
    # Thêm các biến mới nếu chưa có
    if n_gpu_layers is not None and "N_GPU_LAYERS" not in updated:
        updated_lines.append(f"N_GPU_LAYERS={n_gpu_layers}\n")
    if n_ctx is not None and "N_CTX" not in updated:
        updated_lines.append(f"N_CTX={n_ctx}\n")
    if n_batch is not None and "N_BATCH" not in updated:
        updated_lines.append(f"N_BATCH={n_batch}\n")
    
    with open(env_path, "w", encoding="utf-8") as f:
        f.writelines(updated_lines)
    
    print("Đã cập nhật file .env thành công")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Công cụ tối ưu hóa GPU cho ChatBotKinhTe")
    parser.add_argument("--info", action="store_true", help="Hiển thị thông tin GPU")
    parser.add_argument("--optimize", action="store_true", help="Tối ưu hóa cấu hình GPU")
    parser.add_argument("--clean", action="store_true", help="Giải phóng bộ nhớ GPU")
    parser.add_argument("--check-llama", action="store_true", help="Kiểm tra cài đặt llama-cpp")
    parser.add_argument("--update-env", action="store_true", help="Cập nhật file .env")
    parser.add_argument("--n-gpu-layers", type=int, help="Số lớp GPU")
    parser.add_argument("--n-ctx", type=int, help="Context window size")
    parser.add_argument("--n-batch", type=int, help="Batch size")
    
    args = parser.parse_args()
    
    # Nếu không có tham số nào, hiển thị thông tin GPU
    if len(sys.argv) == 1:
        print_gpu_info()
        optimize_for_inference()
        check_llama_cpp()
    else:
        if args.info:
            print_gpu_info()
        if args.optimize:
            optimize_for_inference()
        if args.clean:
            tune_gpu_memory()
        if args.check_llama:
            check_llama_cpp()
        if args.update_env:
            update_env_file(args.n_gpu_layers, args.n_ctx, args.n_batch) 
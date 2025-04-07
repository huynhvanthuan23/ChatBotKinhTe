import torch
import time
from langchain_community.llms import LlamaCpp
import gc

def test_inference(model_path, n_gpu_layers, batch_size, query="Thế nào là kinh tế học?"):
    """Kiểm tra tốc độ suy luận với các cấu hình khác nhau"""
    
    # Đảm bảo GPU memory được giải phóng trước khi test
    if torch.cuda.is_available():
        torch.cuda.empty_cache()
        gc.collect()
    
    # Khởi tạo model
    start_time = time.time()
    model = LlamaCpp(
        model_path=model_path,
        temperature=0.1,
        max_tokens=256,  # Standard for benchmarking
        n_ctx=2048,
        n_gpu_layers=n_gpu_layers,
        n_batch=batch_size,
        f16_kv=torch.cuda.is_available(),
        use_mlock=False,
        use_mmap=False,
        verbose=True
    )
    load_time = time.time() - start_time
    
    # Đo lường thời gian suy luận
    start_time = time.time()
    response = model(query)
    inference_time = time.time() - start_time
    
    # Giải phóng model
    del model
    if torch.cuda.is_available():
        torch.cuda.empty_cache()
        gc.collect()
    
    return {
        "load_time": load_time,
        "inference_time": inference_time,
        "response_length": len(response),
        "total_time": load_time + inference_time
    }

def benchmark_configurations(model_path):
    """Benchmark nhiều cấu hình để tìm cấu hình tối ưu"""
    print("===== BENCHMARK CÁC CẤU HÌNH CHO GPU =====")
    
    if not torch.cuda.is_available():
        print("CUDA không khả dụng. Benchmark sẽ chỉ chạy trên CPU.")
        return
    
    # Các cấu hình cần kiểm tra
    configs = [
        {"n_gpu_layers": 0, "batch_size": 512, "name": "CPU only"},
        {"n_gpu_layers": 8, "batch_size": 256, "name": "8 GPU layers"},
        {"n_gpu_layers": 16, "batch_size": 256, "name": "16 GPU layers"},
        {"n_gpu_layers": 20, "batch_size": 256, "name": "20 GPU layers"},
        {"n_gpu_layers": 24, "batch_size": 256, "name": "24 GPU layers"},
        {"n_gpu_layers": 32, "batch_size": 256, "name": "32 GPU layers"},
        {"n_gpu_layers": 16, "batch_size": 512, "name": "16 GPU layers, 512 batch"},
        {"n_gpu_layers": 20, "batch_size": 512, "name": "20 GPU layers, 512 batch"}
    ]
    
    results = []
    
    print(f"Testing {len(configs)} configurations...")
    print("This may take a few minutes...")
    
    # Thực hiện benchmark
    for config in configs:
        print(f"\nTesting: {config['name']}")
        try:
            result = test_inference(
                model_path=model_path,
                n_gpu_layers=config["n_gpu_layers"],
                batch_size=config["batch_size"]
            )
            
            result.update(config)
            results.append(result)
            
            print(f"  Load time: {result['load_time']:.2f}s")
            print(f"  Inference time: {result['inference_time']:.2f}s")
            print(f"  Total time: {result['total_time']:.2f}s")
            
        except Exception as e:
            print(f"  Error with configuration {config['name']}: {e}")
    
    # Sắp xếp kết quả theo thời gian suy luận
    results.sort(key=lambda x: x["inference_time"])
    
    print("\n===== KẾT QUẢ BENCHMARK =====")
    print("Xếp hạng theo thời gian suy luận (thấp = tốt):")
    
    for i, result in enumerate(results):
        print(f"{i+1}. {result['name']}: {result['inference_time']:.2f}s (tổng: {result['total_time']:.2f}s)")
    
    if results:
        best = results[0]
        print(f"\nCấu hình tốt nhất: {best['name']}")
        print(f"  n_gpu_layers={best['n_gpu_layers']}")
        print(f"  n_batch={best['batch_size']}")
        print(f"\nKhuyến nghị: Cập nhật file .env với:")
        print(f"N_GPU_LAYERS={best['n_gpu_layers']}")
        print(f"N_BATCH={best['batch_size']}")
    
if __name__ == "__main__":
    model_path = input("Nhập đường dẫn đến model (mặc định: models/mistral-7b-instruct-v0.1.Q2_K.gguf): ")
    if not model_path:
        model_path = "models/mistral-7b-instruct-v0.1.Q2_K.gguf"
    
    benchmark_configurations(model_path) 
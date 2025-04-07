from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
from langchain.prompts import PromptTemplate
from langchain_community.llms import LlamaCpp
from typing import Dict, Any, Optional, Tuple
from app.core.config import settings
from app.core.logger import get_logger
import os
import time
import torch
import gc
import traceback

# Cấu hình logging
logger = get_logger(__name__)

class ChatbotService:
    _instance = None
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(ChatbotService, cls).__new__(cls)
            cls._instance._initialized = False
            cls._instance._llm = None
            cls._instance._retry_count = 0
        return cls._instance
    
    def __init__(self):
        # Các thuộc tính sẽ được khởi tạo trong _initialize_components
        pass
    
    def _initialize_components(self):
        """Initialize components at startup"""
        if self._initialized:
            return
            
        logger.info("Initializing ChatbotService components...")
        
        try:
            # Đầu tiên, giải phóng bộ nhớ GPU
            self._clean_gpu_memory()
            
            # Kiểm tra paths
            if not os.path.exists(settings.DB_FAISS_PATH):
                raise FileNotFoundError(f"Vector database not found: {settings.DB_FAISS_PATH}")
                
            if not os.path.exists(settings.MODEL_PATH):
                raise FileNotFoundError(f"LLM model not found: {settings.MODEL_PATH}")
            
            # Kiểm tra GPU
            has_gpu = torch.cuda.is_available()
            if has_gpu:
                gpu_name = torch.cuda.get_device_name(0)
                gpu_memory = torch.cuda.get_device_properties(0).total_memory / (1024**3)
                logger.info(f"GPU detected: {gpu_name} with {gpu_memory:.2f}GB VRAM")
            else:
                logger.info("No GPU detected, using CPU only")
            
            # Tối ưu embedding model để sử dụng GPU
            self._embeddings = HuggingFaceEmbeddings(
                model_name=settings.EMBEDDING_MODEL,
                model_kwargs={"device": "cuda"}
            )
            logger.info(f"Loaded embedding model: {settings.EMBEDDING_MODEL}")
            
            # Tải vector store
            self._db = FAISS.load_local(
                settings.DB_FAISS_PATH, 
                self._embeddings, 
                allow_dangerous_deserialization=True
            )
            logger.info(f"Loaded vector store from {settings.DB_FAISS_PATH}")
            
            # Tạo prompt template - cải thiện để đưa ra câu trả lời chính xác hơn
            custom_prompt = """[INST] <<SYS>>
Bạn là trợ lý AI chuyên về kinh tế. Hãy trả lời câu hỏi dựa vào thông tin được cung cấp.
Phân tích thông tin trong ngữ cảnh và tìm nội dung liên quan đến câu hỏi.
Nếu không tìm thấy thông tin liên quan, hãy nói 'Tôi không tìm thấy thông tin liên quan'.
<</SYS>>

Ngữ cảnh: {context}
Câu hỏi: {question} [/INST]"""
        
            self._prompt = PromptTemplate(template=custom_prompt, input_variables=["context", "question"])
            
            # Tối ưu hóa cấu hình cho GPU
            if has_gpu:
                # Cấu hình tối ưu cho GPU hiện đại
                n_gpu_layers = 32  # Tăng số lớp GPU
                n_batch = 512      # Tăng batch size
                n_ctx = 4096       # Tăng context window
                is_f16 = True      # Sử dụng f16 cho memory efficiency
                
                logger.info(f"GPU config: layers={n_gpu_layers}, batch={n_batch}, ctx={n_ctx}, f16={is_f16}")
            else:
                # Cấu hình CPU
                n_gpu_layers = 0
                n_batch = 512
                n_ctx = settings.N_CTX
                is_f16 = False
                
                logger.info("Using CPU configuration")
            
            # Tải GGUF model
            logger.info(f"Loading GGUF model from {settings.MODEL_PATH}")
            
            try:
                # Cấu hình LLM với tham số tối ưu cho GPU
                logger.info(f"Attempting to load model with n_gpu_layers={n_gpu_layers}")
                
                # Log thông tin GPU trước khi load model
                if has_gpu:
                    logger.info(f"CUDA version: {torch.version.cuda}")
                    logger.info(f"GPU available: {torch.cuda.is_available()}")
                    logger.info(f"Total GPU memory: {torch.cuda.get_device_properties(0).total_memory / 1024**3:.2f}GB")
                    logger.info(f"Current GPU memory allocated: {torch.cuda.memory_allocated() / 1024**2:.2f}MB")
                
                # Cấu hình tham số với tối ưu cho GPU
                self._llm = LlamaCpp(
                    model_path=settings.MODEL_PATH,
                    temperature=0.1,
                    max_tokens=256,
                    n_ctx=n_ctx,
                    n_gpu_layers=n_gpu_layers,
                    n_batch=n_batch,
                    f16_kv=is_f16,
                    use_mlock=True,  # Sử dụng mlock để tăng tốc
                    use_mmap=True,   # Sử dụng mmap cho model loading
                    verbose=True,    # Bật verbose khi debug
                    seed=42,
                    n_threads=6,     # Số luồng CPU tối ưu
                    last_n_tokens_size=64,  # Tối ưu cho memory usage
                    verbose_prompt=False   # Tắt verbose prompt khi xuất logs
                )
                
                logger.info("Model loaded successfully with GPU acceleration")
                
                # Kiểm tra nhanh
                test_prompt = "Trả lời thật ngắn gọn: 2+2 bằng bao nhiêu?"
                start_time = time.time()
                test_result = self._llm(test_prompt)
                end_time = time.time()
                
                inference_time = end_time - start_time
                logger.info(f"Test inference completed in {inference_time:.4f} seconds with result: {test_result[:20]}...")
                
                # Kiểm tra bộ nhớ GPU để xác nhận
                if has_gpu:
                    allocated_memory = torch.cuda.memory_allocated() / 1024**2
                    logger.info(f"GPU memory after model load: {allocated_memory:.2f}MB")
                    logger.info(f"CUDA is being used: {'Yes' if allocated_memory > 0 else 'No'}")
                
            except Exception as e:
                logger.error(f"Failed to load model: {str(e)}")
                logger.error(traceback.format_exc())
                raise
            
            # Cấu hình retriever - số documents tối ưu và tăng tốc độ
            self._retriever = self._db.as_retriever(
                search_type="similarity_score_threshold",  # Tìm theo ngưỡng tương đồng
                search_kwargs={
                    "k": 3,  # Giảm số documents để tránh context quá lớn
                    "score_threshold": 0.5,  # Chỉ lấy documents có độ tương đồng cao
                    "fetch_k": 15  # Tìm kiếm từ pool lớn hơn rồi lọc sau
                }
            )
            
            self._initialized = True
            logger.info("ChatbotService components initialized successfully")
            
        except Exception as e:
            logger.error(f"Error initializing ChatbotService components: {str(e)}")
            logger.error(traceback.format_exc())
            raise
    
    def _clean_gpu_memory(self):
        """Giải phóng bộ nhớ GPU triệt để"""
        if torch.cuda.is_available():
            try:
                # Giải phóng model hiện tại nếu có
                if hasattr(self, '_llm') and self._llm is not None:
                    del self._llm
                    self._llm = None
                
                # Giải phóng memory
                torch.cuda.empty_cache()
                gc.collect()
                
                # Ghi log thông tin memory
                allocated = torch.cuda.memory_allocated() / 1024**2
                reserved = torch.cuda.memory_reserved() / 1024**2
                logger.info(f"Cleaned GPU memory: allocated={allocated:.2f}MB, reserved={reserved:.2f}MB")
                
            except Exception as e:
                logger.error(f"Error cleaning GPU memory: {str(e)}")
    
    async def get_answer(self, query: str) -> Dict[str, Any]:
        """
        Trả lời câu hỏi từ người dùng.
        
        Args:
            query: Câu hỏi của người dùng
            
        Returns:
            Dict chứa kết quả trả lời và metrics
        """
        # Đặt mức retry tối đa
        MAX_RETRIES = 2
        
        logger.info(f"Processing query: {query}")
        try:
            # Lazy loading if needed
            if not self._initialized:
                self._initialize_components()
            
            # Nếu mô hình bị lỗi, thử khởi tạo lại
            if self._llm is None:
                logger.warning("LLM is None, reinitializing components...")
                self._initialized = False
                self._initialize_components()
            
            # Lưu thời điểm bắt đầu xử lý
            start_time = time.time()
            
            try:
                # 1. Tìm documents liên quan
                docs = self._retriever.get_relevant_documents(query)
                retrieval_time = time.time() - start_time
                
                # 2. Log số lượng document tìm được
                logger.info(f"Found {len(docs)} relevant documents in {retrieval_time:.4f} seconds")
                
                if not docs:
                    logger.warning("No relevant documents found")
                    end_time = time.time()
                    return {
                        "answer": "Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu.",
                        "query": query,
                        "processing_time": end_time - start_time,
                        "status": "no_documents"
                    }
                
                # 3. Tạo context - giới hạn kích thước để tránh OOM
                context_parts = []
                total_length = 0
                max_context_length = 3000  # Giới hạn kích thước ngữ cảnh
                
                for doc in docs:
                    if total_length + len(doc.page_content) <= max_context_length:
                        context_parts.append(doc.page_content)
                        total_length += len(doc.page_content)
                    else:
                        break
                
                context = "\n\n".join(context_parts)
                
                # 4. Tạo prompt hoàn chỉnh
                full_prompt = self._prompt.format(context=context, question=query)
                
                # 5. Gọi LLM - bắt và xử lý exceptions
                try:
                    # Lưu thời điểm bắt đầu inference
                    inference_start = time.time()
                    
                    answer_text = self._llm(full_prompt)
                    
                    # Tính thời gian inference
                    inference_time = time.time() - inference_start
                    
                    # 6. Làm sạch câu trả lời
                    answer_text = answer_text.replace("<</SYS>>", "").replace("<<SYS>>", "")
                    answer_text = answer_text.strip()
                    
                    # 7. Tính thời gian xử lý tổng thể
                    end_time = time.time()
                    processing_time = end_time - start_time
                    
                    logger.info(f"Generated answer in {processing_time:.4f} seconds (retrieval: {retrieval_time:.4f}s, inference: {inference_time:.4f}s)")
                    
                    # Reset retry counter nếu thành công
                    self._retry_count = 0
                    
                    # Trả về kết quả với thông tin metrics
                    return {
                        "answer": answer_text,
                        "query": query,
                        "processing_time": processing_time,
                        "retrieval_time": retrieval_time,
                        "inference_time": inference_time,
                        "num_docs": len(docs),
                        "status": "success"
                    }
                    
                except Exception as model_error:
                    logger.error(f"LLM inference error: {str(model_error)}")
                    
                    # Nếu lỗi là do memory, thử giải phóng bộ nhớ và restart
                    if self._retry_count < MAX_RETRIES:
                        self._retry_count += 1
                        logger.warning(f"Retrying ({self._retry_count}/{MAX_RETRIES})...")
                        
                        # Giải phóng bộ nhớ
                        self._clean_gpu_memory()
                        
                        # Khởi tạo lại components
                        self._initialized = False
                        self._initialize_components()
                        
                        # Thử lại
                        return await self.get_answer(query)
                    
                    # Nếu đã retry quá nhiều lần, trả về lỗi
                    end_time = time.time()
                    return {
                        "answer": f"Xin lỗi, tôi gặp lỗi khi xử lý câu hỏi của bạn: {str(model_error)}",
                        "query": query,
                        "error": str(model_error),
                        "processing_time": end_time - start_time,
                        "status": "model_error"
                    }
                
            except Exception as process_error:
                logger.error(f"Processing error: {str(process_error)}")
                end_time = time.time()
                return {
                    "answer": "Xin lỗi, tôi gặp lỗi khi xử lý câu hỏi của bạn. Vui lòng thử lại sau.",
                    "query": query,
                    "error": str(process_error),
                    "processing_time": end_time - start_time,
                    "status": "process_error"
                }
                
        except Exception as e:
            logger.error(f"Unhandled exception: {str(e)}")
            return {
                "answer": "Xin lỗi, hệ thống gặp lỗi khi xử lý yêu cầu của bạn. Vui lòng thử lại sau.",
                "query": query,
                "error": str(e),
                "status": "system_error"
            }
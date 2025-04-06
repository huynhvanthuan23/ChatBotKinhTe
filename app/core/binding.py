from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
from langchain_community.llms import LlamaCpp
import logging
import os

# Cấu hình logger
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class ModelManager:
    """Quản lý tải và ràng buộc mô hình."""
    
    _instance = None
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(ModelManager, cls).__new__(cls)
            cls._instance._initialized = False
        return cls._instance
    
    def __init__(self):
        if self._initialized:
            return
        
        self._initialized = True
        self._embedding_model = None
        self._vector_store = None
        self._llm = None
        self._model_path = "models/mistral-7b-instruct-v0.1.Q2_K.gguf"
        self._vector_db_path = "vector_db"
    
    def get_embedding_model(self):
        """Lấy embedding model."""
        if self._embedding_model is None:
            try:
                logger.info("Loading embedding model...")
                self._embedding_model = HuggingFaceEmbeddings(
                    model_name="sentence-transformers/all-MiniLM-L6-v2"
                )
                logger.info("Embedding model loaded successfully")
            except Exception as e:
                logger.error(f"Error loading embedding model: {str(e)}")
                raise
        return self._embedding_model
    
    def get_vector_store(self):
        """Lấy vector store."""
        if self._vector_store is None:
            try:
                embeddings = self.get_embedding_model()
                logger.info(f"Loading vector store from {self._vector_db_path}...")
                if not os.path.exists(self._vector_db_path):
                    logger.error(f"Vector store path not found: {self._vector_db_path}")
                    return None
                
                # Cố gắng load với các tham số khác nhau
                try:
                    self._vector_store = FAISS.load_local(self._vector_db_path, embeddings, allow_dangerous_deserialization=True)
                except:
                    try:
                        self._vector_store = FAISS.load_local(self._vector_db_path, embeddings)
                    except:
                        logger.error("Failed to load vector store with standard parameters")
                        return None
                
                logger.info("Vector store loaded successfully")
            except Exception as e:
                logger.error(f"Error loading vector store: {str(e)}")
                return None  # Trả về None thay vì raise lỗi
        return self._vector_store
    
    def get_llm(self):
        """Lấy language model."""
        if self._llm is None:
            try:
                logger.info(f"Loading LLM from {self._model_path}...")
                if not os.path.exists(self._model_path):
                    logger.error(f"Model path not found: {self._model_path}")
                    # Trả về đối tượng giả nếu không tìm thấy mô hình
                    class FakeLLM:
                        def __call__(self, prompt):
                            return "Hệ thống hiện không thể xử lý yêu cầu này."
                    return FakeLLM()
                
                # Cập nhật tham số cho Mistral
                try:
                    self._llm = LlamaCpp(
                        model_path=self._model_path,
                        temperature=0.1,
                        max_tokens=2000,
                        n_ctx=2048,
                        n_gpu_layers=-1,  # Sử dụng tất cả GPU layers
                        n_batch=512,
                        f16_kv=True,  # Để tăng tốc độ
                        verbose=True,
                        stop=["<|im_end|>", "<|endoftext|>"]  # Điều chỉnh stop tokens cho Mistral
                    )
                    logger.info("Mistral model loaded successfully with GPU support")
                except Exception as gpu_error:
                    logger.warning(f"Failed to load with GPU: {str(gpu_error)}")
                    # Fallback to CPU
                    self._llm = LlamaCpp(
                        model_path=self._model_path,
                        temperature=0.1,
                        max_tokens=2000,
                        n_ctx=2048,
                        n_gpu_layers=0,  # Không sử dụng GPU
                        verbose=True
                    )
                    logger.info("Mistral model loaded successfully with CPU")
            except Exception as e:
                logger.error(f"Error loading LLM: {str(e)}")
                # Trả về đối tượng giả nếu có lỗi
                class FakeLLM:
                    def __call__(self, prompt):
                        return "Hệ thống hiện không thể xử lý yêu cầu này."
                return FakeLLM()
        return self._llm
    
    def reload_vector_store(self):
        """Tải lại vector store."""
        self._vector_store = None
        return self.get_vector_store()

# Tạo instance toàn cục
model_manager = ModelManager() 
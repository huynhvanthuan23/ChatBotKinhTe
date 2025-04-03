from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
from langchain.chains import RetrievalQA
from langchain.prompts import PromptTemplate
from langchain_community.llms import LlamaCpp
from typing import Dict, Any, Optional
from app.core.config import settings
from app.core.logger import get_logger
import os

logger = get_logger(__name__)

class ChatbotService:
    _instance = None
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(ChatbotService, cls).__new__(cls)
            cls._instance._initialized = False
        return cls._instance
    
    def __init__(self):
        # Không làm gì trong __init__, chỉ gán biến cần thiết
        if self._initialized:
            return
            
        # Chỉ đặt cờ, không tải model
        self._initialized = True
        self._embeddings = None
        self._db = None
        self._llm = None
        self._qa_chain = None
        self._prompt = None
        logger.info("ChatbotService instance created (lazy loading enabled)")
    
    def _initialize_components(self):
        """Lazy initialization of components when needed"""
        if self._qa_chain is not None:
            return
            
        logger.info("Initializing ChatbotService components...")
        
        try:
            # Kiểm tra paths
            if not os.path.exists(settings.DB_FAISS_PATH):
                raise FileNotFoundError(f"Vector database not found: {settings.DB_FAISS_PATH}")
                
            if not os.path.exists(settings.MODEL_PATH):
                raise FileNotFoundError(f"LLM model not found: {settings.MODEL_PATH}")
            
            # Khởi tạo embedding model
            self._embeddings = HuggingFaceEmbeddings(model_name=settings.EMBEDDING_MODEL)
            logger.info(f"Loaded embedding model: {settings.EMBEDDING_MODEL}")
            
            # Tải vector store
            self._db = FAISS.load_local(
                settings.DB_FAISS_PATH, 
                self._embeddings, 
                allow_dangerous_deserialization=True
            )
            logger.info(f"Loaded vector store from {settings.DB_FAISS_PATH}")
            
            # Tạo prompt template
            custom_prompt = """[INST] <<SYS>>
Bạn là trợ lý AI hữu ích. Hãy trả lời câu hỏi dựa vào thông tin được cung cấp.Nếu không biết câu trả lời, hãy nói 'Tôi không tìm thấy thông tin liên quan'.
<</SYS>>

Context: {context}
Question: {question} [/INST]"""
            
            self._prompt = PromptTemplate(template=custom_prompt, input_variables=["context", "question"])
            
            # Tải GGUF model
            self._llm = LlamaCpp(
                model_path=settings.MODEL_PATH,
                temperature=settings.TEMPERATURE,
                max_tokens=settings.MAX_TOKENS,
                n_ctx=settings.N_CTX,
                n_gpu_layers=settings.N_GPU_LAYERS,
                verbose=False
            )
            logger.info(f"Loaded LLM model from {settings.MODEL_PATH}")
            
            # Tạo QA chain
            self._qa_chain = RetrievalQA.from_chain_type(
                llm=self._llm,
                chain_type="stuff",
                retriever=self._db.as_retriever(),
                chain_type_kwargs={"prompt": self._prompt}
            )
            
            logger.info("ChatbotService components initialized successfully")
            
        except Exception as e:
            logger.error(f"Error initializing ChatbotService components: {str(e)}")
            raise
    
    async def get_answer(self, query: str) -> Dict[str, Any]:
        """
        Trả lời câu hỏi từ người dùng.
        
        Args:
            query: Câu hỏi của người dùng
            
        Returns:
            Dict chứa kết quả trả lời
        """
        logger.info(f"Processing query: {query}")
        try:
            # Lazy loading
            self._initialize_components()
            
            result = self._qa_chain({"query": query})
            logger.info("Answer generated successfully")
            return {
                "answer": result['result'],
                "query": query
            }
        except Exception as e:
            logger.error(f"Error generating answer: {str(e)}")
            return {
                "answer": f"Xin lỗi, đã xảy ra lỗi khi xử lý câu hỏi của bạn: {str(e)}",
                "query": query
            } 
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import FAISS
from langchain.prompts import PromptTemplate
from typing import Dict, Any, Optional, Tuple, List
from app.core.config import settings
from app.core.logger import get_logger
import os
import time
import torch
import gc
import traceback
import google.generativeai as genai
import openai
from openai import OpenAI
import json
import re
from langchain.schema import Document
from langchain_community.document_loaders import TextLoader, PyPDFLoader, Docx2txtLoader
from langchain.text_splitter import RecursiveCharacterTextSplitter
import shutil

# Cấu hình logging
logger = get_logger(__name__)

class ChatbotService:
    _instance = None
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(ChatbotService, cls).__new__(cls)
            cls._instance._initialized = False
            cls._instance._retry_count = 0
            cls._instance._gemini_model = None
            cls._instance._gemini_chat = None
            cls._instance._openai_client = None
        return cls._instance
    
    def __init__(self):
        # Các thuộc tính sẽ được khởi tạo trong _initialize_components
        pass
    
    def initialize_vector_db(self):
        """
        Khởi tạo và trả về vector database
        
        Returns:
            Retriever đã được cấu hình hoặc None nếu có lỗi
        """
        try:
            logger.info("Initializing vector database...")
            # Kiểm tra đường dẫn và file tồn tại
            db_path = settings.DB_FAISS_PATH
            
            if not os.path.exists(db_path):
                logger.error(f"Vector database directory not found: {db_path}")
                os.makedirs(db_path, exist_ok=True)
                logger.info(f"Created vector database directory: {db_path}")
                return None
                
            # Kiểm tra các file cần thiết
            if not os.path.exists(os.path.join(db_path, "index.faiss")) or not os.path.exists(os.path.join(db_path, "index.pkl")):
                logger.error(f"Missing vector index files in {db_path}")
                return None
                
            # Tải embedding model
            embedding = HuggingFaceEmbeddings(model_name=settings.EMBEDDING_MODEL)
            
            # Tải vector database
            try:
                logger.info(f"Loading vector database from {db_path}")
                try:
                    db = FAISS.load_local(db_path, embedding, allow_dangerous_deserialization=True)
                except TypeError:
                    db = FAISS.load_local(db_path, embedding)
                    
                # Kiểm tra kích thước của vector database
                if hasattr(db, 'index') and hasattr(db.index, 'ntotal'):
                    logger.info(f"Vector database loaded with {db.index.ntotal} vectors")
                else:
                    logger.warning("Vector database loaded but size could not be determined")
                
                # Tạo retriever với tham số cải thiện để tìm nhiều tài liệu hơn
                retriever = db.as_retriever(
                    search_type="mmr",
                    search_kwargs={
                        "k": 8,              # Tăng số lượng kết quả trả về
                        "fetch_k": 30,       # Tăng số lượng kết quả lấy ra trước khi áp dụng MMR
                        "lambda_mult": 0.7,  # Cân bằng giữa relevance và diversity
                        "score_threshold": 0.2  # Giảm ngưỡng điểm để lấy nhiều kết quả hơn 
                    }
                )
                
                logger.info("Vector database loaded and retriever configured with enhanced parameters")
                return retriever
                
            except Exception as e:
                logger.error(f"Error loading vector database: {str(e)}")
                logger.error(traceback.format_exc())
                return None
                
        except Exception as e:
            logger.error(f"Error initializing vector database: {str(e)}")
            logger.error(traceback.format_exc())
            return None
    
    def _initialize_components(self):
        """Initialize components at startup"""
        # Reset initialized flag to force reloading components
        self._initialized = False
        
        # Reset các thành phần để tránh sử dụng cache
        self._embeddings = None
        self._db = None
        self._retriever = None
        self._gemini_model = None
        self._gemini_chat = None
        self._openai_client = None
            
        logger.info("Initializing ChatbotService components...")
        
        try:
            # Đầu tiên, giải phóng bộ nhớ GPU
            self._clean_gpu_memory()
            
            # Khởi tạo model dựa vào API_TYPE
            if settings.USE_API:
                if settings.API_TYPE.lower() == "google" and settings.GOOGLE_API_KEY:
                    try:
                        logger.info("Initializing Gemini API...")
                    
                        # Cấu hình API
                        genai.configure(api_key=settings.GOOGLE_API_KEY)
                    
                        # Sử dụng model gemini-1.5-pro
                        self._gemini_model = genai.GenerativeModel(settings.GOOGLE_MODEL)
                        self._gemini_chat = self._gemini_model.start_chat(history=[])
                        logger.info(f"Gemini API initialized successfully with model {settings.GOOGLE_MODEL}")
                        
                    except Exception as api_err:
                        logger.error(f"Error initializing Gemini API: {str(api_err)}")
                        raise RuntimeError("Failed to initialize Gemini API. Please check your API key and internet connection.")
                
                elif settings.API_TYPE.lower() == "openai" and settings.OPENAI_API_KEY:
                    try:
                        logger.info("Initializing OpenAI API...")
                        
                        # Cấu hình API
                        self._openai_client = OpenAI(api_key=settings.OPENAI_API_KEY)
                        
                        # Test kết nối
                        response = self._openai_client.chat.completions.create(
                            model=settings.OPENAI_MODEL,
                            messages=[{"role": "system", "content": "Test connection"}],
                            max_tokens=5
                        )
                        logger.info(f"OpenAI API initialized successfully with model {settings.OPENAI_MODEL}")
                        
                    except Exception as api_err:
                        logger.error(f"Error initializing OpenAI API: {str(api_err)}")
                        raise RuntimeError("Failed to initialize OpenAI API. Please check your API key and internet connection.")
                else:
                    logger.warning(f"No valid API configuration found. API_TYPE: {settings.API_TYPE}")
            
            # Khởi tạo embedding model và vector store
            try:
                # Kiểm tra đường dẫn vector database
                db_path = settings.DB_FAISS_PATH
                logger.info(f"Vector store path: {db_path}")
                
                # Kiểm tra xem thư mục và file có tồn tại không
                if not os.path.exists(db_path):
                    logger.error(f"Vector store directory does not exist: {db_path}")
                    raise FileNotFoundError(f"Vector store directory not found: {db_path}")
                
                faiss_index_path = os.path.join(db_path, "index.faiss")
                pkl_index_path = os.path.join(db_path, "index.pkl")
                
                if not os.path.exists(faiss_index_path) or not os.path.exists(pkl_index_path):
                    logger.error(f"Missing index files in {db_path}, found: {os.listdir(db_path) if os.path.exists(db_path) else 'N/A'}")
                    raise FileNotFoundError(f"FAISS index files not found in {db_path}")
                
                # Khởi tạo embedding model
                logger.info(f"Loading embedding model: {settings.EMBEDDING_MODEL}")
                self._embeddings = HuggingFaceEmbeddings(
                    model_name=settings.EMBEDDING_MODEL,
                    model_kwargs={"device": "cuda" if torch.cuda.is_available() else "cpu"}
                )
                
                # Tải vector store với xử lý lỗi cụ thể hơn
                logger.info(f"Loading vector store from {db_path}")
                try:
                    # Sửa lại cách load FAISS index
                    self._db = FAISS.load_local(
                        db_path, 
                        self._embeddings
                    )
                    
                    # Kiểm tra xem vector store có dữ liệu không
                    if hasattr(self, '_db') and self._db is not None:
                        logger.info(f"Vector store loaded successfully with {self._db.index.ntotal} vectors")
                    else:
                        logger.warning("Vector store loaded but may be empty or incorrectly structured")
                except Exception as e:
                    logger.error(f"Error loading vector store: {str(e)}")
                    self._db = None
                
                # Cấ hình retriever
                logger.info("Configuring retriever")
                self._retriever = self._db.as_retriever(
                    search_type="similarity",
                    search_kwargs={"k": 4}
                )
                logger.info("Retriever configured successfully")
                
                # Thử test retriever với query đơn giản
                try:
                    test_query = "test query"
                    test_docs = self._retriever.get_relevant_documents(test_query, k=1)
                    logger.info(f"Retriever test successful, found {len(test_docs)} document(s)")
                except Exception as ret_test_err:
                    logger.error(f"Error testing retriever: {str(ret_test_err)}")
                    # Không raise ở đây, chỉ log lỗi để tiếp tục
                
            except Exception as emb_err:
                logger.error(f"Error loading embeddings/vectorstore: {str(emb_err)}")
                logger.error(f"Stacktrace: {traceback.format_exc()}")
                raise RuntimeError(f"Failed to initialize vector database: {str(emb_err)}")
            
            # Đánh dấu là đã khởi tạo xong
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
                # Giải phóng embeddings nếu có
                if hasattr(self, '_embeddings') and self._embeddings is not None:
                    del self._embeddings
                    self._embeddings = None
                
                # Giải phóng vector store nếu có
                if hasattr(self, '_db') and self._db is not None:
                    del self._db
                    self._db = None
                
                # Giải phóng retriever nếu có
                if hasattr(self, '_retriever') and self._retriever is not None:
                    del self._retriever
                    self._retriever = None
                
                # Giải phóng bộ nhớ GPU
                torch.cuda.empty_cache()
                gc.collect()
                
                logger.info("GPU memory cleaned successfully")
            except Exception as e:
                logger.error(f"Error cleaning GPU memory: {str(e)}")
    
    async def get_answer(self, query: str) -> Dict[str, Any]:
        """Get answer from chatbot"""
        try:
            # Đảm bảo components đã được khởi tạo
            if not self._initialized:
                self._initialize_components()
            
            # Tìm kiếm ngữ cảnh từ vector database
            context = ""
            relevant_docs = []
            if self._retriever is not None:
                try:
                    # Lấy top 3 documents liên quan
                    docs = self._retriever.get_relevant_documents(query, k=3)
                    if docs:
                        context = "\n\n".join([doc.page_content for doc in docs])
                        relevant_docs = docs
                        logger.info(f"Found {len(docs)} relevant documents from vector database")
                        
                        # Debug: Hiển thị thông tin các documents tìm được
                        for i, doc in enumerate(docs):
                            logger.info(f"Document {i+1} preview: {doc.page_content[:100]}...")
                    else:
                        logger.info("No relevant documents found in vector database")
                        return {
                            "query": query,
                            "response": "Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu. Vui lòng thử lại với câu hỏi khác.",
                            "model_info": {
                                "context_used": False,
                                "no_relevant_info": True,
                                "documents_found": 0
                            }
                        }
                except Exception as ret_err:
                    logger.error(f"Error retrieving context: {str(ret_err)}")
                    context = ""
                
                # Tạo prompt với ngữ cảnh kèm yêu cầu trích dẫn rõ ràng
                prompt = f"""BẮT BUỘC trả lời dựa HOÀN TOÀN trên thông tin được cung cấp dưới đây.
KHÔNG ĐƯỢC sử dụng kiến thức bên ngoài dưới bất kỳ hình thức nào.
Nếu không có thông tin liên quan, hãy nói "Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu".

THÔNG TIN THAM KHẢO:
{context}

CÂU HỎI: {query}

YÊU CẦU ĐẶC BIỆT:
1. PHẢI bắt đầu câu trả lời của bạn với "Theo dữ liệu tìm được: " và kèm theo trích dẫn trực tiếp từ thông tin trên.
2. Nếu có thông tin liên quan, PHẢI trích dẫn ít nhất một đoạn từ thông tin trên.
3. Nếu không có thông tin liên quan, hãy nói rõ ràng "Tôi không tìm thấy thông tin liên quan trong cơ sở dữ liệu".
4. KHÔNG ĐƯỢC thêm bất kỳ thông tin nào không có trong dữ liệu được cung cấp.
"""
            
            start_time = time.time()
            answer = ""
            model_used = ""
            
            # Sử dụng API dựa trên cấu hình
            if settings.API_TYPE.lower() == "google" and self._gemini_model and self._gemini_chat:
                try:
                    # Gửi prompt đến Gemini API
                    response = self._gemini_chat.send_message(prompt)
                    answer = response.text
                    model_used = settings.GOOGLE_MODEL
                    logger.info("Using Gemini API for response generation")
                except Exception as api_err:
                    logger.error(f"Error getting answer from Gemini API: {str(api_err)}")
                    logger.error(traceback.format_exc())
                    raise RuntimeError(f"Failed to get response from Gemini API: {str(api_err)}")
                    
            elif settings.API_TYPE.lower() == "openai" and self._openai_client:
                try:
                    # Gửi prompt đến OpenAI API
                    messages = [
                        {"role": "system", "content": "Bạn là trợ lý AI có kiến thức chuyên sâu về kinh tế Việt Nam. Nhiệm vụ của bạn là trả lời câu hỏi dựa trên dữ liệu được cung cấp, không sử dụng thông tin bên ngoài."},
                        {"role": "user", "content": prompt}
                    ]
                    
                    response = self._openai_client.chat.completions.create(
                        model=settings.OPENAI_MODEL,
                        messages=messages,
                        temperature=settings.TEMPERATURE,
                        max_tokens=settings.MAX_TOKENS
                    )
                    
                    answer = response.choices[0].message.content
                    model_used = settings.OPENAI_MODEL
                    logger.info("Using OpenAI API for response generation")
                except Exception as api_err:
                    logger.error(f"Error getting answer from OpenAI API: {str(api_err)}")
                    logger.error(traceback.format_exc())
                    raise RuntimeError(f"Failed to get response from OpenAI API: {str(api_err)}")
            else:
                logger.error("No valid API available for response generation")
                raise RuntimeError("No valid API configuration found. Please check your API settings.")
                
            # Đảm bảo câu trả lời bắt đầu với "Theo dữ liệu tìm được: "
            if answer and not answer.startswith("Theo dữ liệu tìm được:"):
                answer = "Theo dữ liệu tìm được: " + answer
            
            elapsed_time = time.time() - start_time
            logger.info(f"API response received in {elapsed_time:.2f} seconds")
            
            # Trả về kết quả
            return {
                "query": query,
                "response": answer,
                "model_info": {
                    "model": model_used,
                    "context_used": True if context else False,
                    "documents_found": len(relevant_docs),
                    "response_time": f"{elapsed_time:.2f} seconds"
                }
            }
                
        except Exception as e:
            logger.error(f"Error in get_answer: {str(e)}")
            logger.error(traceback.format_exc())
            return {
                "query": query,
                "response": "Xin lỗi, có lỗi xảy ra khi xử lý câu hỏi của bạn. Vui lòng thử lại sau.",
                "model_info": {
                    "error": str(e)
                }
            }
    
    def restart(self):
        """Restart all components"""
        logger.info("Restarting ChatbotService components...")
        try:
            # Giải phóng bộ nhớ
            self._clean_gpu_memory()
            
            # Reset initialized flag
            self._initialized = False
            
            # Khởi tạo lại components
            self._initialize_components()
            
            return True
        except Exception as e:
            logger.error(f"Error restarting components: {str(e)}")
            logger.error(traceback.format_exc())
            return False

    async def get_simple_retrieval(self, query: str, document_ids: List[int] = None) -> Dict[str, Any]:
        """Tìm kiếm đơn giản dựa trên vector database mà không sử dụng LLM API"""
        try:
            # Đảm bảo components đã được khởi tạo
            if not self._initialized:
                self._initialize_components()
            
            # Log thông tin query ban đầu
            logger.info(f"Query gốc: '{query}'")
            
            # Tiền xử lý query để tăng khả năng tìm kiếm
            processed_query = query.strip().lower()
            
            # Tạo các biến thể của truy vấn để tìm kiếm tốt hơn (đặc biệt cho truy vấn định nghĩa)
            expanded_queries = [processed_query]
            if "là gì" in processed_query:
                # Trích xuất khái niệm từ câu hỏi "X là gì"
                concept = processed_query.replace("là gì", "").strip()
                # Thêm các biến thể
                expanded_queries.extend([
                    f"định nghĩa {concept}",
                    f"{concept} được định nghĩa",
                    f"{concept} có nghĩa",
                    f"khái niệm {concept}"
                ])
                logger.info(f"Mở rộng truy vấn định nghĩa: {expanded_queries}")
            
            # Tạo embedding cho câu hỏi để kiểm tra
            try:
                embedding_model = HuggingFaceEmbeddings(model_name=settings.EMBEDDING_MODEL)
                start_time = time.time()
                question_embedding = embedding_model.embed_query(query)
                embedding_time = time.time() - start_time
                
                # Kiểm tra và log embedding vector
                if question_embedding and len(question_embedding) > 0:
                    logger.info(f"Đã embedding thành công câu hỏi thành vector kích thước {len(question_embedding)}, thời gian: {embedding_time:.3f}s")
                    # Log một phần nhỏ của vector để xác nhận
                    vector_sample = str(question_embedding[:5])
                    logger.info(f"Mẫu vector câu hỏi: {vector_sample}...")
                else:
                    logger.error("Không thể embedding câu hỏi thành vector!")
            except Exception as emb_err:
                logger.error(f"Lỗi khi tạo embedding cho câu hỏi: {str(emb_err)}")
                logger.error(traceback.format_exc())
            
            docs = []
            citations = [] # Khởi tạo danh sách citations
            detailed_citations = [] # Khởi tạo danh sách detailed_citations
            
            # Log thông tin chi tiết về document IDs
            if document_ids:
                logger.info(f"Đang tìm kiếm trong các tài liệu: {document_ids}")
            else:
                logger.info("Không có tài liệu cụ thể, sẽ tìm kiếm trong toàn bộ vector database")
            
            # Nếu có document IDs, ưu tiên tìm kiếm trong các tài liệu đã chọn
            if document_ids:
                # Đảm bảo document_ids là list các số
                if isinstance(document_ids, str):
                    try:
                        document_ids = [int(doc_id.strip()) for doc_id in document_ids.split(',')]
                        logger.info(f"Đã chuyển đổi document_ids từ string sang list: {document_ids}")
                    except Exception as e:
                        logger.error(f"Lỗi khi chuyển đổi document_ids từ string: {e}")
                
                # Tìm kiếm trong từng tài liệu
                for doc_id in document_ids:
                    # Kiểm tra định dạng mới - trong UPLOAD_VECTOR_DIR
                    user_id = await self.find_user_id_for_document(doc_id)
                    if user_id:
                        document_vectors_path = f"{settings.UPLOAD_VECTOR_DIR}/{user_id}/{doc_id}"
                    else:
                        # Định dạng cũ
                        document_vectors_path = os.path.join(settings.DB_FAISS_PATH, f"doc_{doc_id}")
                    
                    logger.info(f"Kiểm tra đường dẫn tài liệu: {document_vectors_path}")
                    
                    if os.path.exists(document_vectors_path):
                        logger.info(f"Thư mục tài liệu {doc_id} tồn tại")
                        index_path = os.path.join(document_vectors_path, "index.faiss")
                        pkl_path = os.path.join(document_vectors_path, "index.pkl")
                        
                        if os.path.exists(index_path) and os.path.exists(pkl_path):
                            logger.info(f"Các file index cho tài liệu {doc_id} đã được tìm thấy")
                            try:
                                logger.info(f"Đang khởi tạo tìm kiếm trong tài liệu ID {doc_id}")
                                embedding = HuggingFaceEmbeddings(model_name=settings.EMBEDDING_MODEL)
                                try:
                                    doc_db = FAISS.load_local(document_vectors_path, embedding, allow_dangerous_deserialization=True)
                                    logger.info(f"Đã load vector database cho tài liệu {doc_id} với allow_dangerous_deserialization=True")
                                except TypeError:
                                    doc_db = FAISS.load_local(document_vectors_path, embedding)
                                    logger.info(f"Đã load vector database cho tài liệu {doc_id} với cách thông thường")
                                
                                # Kiểm tra kích thước vector database
                                if hasattr(doc_db, 'index') and hasattr(doc_db.index, 'ntotal'):
                                    logger.info(f"Vector database cho tài liệu {doc_id} có {doc_db.index.ntotal} vectors")
                                
                                # Cấu hình retriever với các thông số nới lỏng hơn để tìm nhiều kết quả hơn
                                doc_retriever = doc_db.as_retriever(
                                    search_type="mmr",  # Maximum Marginal Relevance
                                    search_kwargs={
                                        "k": 15,         # Tăng số lượng kết quả hơn nữa
                                        "fetch_k": 50,   # Tăng số lượng kết quả lấy ra để so sánh
                                        "lambda_mult": 0.65,  # Giảm đa dạng, tăng độ liên quan
                                        "score_threshold": 0.12  # Giảm mạnh ngưỡng điểm để lấy nhiều kết quả hơn
                                    }
                                )
                                
                                # Lấy kết quả cho mỗi biến thể query để tăng khả năng tìm thấy kết quả
                                all_results = []
                                for variant in expanded_queries:
                                    logger.info(f"Đang thực hiện tìm kiếm với biến thể query: '{variant}' trong tài liệu ID {doc_id}")
                                    start_search = time.time()
                                    variant_results = doc_retriever.get_relevant_documents(variant)
                                    search_time = time.time() - start_search
                                    
                                    if variant_results:
                                        logger.info(f"Tìm thấy {len(variant_results)} kết quả cho biến thể '{variant}' trong {search_time:.3f}s")
                                        all_results.extend(variant_results)
                                
                                # Lọc kết quả trùng lặp
                                unique_results = []
                                seen_content = set()
                                for doc in all_results:
                                    content_hash = hash(doc.page_content)
                                    if content_hash not in seen_content:
                                        seen_content.add(content_hash)
                                        # Đảm bảo có metadata document_id
                                        if 'document_id' not in doc.metadata:
                                            doc.metadata['document_id'] = doc_id
                                        unique_results.append(doc)
                                
                                if unique_results:
                                    logger.info(f"Tìm thấy {len(unique_results)} kết quả duy nhất từ tài liệu ID {doc_id}")
                                    # Log một phần nội dung kết quả tìm được
                                    for i, doc in enumerate(unique_results[:3]):  # Log 3 kết quả đầu tiên
                                        preview = doc.page_content[:100] + "..." if len(doc.page_content) > 100 else doc.page_content
                                        logger.info(f"  Kết quả {i+1} từ tài liệu {doc_id}: {preview}")
                                    docs.extend(unique_results)
                                    
                                    # Đọc metadata của tài liệu để sử dụng trong citations
                                    doc_title = f"Tài liệu {doc_id}"
                                    metadata_path = os.path.join(document_vectors_path, "document_metadata.json")
                                    if os.path.exists(metadata_path):
                                        try:
                                            with open(metadata_path, 'r', encoding='utf-8') as f:
                                                doc_metadata = json.load(f)
                                                if 'title' in doc_metadata:
                                                    doc_title = doc_metadata['title']
                                        except Exception as e:
                                            logger.error(f"Lỗi đọc metadata tài liệu: {e}")
                                    
                                    # Tạo trích dẫn cho mỗi kết quả
                                    for doc_result in unique_results[:5]:  # Giới hạn số lượng trích dẫn
                                        # Xây dựng URL trích dẫn
                                        page_num = doc_result.metadata.get('page_num', 1)
                                        chunk_idx = doc_result.metadata.get('chunk_index', 0)
                                        doc_url = f"/documents/{doc_id}?page={page_num}&highlight={chunk_idx}"
                                        
                                        # Tạo citation đơn giản
                                        citation = {
                                            "doc_id": doc_id,
                                            "title": doc_title,
                                            "page": page_num,
                                            "chunk_index": chunk_idx,
                                            "url": doc_url,
                                            "citation_text": f"{doc_title} - Trang {page_num}, đoạn {chunk_idx}"
                                        }
                                        citations.append(citation)
                                        
                                        # Tạo citation chi tiết
                                        detailed_citation = {
                                            "text": doc_result.page_content,
                                            "metadata": {
                                                "doc_id": doc_id,
                                                "title": doc_title,
                                                "page": page_num,
                                                "chunk_index": chunk_idx,
                                                "url": doc_url,
                                                "citation_text": f"{doc_title} - Trang {page_num}, đoạn {chunk_idx}"
                                            }
                                        }
                                        detailed_citations.append(detailed_citation)
                                else:
                                    logger.info(f"Không tìm thấy kết quả nào trong tài liệu ID {doc_id}")
                                    # Thử với ngưỡng thấp hơn nếu không tìm thấy kết quả
                                    logger.info(f"Thử lại với ngưỡng rất thấp (0.03) cho tài liệu {doc_id}")
                                    doc_retriever = doc_db.as_retriever(
                                        search_type="similarity",  # Dùng similarity thay vì MMR
                                        search_kwargs={
                                            "k": 20,              # Tăng số lượng kết quả hơn nữa
                                            "score_threshold": 0.03  # Giảm ngưỡng rất thấp
                                        }
                                    )
                                    retry_results = doc_retriever.get_relevant_documents(query)
                                    if retry_results:
                                        logger.info(f"Tìm thấy {len(retry_results)} kết quả sau khi giảm ngưỡng cho tài liệu {doc_id}")
                                        for doc in retry_results:
                                            if 'document_id' not in doc.metadata:
                                                doc.metadata['document_id'] = doc_id
                                        docs.extend(retry_results)
                                        
                                        # Thêm trích dẫn cho kết quả tìm được với ngưỡng thấp
                                        for doc_result in retry_results[:5]:
                                            page_num = doc_result.metadata.get('page_num', 1)
                                            chunk_idx = doc_result.metadata.get('chunk_index', 0)
                                            doc_url = f"/documents/{doc_id}?page={page_num}&highlight={chunk_idx}"
                                            
                                            citation = {
                                                "doc_id": doc_id,
                                                "title": doc_title,
                                                "page": page_num,
                                                "chunk_index": chunk_idx,
                                                "url": doc_url,
                                                "citation_text": f"{doc_title} - Trang {page_num}, đoạn {chunk_idx}"
                                            }
                                            citations.append(citation)
                            except Exception as e:
                                logger.error(f"Lỗi khi tìm kiếm trong tài liệu ID {doc_id}: {e}")
                                logger.error(traceback.format_exc())
                        else:
                            logger.error(f"Không tìm thấy các file index cần thiết cho tài liệu {doc_id}: {index_path}, {pkl_path}")
                    else:
                        logger.error(f"Thư mục vector cho tài liệu ID {doc_id} không tồn tại: {document_vectors_path}")
            
            # Nếu không tìm thấy kết quả trong các tài liệu đã chọn, hoặc không có tài liệu được chọn
            if not docs:
                if not document_ids and self._retriever:
                    logger.info("Không có tài liệu được chọn hoặc không tìm thấy kết quả, đang tìm kiếm trong vector database chính")
                    
                    # Cấu hình retriever tạm thời với thông số tối ưu hơn cho tìm kiếm
                    temp_retriever = self._db.as_retriever(
                        search_type="mmr",
                        search_kwargs={
                            "k": 12,              # Tăng số lượng kết quả
                            "fetch_k": 40,        # Tăng số lượng kết quả lấy ra
                            "lambda_mult": 0.7,   # Cân bằng giữa relevance và diversity
                            "score_threshold": 0.15  # Giảm ngưỡng điểm
                        }
                    )
                    
                    # Tìm kiếm với các biến thể query
                    all_results = []
                    for variant in expanded_queries:
                        logger.info(f"Tìm kiếm với biến thể '{variant}' trong vector database chính")
                        variant_results = temp_retriever.get_relevant_documents(variant)
                        if variant_results:
                            logger.info(f"Tìm thấy {len(variant_results)} kết quả cho biến thể '{variant}'")
                            all_results.extend(variant_results)
                    
                    # Lọc kết quả trùng lặp
                    unique_results = {}  # Sử dụng dict để lọc trùng lặp
                    
                    for doc, score in all_results:
                        content_hash = hash(doc.page_content)
                        # Lưu kết quả có điểm tốt nhất
                        if content_hash not in unique_results or score < unique_results[content_hash][1]:
                            unique_results[content_hash] = (doc, score)
                    
                    # Chuyển đổi dict trở lại thành list và sắp xếp theo điểm
                    filtered_results = list(unique_results.values())
                    filtered_results.sort(key=lambda x: x[1])  # Sắp xếp tăng dần theo điểm (điểm thấp = liên quan hơn)
                    
                    docs = filtered_results
                    logger.info(f"Tìm thấy {len(docs)} tài liệu liên quan độc nhất trong vector database chính")
                    
                    if len(docs) > 0:
                        for i, doc in enumerate(docs[:3]):  # Log 3 kết quả đầu tiên
                            preview = doc.page_content[:100] + "..." if len(doc.page_content) > 100 else doc.page_content
                            logger.info(f"  Kết quả {i+1} từ vector database chính: {preview}")
                        
                        # Tạo trích dẫn cho kết quả từ vector database chính
                        for doc_tuple in docs[:5]:
                            doc = doc_tuple[0]  # Lấy document từ tuple (doc, score)
                            doc_id = doc.metadata.get('document_id', 0)
                            page_num = doc.metadata.get('page_num', 1)
                            chunk_idx = doc.metadata.get('chunk_index', 0)
                            doc_title = doc.metadata.get('title', f"Tài liệu {doc_id}")
                            doc_url = f"/documents/{doc_id}?page={page_num}&highlight={chunk_idx}"
                            
                            citation = {
                                "doc_id": doc_id,
                                "title": doc_title,
                                "page": page_num,
                                "chunk_index": chunk_idx,
                                "url": doc_url,
                                "citation_text": f"{doc_title} - Trang {page_num}, đoạn {chunk_idx}"
                            }
                            citations.append(citation)
                            
                            # Thêm detailed citation
                            detailed_citation = {
                                "text": doc.page_content,
                                "metadata": {
                                    "doc_id": doc_id,
                                    "title": doc_title,
                                    "page": page_num,
                                    "chunk_index": chunk_idx,
                                    "url": doc_url,
                                    "citation_text": f"{doc_title} - Trang {page_num}, đoạn {chunk_idx}"
                                }
                            }
                            detailed_citations.append(detailed_citation)
                    elif self._db:  # Nếu vẫn không tìm thấy, thử lại với ngưỡng thấp hơn nữa
                        logger.info("Thử lại với ngưỡng thấp hơn trong vector database chính")
                        alt_retriever = self._db.as_retriever(
                            search_type="similarity",  # Dùng similarity thay vì MMR
                            search_kwargs={
                                "k": 20,              # Tăng số lượng kết quả
                                "score_threshold": 0.03  # Giảm ngưỡng rất thấp
                            }
                        )
                        # Tách từ khóa từ câu hỏi
                        keywords = processed_query.split()
                        keywords = [w for w in keywords if len(w) > 3]  # Chỉ lấy từ có ít nhất 4 ký tự
                        
                        if keywords:
                            logger.info(f"Tìm kiếm với các từ khóa: {keywords}")
                            for keyword in keywords[:3]:  # Chỉ dùng 3 từ khóa đầu tiên
                                keyword_docs = alt_retriever.get_relevant_documents(keyword)
                                if keyword_docs:
                                    for doc in keyword_docs:
                                        content_hash = hash(doc.page_content)
                                        if content_hash not in seen_content:
                                            seen_content.add(content_hash)
                                            docs.append(doc)
                                            
                                            # Thêm trích dẫn cho kết quả từ từ khóa
                                            doc_id = doc.metadata.get('document_id', 0)
                                            page_num = doc.metadata.get('page_num', 1)
                                            chunk_idx = doc.metadata.get('chunk_index', 0)
                                            doc_title = doc.metadata.get('title', f"Tài liệu {doc_id}")
                                            doc_url = f"/documents/{doc_id}?page={page_num}&highlight={chunk_idx}"
                                            
                                            citation = {
                                                "doc_id": doc_id,
                                                "title": doc_title,
                                                "page": page_num,
                                                "chunk_index": chunk_idx,
                                                "url": doc_url,
                                                "citation_text": f"{doc_title} - Trang {page_num}, đoạn {chunk_idx}"
                                            }
                                            citations.append(citation)
                                    logger.info(f"Tìm thấy {len(keyword_docs)} kết quả cho từ khóa '{keyword}'")
                            
                            logger.info(f"Tổng cộng tìm thấy {len(docs)} tài liệu sau khi tìm kiếm từ khóa")
            
            # Sắp xếp kết quả để ưu tiên những câu có chứa khái niệm cần tìm
            if "là gì" in processed_query:
                concept = processed_query.replace("là gì", "").strip().lower()
                concept_words = concept.split()
                
                # Tính điểm liên quan
                for doc in docs:
                    content_lower = doc.page_content.lower()
                    # Tính số từ khái niệm xuất hiện trong nội dung
                    relevance_score = sum(1 for word in concept_words if word in content_lower)
                    
                    # Nếu nội dung có chứa cả cụm từ khái niệm, tăng điểm nhiều hơn
                    if concept in content_lower:
                        relevance_score += 5
                    
                    # Nếu có các cụm từ định nghĩa, tăng điểm
                    if " là " in content_lower or "định nghĩa" in content_lower or "có nghĩa" in content_lower:
                        relevance_score += 3
                        
                    doc.metadata["relevance_score"] = relevance_score
                
                # Sắp xếp theo điểm liên quan
                docs.sort(key=lambda x: x.metadata.get("relevance_score", 0), reverse=True)
                
                # Giới hạn số lượng kết quả để tránh nhiễu
                docs = docs[:15]
            
            # Nếu tìm thấy tài liệu, sử dụng nội dung làm phản hồi
            if docs:
                # Tạo prompt cho LLM tổng hợp phản hồi nếu có nhiều kết quả
                if len(docs) > 3:
                    content_parts = []
                    for i, doc in enumerate(docs[:10]):  # Giới hạn 10 kết quả
                        content_parts.append(f"[Đoạn {i+1}]: {doc.page_content}")
                    
                    content = "\n\n".join(content_parts)
                    
                    # Sử dụng LLM để tổng hợp phản hồi (nếu có thể)
                    try:
                        if self._gemini_model or self._openai_client:
                            prompt = f"""Dựa trên các đoạn văn dưới đây, hãy tạo một câu trả lời NGẮN GỌN và SÚC TÍCH, trích dẫn thẳng thắn từ các đoạn, không thêm thông tin:

{content}

Câu hỏi: {query}

Yêu cầu: Chỉ sử dụng thông tin từ các đoạn văn được cung cấp, TRÍCH DẪN TRỰC TIẾP những phần liên quan. Nếu là định nghĩa, hãy trích dẫn chính xác và nguyên văn. Chỉ trả lời dựa trên thông tin thực sự có trong các đoạn văn."""
                        
                            if self._gemini_model:
                                response = self._gemini_model.generate_content(prompt)
                                enhanced_content = response.text
                            elif self._openai_client:
                                messages = [
                                    {"role": "system", "content": "Bạn là trợ lý AI tổng hợp thông tin từ tài liệu. Nhiệm vụ của bạn là trích dẫn chính xác và nguyên văn từ các đoạn văn được cung cấp."},
                                    {"role": "user", "content": prompt}
                                ]
                                response = self._openai_client.chat.completions.create(
                                    model=settings.OPENAI_MODEL,
                                    messages=messages,
                                    temperature=0.1,
                                    max_tokens=300
                                )
                                enhanced_content = response.choices[0].message.content
                            
                            # Đảm bảo phản hồi không quá dài
                            if len(enhanced_content) > 1000:
                                enhanced_content = enhanced_content[:1000] + "..."
                            
                            content = enhanced_content
                    except Exception as e:
                        logger.error(f"Lỗi khi tổng hợp phản hồi: {e}")
                        # Quay lại cách xử lý mặc định
                        content = "\n\n".join([doc.page_content for doc in docs[:5]])  # Giới hạn 5 kết quả để không quá dài
                else:
                    # Nếu chỉ có ít kết quả, sử dụng trực tiếp
                    content = "\n\n".join([doc.page_content for doc in docs])
                
                # Log thông tin trích dẫn
                logger.info(f"Tổng số trích dẫn: {len(citations)}")
                
                # Đảm bảo có citations trong kết quả
                response = {
                    "success": True,
                    "response": f"Theo dữ liệu tìm được:\n\n{content}",
                    "query": query,
                    "doc_count": len(docs),
                    "citations": citations,
                    "detailed_citations": detailed_citations
                }
                
                logger.info("Trả về kết quả với citations")
                return response
            else:
                # Không tìm thấy tài liệu
                if document_ids:
                    response = "Không tìm thấy thông tin liên quan đến câu hỏi của bạn trong các tài liệu đã chọn. Vui lòng thử lại với từ khóa khác hoặc diễn đạt khác."
                else:
                    response = "Không tìm thấy thông tin liên quan đến câu hỏi của bạn trong cơ sở dữ liệu."
                    
                return {
                    "success": True,
                    "response": response,
                    "query": query,
                    "doc_count": 0,
                    "citations": []  # Trả về mảng trống vì không có trích dẫn
                }
                
        except Exception as e:
            logger.error(f"Lỗi trong get_simple_retrieval: {str(e)}")
            logger.error(traceback.format_exc())
            return {
                "success": False,
                "response": "Xin lỗi, có lỗi xảy ra khi tìm kiếm thông tin. Vui lòng thử lại sau.",
                "query": query,
                "error": str(e),
                "citations": []  # Trả về mảng trống khi có lỗi
            }

    def extract_text_from_document(self, file_path, file_type):
        """Trích xuất văn bản từ tài liệu với nhiều định dạng khác nhau"""
        try:
            logger.info(f"Đang trích xuất văn bản từ tài liệu: {file_path}, loại: {file_type}")
            pages_text = []
            doc_metadata = {
                "source": os.path.basename(file_path),
                "total_pages": 0
            }
            
            # Xử lý theo loại file
            if "pdf" in file_type.lower():
                # Sử dụng PyPDFLoader
                loader = PyPDFLoader(file_path)
                pages = loader.load()
                
                # Lưu metadata
                doc_metadata["total_pages"] = len(pages)
                
                # Xử lý từng trang
                for i, page in enumerate(pages):
                    # Chuẩn hóa văn bản
                    text = page.page_content
                    text = re.sub(r'\s+', ' ', text)  # Loại bỏ khoảng trắng dư thừa
                    text = text.strip()
                    
                    if text:  # Chỉ thêm trang không trống
                        pages_text.append({
                            "page_num": i + 1,
                            "text": text
                        })
                        
            elif "word" in file_type.lower() or file_path.lower().endswith(('.docx', '.doc')):
                # Sử dụng Docx2txtLoader
                loader = Docx2txtLoader(file_path)
                docs = loader.load()
                
                # Word thường trả về một document duy nhất
                doc_metadata["total_pages"] = 1
                
                # Phân tách theo đoạn
                if docs:
                    text = docs[0].page_content
                    paragraphs = text.split('\n\n')
                    
                    # Gom các đoạn thành các trang ảo, mỗi trang ~2000 ký tự
                    current_page_text = ""
                    current_page_num = 1
                    
                    for para in paragraphs:
                        para = para.strip()
                        if not para:
                            continue
                            
                        if len(current_page_text) + len(para) > 2000:
                            # Lưu trang hiện tại
                            if current_page_text:
                                pages_text.append({
                                    "page_num": current_page_num,
                                    "text": current_page_text
                                })
                                current_page_num += 1
                                current_page_text = para
                        else:
                            if current_page_text:
                                current_page_text += "\n\n" + para
                            else:
                                current_page_text = para
                    
                    # Lưu trang cuối cùng
                    if current_page_text:
                        pages_text.append({
                            "page_num": current_page_num,
                            "text": current_page_text
                        })
                    
                    # Cập nhật tổng số trang
                    doc_metadata["total_pages"] = current_page_num
            
            elif "text" in file_type.lower() or file_path.lower().endswith(('.txt', '.md')):
                # Sử dụng TextLoader với encoding UTF-8
                try:
                    # Thử đọc với UTF-8
                    with open(file_path, 'r', encoding='utf-8') as f:
                        text = f.read()
                    logger.info("Đọc file text với encoding UTF-8")
                except UnicodeDecodeError:
                    # Nếu lỗi, thử đọc với latin-1
                    with open(file_path, 'r', encoding='latin-1') as f:
                        text = f.read()
                    logger.info("Đọc file text với encoding latin-1")
                
                # Phân tách theo dòng trống
                paragraphs = text.split('\n\n')
                
                # Gom các đoạn thành các trang ảo, mỗi trang ~2000 ký tự
                current_page_text = ""
                current_page_num = 1
                
                for para in paragraphs:
                    para = para.strip()
                    if not para:
                        continue
                        
                    if len(current_page_text) + len(para) > 2000:
                        # Lưu trang hiện tại
                        if current_page_text:
                            pages_text.append({
                                "page_num": current_page_num,
                                "text": current_page_text
                            })
                            current_page_num += 1
                            current_page_text = para
                        else:
                            if current_page_text:
                                current_page_text += "\n\n" + para
                            else:
                                current_page_text = para
                
                # Lưu trang cuối cùng
                if current_page_text:
                    pages_text.append({
                        "page_num": current_page_num,
                        "text": current_page_text
                    })
                
                # Cập nhật tổng số trang
                doc_metadata["total_pages"] = current_page_num
                
            else:
                logger.warning(f"Không hỗ trợ loại file: {file_type}")
                pages_text.append({
                    "page_num": 1,
                    "text": "Không thể đọc nội dung từ loại file này."
                })
                doc_metadata["total_pages"] = 1
            
            logger.info(f"Đã trích xuất {len(pages_text)} trang từ tài liệu")
            return pages_text, doc_metadata
            
        except Exception as e:
            logger.error(f"Lỗi khi trích xuất văn bản: {str(e)}")
            logger.error(traceback.format_exc())
            
            # Trả về kết quả trống nếu có lỗi
            return [{
                "page_num": 1,
                "text": f"Lỗi khi đọc tài liệu: {str(e)}"
            }], {
                "source": os.path.basename(file_path),
                "total_pages": 1,
                "error": str(e)
            }

    def create_text_splitter(self, chunk_size=500, chunk_overlap=100):
        """Tạo text splitter với các thông số tối ưu"""
        
        # Sử dụng RecursiveCharacterTextSplitter với các separators tối ưu
        # Ưu tiên chia theo đoạn văn, câu, từ, rồi đến ký tự
        separators = ["\n\n", "\n", ".", " ", ""]
        
        return RecursiveCharacterTextSplitter(
            chunk_size=chunk_size,
            chunk_overlap=chunk_overlap,
            separators=separators,
            length_function=len
        )

    def create_chunks_from_text(self, pages_text, doc_id, metadata, chunk_size=500, chunk_overlap=100):
        """Tạo các chunk từ văn bản đã trích xuất"""
        chunks = []
        
        # Sử dụng text splitter tối ưu 
        text_splitter = self.create_text_splitter(chunk_size, chunk_overlap)
        
        # Xử lý từng trang
        for page_info in pages_text:
            page_num = page_info["page_num"]
            page_text = page_info["text"]
            
            if not page_text or len(page_text.strip()) < 10:  # Bỏ qua trang không có văn bản hoặc quá ngắn
                continue
            
            # Chia trang thành các đoạn chunks
            splits = text_splitter.split_text(page_text)
            
            # Tạo chunk với metadata
            for i, chunk_text in enumerate(splits):
                # Tìm vị trí bắt đầu của chunk trong page_text (dùng để highlight chính xác)
                start_pos = page_text.find(chunk_text[:50])  # Dùng 50 ký tự đầu để xác định
                if start_pos == -1:  # Nếu không tìm thấy chính xác, dùng phương pháp gần đúng
                    start_pos = 0
                
                # Tạo thông tin vị trí đoạn văn chi tiết
                position_info = {
                    "start_pos": start_pos,
                    "end_pos": start_pos + len(chunk_text),
                    "length": len(chunk_text),
                    "paragraph_index": i
                }
                
                # Tạo URL trích dẫn chi tiết hơn
                citation_url = f"/documents/{doc_id}?page={page_num}&highlight={i}&pos={start_pos}"
                
                # Trích xuất vài chục ký tự đầu tiên làm preview
                preview_length = 200
                content_preview = chunk_text[:preview_length] + "..." if len(chunk_text) > preview_length else chunk_text
                
                chunk_metadata = {
                    "doc_id": doc_id,
                    "title": metadata.get("title", f"Tài liệu {doc_id}"),
                    "source": metadata["source"],
                    "page_num": page_num,
                    "chunk_index": i,
                    "position": position_info,
                    "content": content_preview,
                    "citation": f"{metadata.get('title', 'Tài liệu ' + str(doc_id))} - Trang {page_num}, đoạn {i}",
                    "url": citation_url
                }
                
                chunks.append({"text": chunk_text, "metadata": chunk_metadata})
        
        return chunks

    async def create_document_vector(self, doc_id, user_id, file_path, file_type, doc_title, chunk_size=None, chunk_overlap=None):
        """Quy trình tạo vector đầy đủ cho tài liệu"""
        try:
            # Sử dụng giá trị mặc định nếu không được chỉ định
            if chunk_size is None:
                chunk_size = settings.DEFAULT_CHUNK_SIZE
            if chunk_overlap is None:
                chunk_overlap = settings.DEFAULT_CHUNK_OVERLAP
            
            logger.info(f"Bắt đầu tạo vector cho tài liệu {doc_id}, người dùng: {user_id} với chunk_size={chunk_size}, chunk_overlap={chunk_overlap}")
            
            # 1. Trích xuất văn bản
            pages_text, doc_metadata = self.extract_text_from_document(file_path, file_type)
            doc_metadata["title"] = doc_title
            
            # 2. Tách đoạn với thông số tối ưu
            chunks = self.create_chunks_from_text(
                pages_text, 
                doc_id, 
                doc_metadata, 
                chunk_size=chunk_size,
                chunk_overlap=chunk_overlap
            )
            
            # 3. Tạo embeddings và lưu vào FAISS
            # Đảm bảo thư mục tồn tại
            vector_dir = f"{settings.UPLOAD_VECTOR_DIR}/{user_id}/{doc_id}"
            os.makedirs(vector_dir, exist_ok=True)
            
            # Khởi tạo embedding model
            embeddings = HuggingFaceEmbeddings(
                model_name=settings.EMBEDDING_MODEL,
                model_kwargs={"device": "cuda" if torch.cuda.is_available() else "cpu"}
            )
            
            # Tạo danh sách documents cho FAISS
            documents = []
            for chunk in chunks:
                doc = Document(
                    page_content=chunk["text"],
                    metadata=chunk["metadata"]
                )
                documents.append(doc)
            
            # Tạo và lưu FAISS index
            vector_store = FAISS.from_documents(documents, embeddings)
            vector_store.save_local(vector_dir)
            
            # 4. Lưu metadata của tài liệu
            with open(f"{vector_dir}/document_metadata.json", "w", encoding="utf-8") as f:
                json.dump({
                    "doc_id": doc_id,
                    "user_id": user_id,
                    "title": doc_title,
                    "source": doc_metadata["source"],
                    "total_pages": doc_metadata["total_pages"],
                    "total_chunks": len(chunks),
                    "embedding_model": settings.EMBEDDING_MODEL,
                    "created_at": time.strftime("%Y-%m-%d %H:%M:%S"),
                    "chunk_size": chunk_size,
                    "chunk_overlap": chunk_overlap
                }, f, ensure_ascii=False, indent=2)
            
            # 5. Lưu cả nội dung văn bản gốc để trích dẫn
            with open(f"{vector_dir}/original_text.json", "w", encoding="utf-8") as f:
                json.dump(pages_text, f, ensure_ascii=False, indent=2)
            
            logger.info(f"Đã tạo vector thành công cho tài liệu {doc_id} với {len(chunks)} chunks")
            return {
                "success": True,
                "message": f"Đã tạo vector thành công cho tài liệu {doc_id}",
                "document_id": doc_id,
                "total_chunks": len(chunks)
            }
            
        except Exception as e:
            logger.error(f"Lỗi khi tạo vector cho tài liệu {doc_id}: {str(e)}")
            logger.error(traceback.format_exc())
            return {
                    "success": False,
                "message": f"Lỗi khi tạo vector: {str(e)}",
                "document_id": doc_id,
                "error": str(e)
            }

    async def query_document_with_citation(self, query, doc_ids, top_k=None):
        """Truy vấn tài liệu và trả về kết quả kèm trích dẫn"""
        # Sử dụng giá trị mặc định nếu không được chỉ định
        if top_k is None:
            top_k = settings.DEFAULT_TOP_K
            
        # Log chi tiết các tham số
        logger.info(f"Truy vấn tài liệu với query: '{query}', doc_ids: {doc_ids}, top_k: {top_k}")
        
        # Tiền xử lý query để tăng khả năng tìm kiếm
        processed_query = query.strip().lower()
        
        # Tạo các biến thể của truy vấn để tìm kiếm tốt hơn (đặc biệt cho truy vấn định nghĩa)
        expanded_queries = [processed_query]
        is_definition_query = False
        if "là gì" in processed_query:
            is_definition_query = True
            # Trích xuất khái niệm từ câu hỏi "X là gì"
            concept = processed_query.replace("là gì", "").strip()
            # Thêm các biến thể
            expanded_queries.extend([
                f"định nghĩa {concept}",
                f"{concept} được định nghĩa",
                f"{concept} có nghĩa",
                f"khái niệm {concept}"
            ])
            logger.info(f"Mở rộng truy vấn định nghĩa: {expanded_queries}")
        
        results = []
        citations = []
        detailed_citations = [] # Thêm danh sách chi tiết trích dẫn
        
        for doc_id in doc_ids:
            # Lấy thông tin user_id từ database hoặc từ request
            # Trong khuôn khổ hiện tại, chúng ta sẽ tìm tất cả thư mục user_id có chứa doc_id
            user_id = await self.find_user_id_for_document(doc_id)
            
            if not user_id:
                logger.warning(f"Không tìm thấy user_id cho document_id: {doc_id}")
                continue
                
            # Đường dẫn đến vector store
            vector_dir = f"{settings.UPLOAD_VECTOR_DIR}/{user_id}/{doc_id}"
            
            if not os.path.exists(vector_dir):
                logger.warning(f"Không tìm thấy thư mục vector: {vector_dir}")
                continue
                
            try:
                # Đọc metadata
                metadata_path = f"{vector_dir}/document_metadata.json"
                if os.path.exists(metadata_path):
                    with open(metadata_path, "r", encoding="utf-8") as f:
                        doc_metadata = json.load(f)
                else:
                    doc_metadata = {"title": f"Tài liệu {doc_id}"}
                    
                # Tải embedding model
                embeddings = HuggingFaceEmbeddings(
                    model_name=settings.EMBEDDING_MODEL,
                    model_kwargs={"device": "cuda" if torch.cuda.is_available() else "cpu"}
                )
                
                # Tải vector store
                vector_store = FAISS.load_local(vector_dir, embeddings)
                
                # Thực hiện tìm kiếm với tất cả các biến thể query
                all_search_results = []
                
                for variant in expanded_queries:
                    # Tìm kiếm với biến thể hiện tại
                    search_results = vector_store.similarity_search_with_score(variant, k=top_k+5)  # Tăng số lượng kết quả
                    logger.info(f"Tìm thấy {len(search_results)} kết quả cho '{variant}' trong tài liệu {doc_id}")
                    all_search_results.extend(search_results)
                
                # Lọc kết quả trùng lặp và sắp xếp theo điểm
                unique_results = {}  # Sử dụng dict để lọc trùng lặp
                
                for doc, score in all_search_results:
                    content_hash = hash(doc.page_content)
                    # Lưu kết quả có điểm tốt nhất
                    if content_hash not in unique_results or score < unique_results[content_hash][1]:
                        unique_results[content_hash] = (doc, score)
                
                # Chuyển đổi dict trở lại thành list và sắp xếp theo điểm
                filtered_results = list(unique_results.values())
                filtered_results.sort(key=lambda x: x[1])  # Sắp xếp tăng dần theo điểm (điểm thấp = liên quan hơn)
                
                # Ngưỡng điểm thấp hơn (0.45) để tìm được nhiều kết quả hơn
                for doc, score in filtered_results:
                    if score < 0.45:  # Ngưỡng nới lỏng hơn
                        # Tính điểm liên quan cho truy vấn định nghĩa
                        if is_definition_query:
                            content_lower = doc.page_content.lower()
                            relevance_bonus = 0
                            
                            # Nếu có chứa khái niệm cần tìm
                            if concept in content_lower:
                                relevance_bonus += 0.1
                                
                            # Nếu có các cụm từ định nghĩa
                            if " là " in content_lower or "định nghĩa" in content_lower or "có nghĩa" in content_lower:
                                relevance_bonus += 0.15
                                
                            # Điều chỉnh điểm
                            adjusted_score = max(0.01, score - relevance_bonus)
                            logger.info(f"Điểm ban đầu: {score}, điểm sau điều chỉnh: {adjusted_score}")
                            score = adjusted_score
                        
                        # Thêm vào kết quả với điểm đã điều chỉnh
                        results.append(doc.page_content)
                        
                        # Log thông tin chi tiết về metadata
                        logger.info(f"Metadata của document: {json.dumps(doc.metadata, ensure_ascii=False)}")

                        # Xây dựng URL trích dẫn hoàn chỉnh
                        doc_url = doc.metadata.get("url", f"/documents/{doc_id}?page={doc.metadata.get('page_num', 1)}&highlight={doc.metadata.get('chunk_index', 0)}")
                        
                        # Đảm bảo URL trích dẫn đầy đủ
                        if not doc_url.startswith("http") and not doc_url.startswith("/"):
                            doc_url = f"/documents/{doc_id}?page={doc.metadata.get('page_num', 1)}&highlight={doc.metadata.get('chunk_index', 0)}"
                        
                        # Tạo thông tin trích dẫn
                        citation = {
                            "doc_id": doc_id,
                            "title": doc_metadata.get("title", f"Tài liệu {doc_id}"),
                            "source": doc.metadata.get("source", ""),
                            "page": doc.metadata.get("page_num", 1),  # Sử dụng page_num thay vì page
                            "chunk_index": doc.metadata.get("chunk_index", 0),
                            "url": doc_url,
                            "content_preview": doc.metadata.get("content", ""),
                            "score": float(score),  # Thêm điểm để có thể sắp xếp sau này
                            "citation_text": f"{doc_metadata.get('title', f'Tài liệu {doc_id}')} - Trang {doc.metadata.get('page_num', 1)}, đoạn {doc.metadata.get('chunk_index', 0)}"
                        }
                        logger.info(f"Tạo citation: {json.dumps(citation, ensure_ascii=False)}")
                        citations.append(citation)
                        
                        # Tạo trích dẫn chi tiết, có thể tiện lợi hơn cho frontend
                        detailed_citation = {
                            "text": doc.page_content,
                            "metadata": {
                                "doc_id": doc_id,
                                "title": doc_metadata.get("title", f"Tài liệu {doc_id}"),
                                "page": doc.metadata.get("page_num", 1),
                                "chunk_index": doc.metadata.get("chunk_index", 0),
                                "position": doc.metadata.get("position", {}),
                                "url": doc_url,
                                "citation_text": f"{doc_metadata.get('title', f'Tài liệu {doc_id}')} - Trang {doc.metadata.get('page_num', 1)}, đoạn {doc.metadata.get('chunk_index', 0)}",
                                "score": float(score)
                            }
                        }
                        detailed_citations.append(detailed_citation)
            
            except Exception as e:
                logger.error(f"Lỗi khi truy vấn tài liệu {doc_id}: {str(e)}")
                logger.error(traceback.format_exc())
                continue
        
        # Sắp xếp citations theo điểm
        citations.sort(key=lambda x: x.get("score", 1.0))
        
        # Log kết quả cuối cùng
        logger.info(f"Tổng số kết quả tìm được: {len(results)}")
        logger.info(f"Tổng số trích dẫn: {len(citations)}")
        
        # Sắp xếp kết quả để khớp với thứ tự của citations
        if citations and results:
            # Tạo ánh xạ giữa citations và results
            citation_map = {}
            for citation in citations:
                for result in results:
                    if citation.get("content_preview", "") in result:
                        citation_map[result] = citation.get("score", 1.0)
                        break
            
            # Sắp xếp kết quả theo điểm từ citations
            results.sort(key=lambda x: citation_map.get(x, 1.0))
        
        # Giới hạn số lượng kết quả để tránh quá nhiều
        results = results[:min(len(results), 8)]
        citations = citations[:min(len(citations), 8)]
        detailed_citations = detailed_citations[:min(len(detailed_citations), 8)]
        
        return {
            "results": results,
            "citations": citations,
            "detailed_citations": detailed_citations  # Thêm danh sách trích dẫn chi tiết
        }
    
    async def find_user_id_for_document(self, doc_id):
        """Tìm user_id cho một document_id cụ thể"""
        try:
            uploads_dir = settings.UPLOAD_VECTOR_DIR
            if not os.path.exists(uploads_dir):
                return None
                
            # Duyệt qua tất cả các thư mục user
            for user_folder in os.listdir(uploads_dir):
                user_path = os.path.join(uploads_dir, user_folder)
                if os.path.isdir(user_path):
                    # Kiểm tra có thư mục document không
                    doc_path = os.path.join(user_path, str(doc_id))
                    if os.path.exists(doc_path):
                        return user_folder
            
            # Thử tìm trong thư mục cũ
            old_doc_path = os.path.join(settings.DB_FAISS_PATH, f"doc_{doc_id}")
            if os.path.exists(old_doc_path):
                # Di chuyển sang cấu trúc mới
                new_doc_dir = f"{settings.UPLOAD_VECTOR_DIR}/0/{doc_id}"  # Gán cho user_id = 0 nếu không xác định
                os.makedirs(os.path.dirname(new_doc_dir), exist_ok=True)
                shutil.move(old_doc_path, new_doc_dir)
                logger.info(f"Đã chuyển tài liệu từ {old_doc_path} sang {new_doc_dir}")
                return "0"
                
            return None
        except Exception as e:
            logger.error(f"Lỗi khi tìm user_id cho document_id {doc_id}: {str(e)}")
            logger.error(traceback.format_exc())
            return None

    async def process_document(self, request) -> Dict[str, Any]:
        """Xử lý tài liệu và tạo vector embeddings"""
        logger.info(f"Xử lý tài liệu ID: {request.document_id}, Đường dẫn: {request.file_path}")
        try:
            # Khởi tạo các biến
            full_path = None
            original_path = request.file_path
            is_docker = os.path.exists('/app')
            current_dir = os.getcwd()
            logger.info(f"Thư mục hiện tại: {current_dir}, Docker: {is_docker}")
            
            # Thử đường dẫn tuyệt đối nếu có
            absolute_path = getattr(request, 'absolute_path', None)
            if absolute_path and os.path.exists(absolute_path):
                full_path = absolute_path
                logger.info(f"Sử dụng đường dẫn tuyệt đối: {full_path}")
            
            # BƯỚC 1: ƯU TIÊN KIỂM TRA Ở THƯ MỤC LARAVEL PUBLIC STORAGE
            if not full_path:
                # Xác định đường dẫn tới thư mục Laravel public storage
                laravel_storage_paths = []
                
                # Ưu tiên các đường dẫn Laravel 
                if is_docker:
                    laravel_storage_paths = [
                        f"/app/web/public/storage/documents/{request.document_id}/{os.path.basename(original_path)}",
                        f"/app/web/public/storage/{original_path}"
                    ]
                else:
                    laravel_storage_paths = [
                        f"web/public/storage/documents/{request.document_id}/{os.path.basename(original_path)}",
                        f"web/public/storage/{original_path}"
                    ]
                
                # Kiểm tra tất cả các đường dẫn Laravel storage
                for path in laravel_storage_paths:
                    logger.info(f"Kiểm tra đường dẫn Laravel: {path}")
                    if os.path.exists(path):
                        full_path = path
                        logger.info(f"Tìm thấy file trong Laravel storage: {full_path}")
                        break
            
            # BƯỚC 2: TÌM KIẾM TẬP TIN THEO ĐA DẠNG MẪU ĐƯỜNG DẪN
            if not full_path:
                # Tạo danh sách các đường dẫn có thể
                possible_paths = []
                
                # Xử lý với Laravel path mappings
                laravel_path_mappings = [
                    ("documents/", "/app/storage/documents/"),
                    ("documents/", "storage/documents/"),
                    ("storage/", "/app/storage/")
                ]
                
                # Thêm các mapping Laravel
                for prefix, docker_path in laravel_path_mappings:
                    if original_path.startswith(prefix):
                        mapped_path = original_path.replace(prefix, docker_path)
                        possible_paths.append(mapped_path)
                        if is_docker and not mapped_path.startswith('/app'):
                            possible_paths.append(f"/app/{mapped_path}")
                
                # Thêm các đường dẫn khác
                if is_docker:
                    # Trong Docker container
                    possible_paths.extend([
                        os.path.join('/app', settings.STORAGE_PATH, original_path),
                        os.path.join('/app/storage', original_path),
                        f"/app/{original_path}",
                        original_path
                    ])
                else:
                    # Trên máy local
                    possible_paths.extend([
                        os.path.join(current_dir, settings.STORAGE_PATH, original_path),
                        os.path.join(settings.STORAGE_PATH, original_path),
                        os.path.join('storage', original_path),
                        original_path
                    ])
                
                # Kiểm tra từng đường dẫn có thể
                for path in possible_paths:
                    logger.info(f"Kiểm tra đường dẫn: {path}")
                    if os.path.exists(path):
                        full_path = path
                        logger.info(f"Tìm thấy file tại: {full_path}")
                        break
            
            # BƯỚC 3: TÌM KIẾM GẦN ĐÚNG NẾU KHÔNG TÌM THẤY CHÍNH XÁC
            if not full_path:
                logger.warning(f"Không tìm thấy file theo đường dẫn chính xác. Thử tìm gần đúng...")
                
                # Tìm kiếm trong các thư mục chính
                search_dirs = []
                base_filename = os.path.basename(original_path)
                
                if is_docker:
                    search_dirs = [
                        f"/app/web/public/storage/documents/{request.document_id}",
                        "/app/web/public/storage/documents",
                        "/app/storage/documents",
                        f"/app/storage/documents/{request.document_id}"
                    ]
                else:
                    search_dirs = [
                        f"web/public/storage/documents/{request.document_id}",
                        "web/public/storage/documents",
                        "storage/documents", 
                        f"storage/documents/{request.document_id}"
                    ]
                
                # Thêm đường dẫn từ user_id nếu có trong request
                user_id = getattr(request, 'user_id', None)
                if user_id:
                    if is_docker:
                        search_dirs.append(f"/app/web/public/storage/documents/{user_id}")
                        search_dirs.append(f"/app/storage/documents/{user_id}")
                    else:
                        search_dirs.append(f"web/public/storage/documents/{user_id}")
                        search_dirs.append(f"storage/documents/{user_id}")
                
                # Tìm file có tên tương tự trong các thư mục
                for search_dir in search_dirs:
                    if os.path.exists(search_dir):
                        logger.info(f"Tìm kiếm gần đúng trong: {search_dir}")
                        try:
                            files = os.listdir(search_dir)
                            logger.info(f"Số lượng file trong {search_dir}: {len(files)}")
                            
                            # Tìm kiếm file có tên gần đúng
                            for filename in files:
                                # Loại bỏ timestamp nếu có (thường được Laravel thêm vào)
                                clean_name = re.sub(r'^\d+_', '', filename)
                                
                                if base_filename.lower() in filename.lower() or \
                                   clean_name.lower() == base_filename.lower() or \
                                   os.path.splitext(base_filename)[0].lower() in filename.lower():
                                    full_path = os.path.join(search_dir, filename)
                                    logger.info(f"Tìm thấy file gần đúng: {full_path}")
                                    break
                            
                            if full_path:
                                break
                        except Exception as dir_err:
                            logger.error(f"Lỗi khi đọc thư mục {search_dir}: {str(dir_err)}")
            
            # Kiểm tra file cuối cùng
            if not full_path or not os.path.exists(full_path):
                logger.error(f"Không tìm thấy file! Chi tiết:")
                logger.error(f"- Đường dẫn gốc: {original_path}")
                logger.error(f"- Document ID: {request.document_id}")
                logger.error(f"- Môi trường: {'Docker' if is_docker else 'Local'}")
                
                # Log thông tin thư mục để debug
                common_dirs = ["web/public/storage/documents", "storage/documents"]
                for cdir in common_dirs:
                    dir_path = cdir if not is_docker else f"/app/{cdir}"
                    if os.path.exists(dir_path):
                        subdirs = os.listdir(dir_path)
                        logger.info(f"Thư mục con trong {dir_path}: {subdirs}")
                
                return {
                    "success": False,
                    "message": "File không tồn tại, vui lòng kiểm tra lại đường dẫn",
                    "document_id": request.document_id,
                    "error": f"File not found: {original_path}",
                    "search_paths": possible_paths if 'possible_paths' in locals() else []
                }
            
            # Xác định loại file
            if request.file_type:
                mime_type = request.file_type
            else:
                # Đoán mime type từ đuôi file
                file_ext = os.path.splitext(full_path)[1].lower()
                if file_ext == '.pdf':
                    mime_type = 'application/pdf'
                elif file_ext in ['.docx', '.doc']:
                    mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                elif file_ext == '.txt':
                    mime_type = 'text/plain'
                elif file_ext == '.md':
                    mime_type = 'text/markdown'
                else:
                    mime_type = 'application/octet-stream'
            
            # Lấy user_id từ request nếu có
            user_id = getattr(request, 'user_id', 0)
            
            # Lấy thông số chunk từ request hoặc sử dụng giá trị mặc định
            chunk_size = getattr(request, 'chunk_size', settings.DEFAULT_CHUNK_SIZE)
            chunk_overlap = getattr(request, 'chunk_overlap', settings.DEFAULT_CHUNK_OVERLAP)
            
            # Gọi hàm tạo vector tối ưu
            result = await self.create_document_vector(
                doc_id=request.document_id,
                user_id=user_id,
                file_path=full_path,
                file_type=mime_type,
                doc_title=request.title or os.path.basename(full_path),
                chunk_size=chunk_size,
                chunk_overlap=chunk_overlap
            )
            
            return result
                
        except Exception as e:
            logger.error(f"Lỗi xử lý tài liệu: {e}")
            logger.error(traceback.format_exc())
            return {
                "success": False,
                "message": "Lỗi xử lý tài liệu",
                "document_id": request.document_id,
                "error": str(e)
            }

    async def integrate_document(self, document_id: int) -> Dict[str, Any]:
        """Tích hợp vector của tài liệu vào vector database chính"""
        try:
            logger.info(f"Đang tích hợp vector của tài liệu ID: {document_id}")
            
            # Tìm user_id của tài liệu
            user_id = await self.find_user_id_for_document(document_id)
            if not user_id:
                logger.error(f"Không tìm thấy thông tin user_id cho tài liệu {document_id}")
                return {
                    "success": False,
                    "message": f"Không thể tích hợp: không tìm thấy vector của tài liệu {document_id}",
                    "document_id": document_id,
                    "error": "Document vector not found"
                }
            
            # Đường dẫn tới vector database của tài liệu
            document_vectors_path = f"{settings.UPLOAD_VECTOR_DIR}/{user_id}/{document_id}"
            
            # Kiểm tra có tồn tại không
            if not os.path.exists(document_vectors_path) or not os.path.exists(os.path.join(document_vectors_path, "index.faiss")):
                logger.error(f"Không tìm thấy đường dẫn vector của tài liệu: {document_vectors_path}")
                return {
                    "success": False,
                    "message": f"Không thể tích hợp vector của tài liệu {document_id}",
                    "document_id": document_id,
                    "error": "Vector store not found"
                }
            
            # Đọc metadata của tài liệu
            doc_title = f"Tài liệu {document_id}"
            metadata_path = f"{document_vectors_path}/document_metadata.json"
            if os.path.exists(metadata_path):
                try:
                    with open(metadata_path, "r", encoding="utf-8") as f:
                        doc_metadata = json.load(f)
                        doc_title = doc_metadata.get("title", f"Tài liệu {document_id}")
                        logger.info(f"Đọc metadata của tài liệu: {doc_title}")
                except Exception as e:
                    logger.error(f"Lỗi khi đọc metadata: {str(e)}")
            
            # Tải vector database chính
            embedding = HuggingFaceEmbeddings(model_name=settings.EMBEDDING_MODEL)
            
            # Nếu vector database chính không tồn tại, tạo mới
            main_vector_path = settings.DB_FAISS_PATH
            
            if not os.path.exists(os.path.join(main_vector_path, "index.faiss")):
                logger.info("Không tìm thấy vector database chính, đang tạo mới")
                empty_texts = ["Đây là vector database ban đầu."]
                main_db = FAISS.from_texts(empty_texts, embedding)
                main_db.save_local(main_vector_path)
            
            # Tải vector database chính và vector database của tài liệu
            try:
                main_db = FAISS.load_local(main_vector_path, embedding, allow_dangerous_deserialization=True)
            except TypeError:
                main_db = FAISS.load_local(main_vector_path, embedding)
            
            try:
                doc_db = FAISS.load_local(document_vectors_path, embedding, allow_dangerous_deserialization=True)
            except TypeError:
                doc_db = FAISS.load_local(document_vectors_path, embedding)
            
            # Đảm bảo thông tin metadata của tài liệu được cập nhật trước khi kết hợp
            # Lấy tất cả documents từ doc_db để kiểm tra và cập nhật metadata
            doc_docs = doc_db.similarity_search("", k=1000)  # Lấy tất cả các documents trong cơ sở dữ liệu tài liệu
            
            # Đảm bảo mỗi document có thông tin trích dẫn đầy đủ
            updated_docs = []
            for doc in doc_docs:
                # Cập nhật metadata nếu thiếu thông tin quan trọng
                if "title" not in doc.metadata:
                    doc.metadata["title"] = doc_title
                
                # Đảm bảo có thông tin citation
                if "citation" not in doc.metadata:
                    page_num = doc.metadata.get("page_num", 1)
                    chunk_index = doc.metadata.get("chunk_index", 0)
                    doc.metadata["citation"] = f"{doc_title} - Trang {page_num}, đoạn {chunk_index}"
                
                # Đảm bảo có URL chi tiết
                if "url" not in doc.metadata or "&pos=" not in doc.metadata["url"]:
                    page_num = doc.metadata.get("page_num", 1)
                    chunk_index = doc.metadata.get("chunk_index", 0)
                    # Nếu không có thông tin position, dùng giá trị mặc định
                    pos = 0
                    if "position" in doc.metadata and "start_pos" in doc.metadata["position"]:
                        pos = doc.metadata["position"]["start_pos"]
                    doc.metadata["url"] = f"/documents/{document_id}?page={page_num}&highlight={chunk_index}&pos={pos}"
                
                # Thêm thông tin position nếu chưa có
                if "position" not in doc.metadata:
                    doc.metadata["position"] = {
                        "start_pos": 0,
                        "end_pos": len(doc.page_content),
                        "length": len(doc.page_content),
                        "paragraph_index": doc.metadata.get("chunk_index", 0)
                    }
                
                updated_docs.append(doc)
            
            # Tạo FAISS database mới với metadata đã được cập nhật
            if updated_docs:
                logger.info(f"Cập nhật metadata cho {len(updated_docs)} vectors của tài liệu {document_id}")
                updated_doc_db = FAISS.from_documents(updated_docs, embedding)
                
                # Lưu vector database cập nhật của tài liệu
                updated_doc_db.save_local(document_vectors_path)
                
                # Kết hợp vào vector database chính
                logger.info("Đang kết hợp vector của tài liệu vào vector database chính")
                main_db.merge_from(updated_doc_db)
            else:
                logger.info("Không có vectors nào cần cập nhật. Kết hợp trực tiếp.")
            # Kết hợp hai vector database
            logger.info("Đang kết hợp vector của tài liệu vào vector database chính")
            main_db.merge_from(doc_db)
            
            # Lưu lại vector database chính
            logger.info("Lưu vector database đã kết hợp")
            main_db.save_local(main_vector_path)
            
            # Khởi động lại retriever để áp dụng thay đổi
            if hasattr(self, '_db') and self._db is not None:
                del self._db
            
            self._db = main_db
            self._retriever = self._db.as_retriever(
                search_type="mmr",
                search_kwargs={
                    "k": 8,
                    "fetch_k": 30,
                    "lambda_mult": 0.7,
                    "score_threshold": 0.2
                }
            )
            
            # Kiểm tra kết quả
            if os.path.exists(os.path.join(main_vector_path, "index.faiss")):
                logger.info(f"Tích hợp vector thành công cho tài liệu {document_id}")
                return {
                    "success": True,
                    "message": f"Đã tích hợp vector của tài liệu {document_id} thành công",
                    "document_id": document_id
                }
            else:
                logger.error(f"Tích hợp vector thất bại cho tài liệu {document_id}")
                return {
                    "success": False,
                    "message": f"Không thể tích hợp vector của tài liệu {document_id}",
                    "document_id": document_id,
                    "error": "Integration failed"
                }
                
        except Exception as e:
            logger.error(f"Lỗi khi tích hợp vector của tài liệu: {e}")
            logger.error(traceback.format_exc())
            return {
                "success": False,
                "message": "Lỗi khi tích hợp vector",
                "document_id": document_id,
                "error": str(e)
            } 
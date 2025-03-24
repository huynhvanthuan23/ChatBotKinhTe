from langchain.embeddings import HuggingFaceEmbeddings
from langchain.vectorstores import FAISS
from langchain.chains import RetrievalQA
from langchain.prompts import PromptTemplate
from langchain.llms import LlamaCpp
from typing import Dict, Optional, Any

class VietnamDocChatbot:
    """Class quản lý chatbot trả lời câu hỏi dựa trên tài liệu."""
    
    def __init__(
        self, 
        db_path: str = "vector_db",
        model_path: str = "models/mistral-7b-instruct-v0.1.Q2_K.gguf",
        embedding_model: str = "sentence-transformers/all-MiniLM-L6-v2",
        temperature: float = 0.7,
        max_tokens: int = 2000,
        n_ctx: int = 2048,
        n_gpu_layers: int = 8
    ):
        """Khởi tạo chatbot với các tham số được cung cấp."""
        self.db_path = db_path
        self.model_path = model_path
        
        # Khởi tạo embedding model
        self.embeddings = HuggingFaceEmbeddings(model_name=embedding_model)
        
        # Tải vector store
        self.db = FAISS.load_local(
            db_path, 
            self.embeddings, 
            allow_dangerous_deserialization=True
        )
        
        # Tạo prompt template
        custom_prompt = """
        <<SYS>>
        Bạn là trợ lý AI hữu ích. Hãy trả lời câu hỏi dựa vào thông tin được cung cấp.Nếu không biết câu trả lời, hãy nói 'Tôi không tìm thấy thông tin liên quan'.
        <</SYS>>

        Context: {context}
        Question: {question} 
        """
        
        self.prompt = PromptTemplate(template=custom_prompt, input_variables=["context", "question"])
        
        # Tải GGUF model
        self.llm = LlamaCpp(
            model_path=model_path,
            temperature=temperature,
            max_tokens=max_tokens,
            n_ctx=n_ctx,
            n_gpu_layers=n_gpu_layers,
            verbose=False
        )
        
        # Tạo QA chain
        self.qa_chain = RetrievalQA.from_chain_type(
            llm=self.llm,
            chain_type="stuff",
            retriever=self.db.as_retriever(),
            chain_type_kwargs={"prompt": self.prompt}
        )
    
    def answer_question(self, query: str) -> Dict[str, Any]:
        """
        Trả lời câu hỏi dựa trên tài liệu.
        
        Args:
            query: Câu hỏi của người dùng
            
        Returns:
            Dict chứa kết quả trả lời
        """
        return self.qa_chain({"query": query})
    
    def get_answer_text(self, query: str) -> str:
        """
        Trả về chỉ phần văn bản của câu trả lời.
        
        Args:
            query: Câu hỏi của người dùng
            
        Returns:
            Văn bản câu trả lời
        """
        result = self.answer_question(query)
        return result['result']


def run_cli():
    """Chạy giao diện dòng lệnh đơn giản."""
    chatbot = VietnamDocChatbot()
    print("Chatbot: Xin chào! Tôi có thể giúp gì cho bạn?")
    
    while True:
        query = input("\nBạn: ")
        if query.lower() in ["exit", "quit"]:
            break
        
        answer = chatbot.get_answer_text(query)
        print(f"\nChatbot: {answer}")


if __name__ == "__main__":
    # Chạy CLI nếu file được thực thi trực tiếp
    run_cli()
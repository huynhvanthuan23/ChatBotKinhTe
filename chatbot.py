from langchain.embeddings import HuggingFaceEmbeddings
from langchain.vectorstores import FAISS
from langchain.chains import RetrievalQA
from langchain.prompts import PromptTemplate
from langchain.llms import LlamaCpp

# Cấu hình
DB_FAISS_PATH = "vector_db"
MODEL_PATH = "models/mistral-7b-instruct-v0.1.Q2_K.gguf"

# 1. Load vector store
embeddings = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
db = FAISS.load_local(
    DB_FAISS_PATH, 
    embeddings, 
    allow_dangerous_deserialization=True
)

# 2. Tạo prompt template
custom_prompt = """[INST] <<SYS>>
Bạn là trợ lý AI hữu ích. Hãy trả lời câu hỏi dựa vào thông tin được cung cấp.Nếu không biết câu trả lời, hãy nói 'Tôi không tìm thấy thông tin liên quan'.
<</SYS>>

Context: {context}
Question: {question} [/INST]"""

prompt = PromptTemplate(template=custom_prompt, input_variables=["context", "question"])

# 3. Load GGUF model
llm = LlamaCpp(
    model_path=MODEL_PATH,
    temperature=0.7,
    max_tokens=2000,
    n_ctx=2048,
    n_gpu_layers=8,
    verbose=False
)

# 4. Tạo QA chain
qa_chain = RetrievalQA.from_chain_type(
    llm=llm,
    chain_type="stuff",
    retriever=db.as_retriever(),
    chain_type_kwargs={"prompt": prompt}
)

# 5. Giao diện chatbot
print("Chatbot: Xin chào! Tôi có thể giúp gì cho bạn?")
while True:
    query = input("\nBạn: ")
    if query.lower() in ["exit", "quit"]:
        break
    
    result = qa_chain({"query": query})
    print(f"\nChatbot: {result['result']}")
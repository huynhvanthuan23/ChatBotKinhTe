import os
import argparse
import json
from langchain_community.embeddings import HuggingFaceEmbeddings, OpenAIEmbeddings
from langchain_community.vectorstores import FAISS
from langchain_core.prompts import PromptTemplate
from langchain.chains import RetrievalQA
from langchain_openai import ChatOpenAI
from langchain_google_genai import ChatGoogleGenerativeAI
from dotenv import load_dotenv

load_dotenv()

def test_simple_retrieval(vector_db_path, query, top_k=5):
    """Test simple retrieval without LLM to examine raw results"""
    print("\n=== TESTING SIMPLE RETRIEVAL (WITHOUT LLM) ===")
    print(f"Query: {query}")
    print(f"Vector DB: {vector_db_path}")
    
    # Load vector store
    try:
        embedding_model = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
        vectorstore = FAISS.load_local(vector_db_path, embedding_model, allow_dangerous_deserialization=True)
        
        # Perform similarity search
        docs = vectorstore.similarity_search(query, k=top_k)
        
        # Display results
        print(f"\nFound {len(docs)} relevant documents:")
        for i, doc in enumerate(docs):
            print(f"\n--- Document {i+1} ---")
            print(f"Content: {doc.page_content[:300]}..." if len(doc.page_content) > 300 else doc.page_content)
            print(f"Metadata: {json.dumps(doc.metadata, ensure_ascii=False, indent=2)}")
        
        return docs
    except Exception as e:
        print(f"Error in simple retrieval: {str(e)}")
        return []

def test_mmr_retrieval(vector_db_path, query, top_k=5):
    """Test MMR retrieval which optimizes for diversity"""
    print("\n=== TESTING MMR RETRIEVAL (MAXIMUM MARGINAL RELEVANCE) ===")
    print(f"Query: {query}")
    
    try:
        embedding_model = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
        vectorstore = FAISS.load_local(vector_db_path, embedding_model, allow_dangerous_deserialization=True)
        
        # Perform MMR search
        docs = vectorstore.max_marginal_relevance_search(
            query, 
            k=top_k, 
            fetch_k=top_k*3,  # Fetch more docs initially to select diverse ones
            lambda_mult=0.7  # 0.7 = balance between relevance and diversity
        )
        
        # Display results
        print(f"\nFound {len(docs)} relevant diverse documents:")
        for i, doc in enumerate(docs):
            print(f"\n--- Document {i+1} ---")
            print(f"Content: {doc.page_content[:300]}..." if len(doc.page_content) > 300 else doc.page_content)
            print(f"Metadata: {json.dumps(doc.metadata, ensure_ascii=False, indent=2)}")
        
        return docs
    except Exception as e:
        print(f"Error in MMR retrieval: {str(e)}")
        return []

def test_rag_pipeline(vector_db_path, query, api_type="openai"):
    """Test full RAG pipeline with LLM"""
    print("\n=== TESTING FULL RAG PIPELINE ===")
    print(f"Query: {query}")
    print(f"Using LLM API: {api_type}")
    
    try:
        # Load vector store
        embedding_model = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
        vectorstore = FAISS.load_local(vector_db_path, embedding_model, allow_dangerous_deserialization=True)
        
        # Create retriever with MMR
        retriever = vectorstore.as_retriever(
            search_type="mmr",
            search_kwargs={
                "k": 5,
                "fetch_k": 15,
                "lambda_mult": 0.7,
                "score_threshold": 0.5
            }
        )
        
        # Set up LLM
        if api_type.lower() == "openai":
            llm = ChatOpenAI(temperature=0.2, model="gpt-4o-mini")
        else:  # google
            llm = ChatGoogleGenerativeAI(model="gemini-1.5-pro", temperature=0.2)
        
        # Define prompt template
        prompt_template = """
        Bạn là trợ lý AI chuyên về kinh tế Việt Nam.
        
        Dưới đây là thông tin từ các tài liệu liên quan đến câu hỏi:
        
        {context}
        
        Dựa vào thông tin được cung cấp, hãy trả lời câu hỏi sau:
        
        Câu hỏi: {question}
        
        Yêu cầu:
        1. CHỈ sử dụng thông tin được cung cấp ở trên, KHÔNG sử dụng kiến thức bên ngoài
        2. Trích dẫn cụ thể từ tài liệu để hỗ trợ câu trả lời
        3. Nếu thông tin không đủ để trả lời, hãy nói "Tôi không có đủ thông tin từ tài liệu để trả lời câu hỏi này."
        4. Trả lời bằng tiếng Việt, đầy đủ và dễ hiểu
        
        Câu trả lời:
        """
        
        prompt = PromptTemplate(
            input_variables=["context", "question"],
            template=prompt_template
        )
        
        # Create QA chain
        qa_chain = RetrievalQA.from_chain_type(
            llm=llm,
            chain_type="stuff",
            retriever=retriever,
            chain_type_kwargs={"prompt": prompt}
        )
        
        # Run QA chain
        result = qa_chain.invoke({"query": query})
        
        # Display result
        print("\n=== RAG ANSWER ===")
        print(result["result"])
        
        # Also show retrieved documents for comparison
        docs = retriever.get_relevant_documents(query)
        print(f"\nRetrieved {len(docs)} documents for context.")
        
        return result["result"]
    except Exception as e:
        print(f"Error in RAG pipeline: {str(e)}")
        return f"Error: {str(e)}"

def main():
    parser = argparse.ArgumentParser(description="Test RAG components")
    parser.add_argument("--vector_db", required=True, help="Path to vector database")
    parser.add_argument("--query", required=True, help="Query to test")
    parser.add_argument("--mode", choices=["retrieval", "mmr", "rag"], default="rag", 
                      help="Test mode: simple retrieval, mmr, or full rag")
    parser.add_argument("--api", choices=["openai", "google"], default="openai", 
                      help="LLM API to use for RAG mode")
    
    args = parser.parse_args()
    
    if args.mode == "retrieval":
        test_simple_retrieval(args.vector_db, args.query)
    elif args.mode == "mmr":
        test_mmr_retrieval(args.vector_db, args.query)
    else:  # rag
        test_rag_pipeline(args.vector_db, args.query, args.api)

if __name__ == "__main__":
    main()

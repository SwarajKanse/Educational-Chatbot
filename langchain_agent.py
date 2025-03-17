from flask import Flask, request, jsonify, render_template
from langchain.agents import initialize_agent, Tool
from langchain_google_genai import ChatGoogleGenerativeAI
from langchain.memory import ConversationBufferMemory
from langchain.chains import LLMChain
from langchain.prompts import PromptTemplate
import os
import requests
import wikipediaapi
import json
from uuid import uuid4
from dotenv import load_dotenv
from werkzeug.utils import secure_filename
from document_processor import DocumentProcessor

# Load environment variables from .env file
load_dotenv()

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = './uploads'
app.config['MAX_CONTENT_LENGTH'] = 16 * 1024 * 1024  # 16MB max file size
app.config['ALLOWED_EXTENSIONS'] = {'txt', 'pdf', 'docx'}

# Initialize document processor
document_processor = DocumentProcessor()

# Function to check if file extension is allowed
def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in app.config['ALLOWED_EXTENSIONS']

# Retrieve keys and model from environment variables
GEMINI_MODEL = os.getenv("GEMINI_MODEL")  # e.g., "gemini-1.5-flash"
GOOGLE_API_KEY = os.getenv("GOOGLE_API_KEY")
WOLFRAM_ALPHA_APPID = os.getenv("WOLFRAM_ALPHA_APPID")
SERPAPI_API_KEY = os.getenv("SERPAPI_API_KEY")
YOUTUBE_API_KEY = os.getenv("YOUTUBE_API_KEY")

# Check that required variables are set
if not GOOGLE_API_KEY:
    raise ValueError("GOOGLE_API_KEY is not set. Please add it to your .env file.")
if not WOLFRAM_ALPHA_APPID:
    raise ValueError("WOLFRAM_ALPHA_APPID is not set. Please add it to your .env file.")

# Set the GOOGLE_API_KEY environment variable so ChatGoogleGenerativeAI uses it.
os.environ["GOOGLE_API_KEY"] = GOOGLE_API_KEY

# Initialize the LLM
llm = ChatGoogleGenerativeAI(model=GEMINI_MODEL, temperature=0)

# Create a conversation history file
CONVERSATION_HISTORY_FILE = "conversation_history.json"

# Initialize conversation history
if os.path.exists(CONVERSATION_HISTORY_FILE):
    with open(CONVERSATION_HISTORY_FILE, 'r') as f:
        try:
            conversation_history = json.load(f)
        except json.JSONDecodeError:
            conversation_history = {}
else:
    conversation_history = {}

# Function to save conversation history
def save_conversation_history():
    with open(CONVERSATION_HISTORY_FILE, 'w') as f:
        json.dump(conversation_history, f)

# Function to get or create a user session
def get_user_session(user_id):
    if user_id not in conversation_history:
        conversation_history[user_id] = {
            'session_id': str(uuid4()),
            'messages': [],
            'topic_context': None,
            'documents': []  # Track uploaded documents
        }
    return conversation_history[user_id]

# Define custom tools (existing tools from your original code)
def wolfram_alpha_tool(query: str) -> str:
    from langchain_community.utilities import WolframAlphaAPIWrapper
    wolfram = WolframAlphaAPIWrapper()
    try:
        result = wolfram.run(query)
        return result if result else "No result from Wolfram Alpha."
    except Exception as e:
        return f"Error using Wolfram Alpha: {str(e)}"

def serpapi_tool(query: str) -> str:
    from langchain_community.utilities import SerpAPIWrapper
    serpapi = SerpAPIWrapper()
    try:
        result = serpapi.run(query)
        return result if result else "No search results found."
    except Exception as e:
        return f"Error using SerpAPI: {str(e)}"

def youtube_tool(query: str) -> str:
    """Find a YouTube video that matches the query."""
    url = (
        f"https://www.googleapis.com/youtube/v3/search?"
        f"part=snippet&q={requests.utils.quote(query)}&key={YOUTUBE_API_KEY}&type=video&maxResults=3"
    )
    try:
        response = requests.get(url)
        data = response.json()
        if data.get('items'):
            results = []
            for i, item in enumerate(data['items'][:3]):  # Get up to 3 videos
                videoId = item['id']['videoId']
                title = item['snippet']['title']
                channel = item['snippet']['channelTitle']
                results.append(f"{i+1}. {title} by {channel}\n   Watch: https://www.youtube.com/watch?v={videoId}")
            return "\n\n".join(results)
        else:
            return "No videos found."
    except Exception as e:
        return f"Error using YouTube API: {str(e)}"

def wikipedia_tool(query: str) -> str:
    user_agent = "SkillDevBot/1.0 (example@example.com)"
    wiki_wiki = wikipediaapi.Wikipedia(user_agent=user_agent, language='en')
    page = wiki_wiki.page(query)
    if page.exists():
        return page.summary[:500] + ("..." if len(page.summary) > 500 else "")
    else:
        return "No Wikipedia page found for the query."

# New tool for document queries
def document_query_tool(query: str, session_id: str = None) -> str:
    """Query the uploaded documents for relevant information."""
    if not session_id:
        return "No active session to query documents."
    
    contexts = document_processor.query_documents(query, session_id, k=3)
    
    if not contexts:
        return "No relevant information found in the uploaded documents."
    
    # Format the context results
    result = "Here's what I found in your documents:\n\n"
    for i, context in enumerate(contexts, 1):
        # Add metadata if available
        metadata_str = ""
        if context["metadata"]:
            if "source" in context["metadata"]:
                metadata_str = f"Source: {context['metadata']['source']}"
            if "page" in context["metadata"]:
                metadata_str += f", Page: {context['metadata']['page']}"
                
        result += f"--- Excerpt {i} {metadata_str} ---\n{context['content']}\n\n"
    
    return result

# Create tools for the LangChain agent
def get_tools(session_id=None):
    tools = [
        Tool(
            name="WolframAlpha",
            func=wolfram_alpha_tool,
            description="Useful for math computations and calculations."
        ),
        Tool(
            name="SerpAPI",
            func=serpapi_tool,
            description="Useful for web searches and getting current information."
        ),
        Tool(
            name="YouTube",
            func=youtube_tool,
            description="Useful for finding educational videos. When the user asks for videos, ALWAYS use this tool."
        ),
        Tool(
            name="Wikipedia",
            func=wikipedia_tool,
            description="Useful for getting summaries of topics from Wikipedia."
        )
    ]
    
    # Add document query tool if session_id is provided
    if session_id:
        tools.append(
            Tool(
                name="DocumentQuery",
                func=lambda query: document_query_tool(query, session_id),
                description="Useful for querying information from user-uploaded documents. ALWAYS use this tool first when the user seems to be asking about content in their uploaded files."
            )
        )
    
    return tools

# Initialize the ConversationBufferMemory
memory = ConversationBufferMemory(
    memory_key="chat_history",
    return_messages=True
)

# Enhanced system prompt that includes document context
SYSTEM_PROMPT = """
You are a helpful student assistant with access to the following tools:
- Use "YouTube" to find educational videos.
- Use "WolframAlpha" for math-related questions or computations.
- Use "SerpAPI" to search the web for current information.
- Use "Wikipedia" to get summaries of topics from Wikipedia.
- Use "DocumentQuery" to search through the user's uploaded documents.

CRITICALLY IMPORTANT INSTRUCTIONS:
1. ALWAYS MAINTAIN CONTEXT from the conversation history.
2. When a user asks about content from their uploaded documents, ALWAYS use the DocumentQuery tool first.
3. When a user asks for educational videos, ALWAYS use the YouTube tool and provide multiple suggestions (up to 3).
4. When a user asks for "another" or "another one", understand they want another video on the same topic.
5. Always read through your chat history before responding to remember past interactions.
6. Respond concisely and accurately based on the full conversation context.
7. When responding to document queries, clearly indicate when your information comes from their uploaded files.
"""

# Initialize the agent with dynamic tools and system prompt
def get_agent(session_id=None):
    agent_tools = get_tools(session_id)
    
    agent = initialize_agent(
        agent_tools,
        llm,
        agent="zero-shot-react-description",
        verbose=True,
        handle_parsing_errors=True,
        memory=memory,
        agent_kwargs={"system_message": SYSTEM_PROMPT}
    )
    
    return agent

# Enhanced query analyzer template
query_analyzer_template = """
Given the previous conversation context and the current query, determine:
1. If this is a follow-up request that refers to a previous topic
2. If this query is likely asking about the user's uploaded documents
3. The appropriate way to handle the current query

Previous conversation context:
{context}

Current query: {query}

User has uploaded documents: {has_documents}

If the query contains words like "another," "more," "similar," or is very short and seems 
to be continuing the previous topic, create an enhanced query that includes the necessary context.

If the query mentions "file", "document", "upload", "pdf", or seems to be asking about content 
that might be in their documents, prioritize document search.

Your analysis should output ONE of:
1. ENHANCED_QUERY: [enhanced version of the query with context added]
2. DOCUMENT_QUERY: [reformat query to clearly search documents]
3. ORIGINAL: [keep the original query as is]

Examples:
- If previous context was about "Java DSA tutorials" and current query is "another one", respond with: "ENHANCED_QUERY: Find another Java DSA tutorial video"
- If query is "what does my document say about neural networks", respond with: "DOCUMENT_QUERY: neural networks"
- If query is new and unrelated to previous context, respond with: "ORIGINAL: [the original query]"

Your analysis:
"""

query_analyzer_chain = LLMChain(
    llm=llm,
    prompt=PromptTemplate(
        input_variables=["context", "query", "has_documents"],
        template=query_analyzer_template
    )
)

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/upload', methods=['POST'])
def upload_file():
    if 'file' not in request.files:
        return jsonify({"error": "No file part"}), 400
    
    file = request.files['file']
    user_id = request.form.get('user_id', 'default_user')
    
    if file.filename == '':
        return jsonify({"error": "No selected file"}), 400
        
    if file and allowed_file(file.filename):
        try:
            # Get user session
            session = get_user_session(user_id)
            session_id = session['session_id']
            
            # Save uploaded file
            file_path = document_processor.save_uploaded_file(file)
            
            # Process document and store in ChromaDB
            result = document_processor.process_document(file_path, session_id)
            
            # Add document info to session
            session['documents'].append({
                "filename": file.filename,
                "path": file_path,
                "upload_time": str(datetime.datetime.now())
            })
            save_conversation_history()
            
            # Respond to the user
            return jsonify({
                "success": True,
                "message": f"File '{file.filename}' uploaded and processed successfully.",
                "details": result
            })
        except Exception as e:
            return jsonify({"error": str(e)}), 500
    else:
        return jsonify({"error": f"File type not allowed. Supported types: {', '.join(app.config['ALLOWED_EXTENSIONS'])}"}), 400

@app.route('/query', methods=['POST'])
def query_agent():
    data = request.json
    user_query = data.get('message', '')
    user_id = data.get('user_id', 'default_user')
    
    if not user_query:
        return jsonify({"error": "No query provided."}), 400
    
    try:
        # Get user session
        session = get_user_session(user_id)
        session_id = session['session_id']
        
        # Check if user has documents
        has_documents = len(session.get('documents', [])) > 0
        
        # Add the user query to the conversation history
        session['messages'].append({'role': 'user', 'content': user_query})
        
        # Extract all messages for context
        context = "\n".join([f"{msg['role']}: {msg['content']}" for msg in session['messages']])
        
        # Initialize agent with or without document tool
        agent = get_agent(session_id if has_documents else None)
        
        # Use the query analyzer if there's context
        if len(session['messages']) > 1:
            analysis_result = query_analyzer_chain.run(
                context=context, 
                query=user_query,
                has_documents=str(has_documents)
            ).strip()
            
            print(f"Query analysis result: {analysis_result}")
            
            if analysis_result.startswith("ENHANCED_QUERY:"):
                # Use the enhanced query
                enhanced_query = analysis_result.split("ENHANCED_QUERY:")[1].strip()
                print(f"Enhanced query: '{enhanced_query}'")
                response = agent.run(enhanced_query)
                
                # Update topic context based on the query
                if 'java' in enhanced_query.lower() and 'dsa' in enhanced_query.lower():
                    session['topic_context'] = 'Java DSA tutorials'
                elif 'java' in enhanced_query.lower():
                    session['topic_context'] = 'Java programming tutorials'
            
            elif analysis_result.startswith("DOCUMENT_QUERY:"):
                # Use document-focused query
                doc_query = analysis_result.split("DOCUMENT_QUERY:")[1].strip()
                print(f"Document query: '{doc_query}'")
                response = agent.run(f"Search the uploaded documents for information about: {doc_query}")
            
            else:
                # Use original query
                response = agent.run(user_query)
                
                # Update topic context based on the query
                if 'java' in user_query.lower() and 'dsa' in user_query.lower():
                    session['topic_context'] = 'Java DSA tutorials'
                elif 'java' in user_query.lower() and 'video' in user_query.lower():
                    session['topic_context'] = 'Java programming tutorials'
        else:
            # First message, just run the agent with the original query
            response = agent.run(user_query)
        
        # Add the agent's response to the conversation history
        session['messages'].append({'role': 'assistant', 'content': response})
        
        # Save the updated conversation history
        save_conversation_history()
        
        return jsonify({"response": response})
    except Exception as e:
        import traceback
        traceback.print_exc()
        response = f"Error processing query: {str(e)}"
        return jsonify({"response": response})

@app.route('/list_documents', methods=['POST'])
def list_documents():
    """List all documents uploaded by a user"""
    data = request.json
    user_id = data.get('user_id', 'default_user')
    
    try:
        # Get user session
        session = get_user_session(user_id)
        
        # Return list of documents
        return jsonify({
            "success": True,
            "documents": session.get('documents', [])
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/clear_documents', methods=['POST'])
def clear_documents():
    """Clear all documents for a user"""
    data = request.json
    user_id = data.get('user_id', 'default_user')
    
    try:
        # Get user session
        session = get_user_session(user_id)
        session_id = session['session_id']
        
        # Clear documents from session
        session['documents'] = []
        save_conversation_history()
        
        # Clear ChromaDB collection
        collection_name = f"user_{session_id}"
        # This is a placeholder - you'd need to implement actual collection deletion in ChromaDB
        # For now, we'll just clear the session tracking
        
        return jsonify({
            "success": True,
            "message": "All documents cleared."
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    # Create necessary directories
    os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)
    
    # Add missing import
    import datetime
    
    app.run(port=5000)
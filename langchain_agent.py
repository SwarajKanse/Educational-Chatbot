from flask import Flask, request, jsonify
from langchain.agents import initialize_agent, Tool
from langchain_google_genai import ChatGoogleGenerativeAI
from langchain.memory import ConversationBufferMemory
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain.chains import LLMChain
from langchain.prompts import PromptTemplate
import os
import requests
import wikipediaapi
import json
from uuid import uuid4
from dotenv import load_dotenv

# Load environment variables from .env file
load_dotenv()
print("WOLFRAM_ALPHA_APPID:", os.getenv("WOLFRAM_ALPHA_APPID"))

app = Flask(__name__)

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

# Set the GOOGLE_API_KEY environment variable so that ChatGoogleGenerativeAI uses it.
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
            'topic_context': None
        }
    return conversation_history[user_id]

# Define custom tools
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
    user_agent = "SkillDevBot/1.0 (gesturax@gmail.com)"
    wiki_wiki = wikipediaapi.Wikipedia(user_agent=user_agent, language='en')
    page = wiki_wiki.page(query)
    if page.exists():
        return page.summary[:500] + ("..." if len(page.summary) > 500 else "")
    else:
        return "No Wikipedia page found for the query."

# Create tools for the LangChain agent
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

# Initialize the ConversationBufferMemory - switched from SummaryBuffer to regular Buffer for better context retention
memory = ConversationBufferMemory(
    memory_key="chat_history",
    return_messages=True
)

# Enhanced system prompt that maintains context better
SYSTEM_PROMPT = """
You are a helpful student assistant with access to the following tools:
- Use "YouTube" to find educational videos.
- Use "WolframAlpha" for math-related questions or computations.
- Use "SerpAPI" to search the web for current information.
- Use "Wikipedia" to get summaries of topics from Wikipedia.

CRITICALLY IMPORTANT INSTRUCTIONS:
1. ALWAYS MAINTAIN CONTEXT from the conversation history.
2. When a user asks for educational videos, ALWAYS use the YouTube tool and provide multiple suggestions (up to 3).
3. When a user asks for "another" or "another one", understand they want another video on the same topic.
4. If a user asks for a Java programming video, then asks for "another one", search for another Java programming video.
5. If a user asks for a specific type like "Java DSA tutorial", maintain that specific context for follow-up requests.
6. Always read through your chat history before responding to remember past interactions.
7. Respond concisely and accurately based on the full conversation context.
"""

# Initialize the agent with the system prompt and memory.
agent = initialize_agent(
    tools,
    llm,
    agent="zero-shot-react-description",
    verbose=True,
    handle_parsing_errors=True,
    memory=memory,
    agent_kwargs={"system_message": SYSTEM_PROMPT}
)

# Enhanced query analyzer template
query_analyzer_template = """
Given the previous conversation context and the current query, determine:
1. If this is a follow-up request that refers to a previous topic
2. The appropriate way to handle the current query

Previous conversation context:
{context}

Current query: {query}

If the query contains words like "another," "more," "similar," or is very short and seems 
to be continuing the previous topic, create an enhanced query that includes the necessary context.

Your analysis should output ONE of:
1. ENHANCED_QUERY: [enhanced version of the query with context added]
2. ORIGINAL: [keep the original query as is]

Examples:
- If previous context was about "Java DSA tutorials" and current query is "another one", respond with: "ENHANCED_QUERY: Find another Java DSA tutorial video"
- If query is new and unrelated to previous context, respond with: "ORIGINAL: [the original query]"

Your analysis:
"""

query_analyzer_chain = LLMChain(
    llm=llm,
    prompt=PromptTemplate(
        input_variables=["context", "query"],
        template=query_analyzer_template
    )
)

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
        
        # Add the user query to the conversation history
        session['messages'].append({'role': 'user', 'content': user_query})
        
        # Extract all messages for context
        context = "\n".join([f"{msg['role']}: {msg['content']}" for msg in session['messages']])
        
        # Use the query analyzer if there's context
        if len(session['messages']) > 1:
            analysis_result = query_analyzer_chain.run(
                context=context, 
                query=user_query
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

if __name__ == '__main__':
    app.run(port=5000)
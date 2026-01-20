# Tutorial 17: RAG-Enhanced Workflows

Learn how to integrate claude-php-agent's RAG (Retrieval Augmented Generation) capabilities for knowledge-augmented ASL workflows.

## What You'll Learn

- Understanding RAG architecture and benefits
- Setting up vector stores and embeddings
- Creating RAG-enabled agent adapters
- Document ingestion and retrieval states
- Building a knowledge-base Q&A workflow
- Combining RAG with multi-agent patterns

## Prerequisites

- Completed [Tutorial 16: Loop Strategies in Workflows](16-loop-strategies-in-workflows.md)
- Understanding of embeddings and vector databases

## Why RAG?

RAG enhances AI agents with external knowledge:

| Without RAG | With RAG |
|-------------|----------|
| Limited to training data | Access to current knowledge |
| May hallucinate facts | Grounded in source documents |
| Generic responses | Context-specific answers |
| Static knowledge | Dynamic, updatable knowledge |

## Step 1: Understanding RAG Architecture

```
Query → Embed → Search Vector Store → Retrieve Documents → Augment Prompt → Generate
```

Key components:
1. **Embedding Model** - Converts text to vectors
2. **Vector Store** - Stores and searches embeddings
3. **Retriever** - Finds relevant documents
4. **Generator** - Produces answers using retrieved context

## Step 2: Create RAG Components

Create `src/RAG/SimpleVectorStore.php`:

```php
<?php

namespace MyOrg\RAG;

/**
 * Simple in-memory vector store for demonstration.
 * In production, use Pinecone, Weaviate, Qdrant, or similar.
 */
class SimpleVectorStore
{
    /** @var array<string, array{embedding: array, content: string, metadata: array}> */
    private array $documents = [];

    /**
     * Add a document with its embedding.
     */
    public function add(string $id, array $embedding, string $content, array $metadata = []): void
    {
        $this->documents[$id] = [
            'embedding' => $embedding,
            'content' => $content,
            'metadata' => $metadata,
        ];
    }

    /**
     * Search for similar documents.
     *
     * @param array $queryEmbedding Query vector
     * @param int $topK Number of results
     * @return array<array{id: string, content: string, score: float, metadata: array}>
     */
    public function search(array $queryEmbedding, int $topK = 5): array
    {
        $scores = [];

        foreach ($this->documents as $id => $doc) {
            $score = $this->cosineSimilarity($queryEmbedding, $doc['embedding']);
            $scores[$id] = [
                'id' => $id,
                'content' => $doc['content'],
                'score' => $score,
                'metadata' => $doc['metadata'],
            ];
        }

        // Sort by score descending
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_values($scores), 0, $topK);
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Get document count.
     */
    public function count(): int
    {
        return count($this->documents);
    }

    /**
     * Clear all documents.
     */
    public function clear(): void
    {
        $this->documents = [];
    }
}
```

Create `src/RAG/SimpleEmbedder.php`:

```php
<?php

namespace MyOrg\RAG;

use ClaudePhp\ClaudePhp;

/**
 * Simple embedder using Claude for text embeddings.
 * In production, use OpenAI embeddings, Cohere, or similar.
 */
class SimpleEmbedder
{
    private int $dimensions;

    public function __construct(int $dimensions = 384)
    {
        $this->dimensions = $dimensions;
    }

    /**
     * Generate embedding for text.
     * This is a simple hash-based simulation.
     * In production, use a real embedding API.
     */
    public function embed(string $text): array
    {
        // Normalize text
        $text = strtolower(trim($text));
        $words = preg_split('/\s+/', $text);

        // Create a simple bag-of-words style embedding
        $embedding = array_fill(0, $this->dimensions, 0.0);

        foreach ($words as $word) {
            $hash = crc32($word);
            for ($i = 0; $i < min(10, $this->dimensions); $i++) {
                $index = ($hash + $i * 37) % $this->dimensions;
                $embedding[$index] += 1.0 / (1 + $i);
            }
        }

        // Normalize
        $norm = sqrt(array_sum(array_map(fn($x) => $x * $x, $embedding)));
        if ($norm > 0) {
            $embedding = array_map(fn($x) => $x / $norm, $embedding);
        }

        return $embedding;
    }

    /**
     * Embed multiple texts.
     *
     * @param array<string> $texts
     * @return array<array>
     */
    public function embedBatch(array $texts): array
    {
        return array_map(fn($text) => $this->embed($text), $texts);
    }
}
```

Create `src/RAG/KnowledgeBase.php`:

```php
<?php

namespace MyOrg\RAG;

/**
 * Knowledge base that combines embedding and vector search.
 */
class KnowledgeBase
{
    private SimpleVectorStore $vectorStore;
    private SimpleEmbedder $embedder;
    private int $documentCounter = 0;

    public function __construct(
        ?SimpleVectorStore $vectorStore = null,
        ?SimpleEmbedder $embedder = null
    ) {
        $this->vectorStore = $vectorStore ?? new SimpleVectorStore();
        $this->embedder = $embedder ?? new SimpleEmbedder();
    }

    /**
     * Ingest a document into the knowledge base.
     */
    public function ingest(string $content, array $metadata = []): string
    {
        $id = 'doc_' . (++$this->documentCounter);

        // Chunk if needed (simple paragraph chunking)
        $chunks = $this->chunkDocument($content);

        foreach ($chunks as $i => $chunk) {
            $chunkId = "{$id}_chunk_{$i}";
            $embedding = $this->embedder->embed($chunk);
            $this->vectorStore->add($chunkId, $embedding, $chunk, array_merge($metadata, [
                'documentId' => $id,
                'chunkIndex' => $i,
            ]));
        }

        return $id;
    }

    /**
     * Ingest multiple documents.
     *
     * @param array<array{content: string, metadata?: array}> $documents
     * @return array<string> Document IDs
     */
    public function ingestBatch(array $documents): array
    {
        $ids = [];
        foreach ($documents as $doc) {
            $ids[] = $this->ingest($doc['content'], $doc['metadata'] ?? []);
        }
        return $ids;
    }

    /**
     * Query the knowledge base.
     *
     * @param string $query Query text
     * @param int $topK Number of results
     * @return array<array{content: string, score: float, metadata: array}>
     */
    public function query(string $query, int $topK = 5): array
    {
        $queryEmbedding = $this->embedder->embed($query);
        return $this->vectorStore->search($queryEmbedding, $topK);
    }

    /**
     * Get the number of chunks in the knowledge base.
     */
    public function getChunkCount(): int
    {
        return $this->vectorStore->count();
    }

    /**
     * Clear the knowledge base.
     */
    public function clear(): void
    {
        $this->vectorStore->clear();
        $this->documentCounter = 0;
    }

    /**
     * Chunk a document into smaller pieces.
     */
    private function chunkDocument(string $content, int $maxChunkSize = 500): array
    {
        // Simple paragraph-based chunking
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $chunks = [];
        $currentChunk = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }

            if (strlen($currentChunk) + strlen($paragraph) < $maxChunkSize) {
                $currentChunk .= ($currentChunk ? "\n\n" : '') . $paragraph;
            } else {
                if ($currentChunk) {
                    $chunks[] = $currentChunk;
                }
                $currentChunk = $paragraph;
            }
        }

        if ($currentChunk) {
            $chunks[] = $currentChunk;
        }

        return $chunks ?: [$content];
    }
}
```

## Step 3: Create RAG Agent Adapter

Create `src/Adapters/RAGAgentAdapter.php`:

```php
<?php

namespace MyOrg\Adapters;

use AgentStateLanguage\Agents\AgentInterface;
use ClaudeAgents\Agent;
use ClaudeAgents\AgentResult;
use ClaudePhp\ClaudePhp;
use MyOrg\RAG\KnowledgeBase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * RAG-enabled agent adapter for ASL workflows.
 */
class RAGAgentAdapter implements AgentInterface
{
    private Agent $agent;
    private string $name;
    private KnowledgeBase $knowledgeBase;
    private int $retrievalCount;
    private float $relevanceThreshold;
    private array $lastRetrievedDocs = [];
    private ?AgentResult $lastResult = null;

    public function __construct(
        string $name,
        Agent $agent,
        KnowledgeBase $knowledgeBase,
        int $retrievalCount = 5,
        float $relevanceThreshold = 0.5
    ) {
        $this->name = $name;
        $this->agent = $agent;
        $this->knowledgeBase = $knowledgeBase;
        $this->retrievalCount = $retrievalCount;
        $this->relevanceThreshold = $relevanceThreshold;
    }

    /**
     * Create a RAG agent with knowledge base.
     */
    public static function create(
        string $name,
        ClaudePhp $client,
        KnowledgeBase $knowledgeBase,
        string $systemPrompt = '',
        int $retrievalCount = 5,
        ?LoggerInterface $logger = null
    ): self {
        $logger = $logger ?? new NullLogger();

        $agent = Agent::create($client)
            ->withName($name)
            ->withSystemPrompt($systemPrompt)
            ->withLogger($logger)
            ->maxTokens(4000);

        return new self($name, $agent, $knowledgeBase, $retrievalCount);
    }

    /**
     * Execute with RAG augmentation.
     */
    public function execute(array $parameters): array
    {
        // Extract query
        $query = $this->extractQuery($parameters);

        // Retrieve relevant documents
        $this->lastRetrievedDocs = $this->retrieve($query);

        // Build augmented prompt
        $augmentedPrompt = $this->buildAugmentedPrompt($query, $this->lastRetrievedDocs);

        // Run agent with augmented context
        $this->lastResult = $this->agent->run($augmentedPrompt);

        return $this->formatResult($this->lastResult);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get last retrieved documents.
     */
    public function getLastRetrievedDocs(): array
    {
        return $this->lastRetrievedDocs;
    }

    /**
     * Add document to knowledge base.
     */
    public function addDocument(string $content, array $metadata = []): string
    {
        return $this->knowledgeBase->ingest($content, $metadata);
    }

    /**
     * Get the underlying agent.
     */
    public function getAgent(): Agent
    {
        return $this->agent;
    }

    /**
     * Get the knowledge base.
     */
    public function getKnowledgeBase(): KnowledgeBase
    {
        return $this->knowledgeBase;
    }

    /**
     * Retrieve relevant documents for query.
     */
    private function retrieve(string $query): array
    {
        $results = $this->knowledgeBase->query($query, $this->retrievalCount);

        // Filter by relevance threshold
        return array_filter($results, fn($doc) => $doc['score'] >= $this->relevanceThreshold);
    }

    /**
     * Build augmented prompt with retrieved context.
     */
    private function buildAugmentedPrompt(string $query, array $documents): string
    {
        if (empty($documents)) {
            return "Question: {$query}\n\nNote: No relevant documents found in the knowledge base. Please answer based on your general knowledge, but indicate that the answer is not from the knowledge base.";
        }

        $context = "=== RELEVANT DOCUMENTS FROM KNOWLEDGE BASE ===\n\n";

        foreach ($documents as $i => $doc) {
            $num = $i + 1;
            $score = number_format($doc['score'] * 100, 1);
            $source = $doc['metadata']['source'] ?? 'Unknown';
            
            $context .= "[Document {$num}] (Relevance: {$score}%, Source: {$source})\n";
            $context .= $doc['content'] . "\n\n";
        }

        $context .= "=== END OF DOCUMENTS ===\n\n";

        return <<<PROMPT
{$context}
Based on the documents above, please answer the following question. 
Cite the relevant document numbers when possible.

Question: {$query}

Instructions:
1. Answer based primarily on the provided documents
2. If the documents don't contain enough information, say so
3. Cite document numbers for claims (e.g., [Document 1])
4. Be accurate and don't make up information
PROMPT;
    }

    /**
     * Extract query from parameters.
     */
    private function extractQuery(array $parameters): string
    {
        foreach (['query', 'question', 'prompt', 'message', 'input'] as $key) {
            if (isset($parameters[$key])) {
                return (string) $parameters[$key];
            }
        }

        return json_encode($parameters);
    }

    /**
     * Format result for ASL.
     */
    private function formatResult(AgentResult $result): array
    {
        $tokenUsage = $result->getTokenUsage();

        return [
            'response' => $result->getAnswer(),
            'success' => $result->isSuccess(),
            'sources' => array_map(fn($doc) => [
                'content' => substr($doc['content'], 0, 200) . '...',
                'score' => $doc['score'],
                'metadata' => $doc['metadata'],
            ], $this->lastRetrievedDocs),
            'sourceCount' => count($this->lastRetrievedDocs),
            'iterations' => $result->getIterations(),
            '_tokens' => ($tokenUsage['input'] ?? 0) + ($tokenUsage['output'] ?? 0),
            '_cost' => $this->calculateCost($tokenUsage),
            '_usage' => $tokenUsage,
        ];
    }

    private function calculateCost(array $tokenUsage): float
    {
        $inputCost = (($tokenUsage['input'] ?? 0) / 1_000_000) * 3.00;
        $outputCost = (($tokenUsage['output'] ?? 0) / 1_000_000) * 15.00;
        return $inputCost + $outputCost;
    }
}
```

## Step 4: Define the Workflow

Create `workflows/knowledge-qa.asl.json`:

```json
{
  "Comment": "Knowledge-base Q&A workflow with RAG",
  "Version": "1.0",
  "StartAt": "CheckKnowledgeBase",
  "States": {
    "CheckKnowledgeBase": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.ingestDocuments",
          "BooleanEquals": true,
          "Next": "IngestDocuments"
        }
      ],
      "Default": "QueryKnowledgeBase"
    },
    "IngestDocuments": {
      "Type": "Task",
      "Agent": "DocumentIngester",
      "Parameters": {
        "prompt.$": "States.Format('Process and summarize these documents for the knowledge base:\n\n{}', $.documents)"
      },
      "ResultPath": "$.ingestion",
      "Next": "QueryKnowledgeBase"
    },
    "QueryKnowledgeBase": {
      "Type": "Task",
      "Agent": "RAGAssistant",
      "Parameters": {
        "query.$": "$.question"
      },
      "ResultPath": "$.ragResult",
      "Next": "EvaluateAnswer"
    },
    "EvaluateAnswer": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.ragResult.sourceCount",
          "NumericEquals": 0,
          "Next": "FallbackAnswer"
        }
      ],
      "Default": "ValidateWithSources"
    },
    "FallbackAnswer": {
      "Type": "Task",
      "Agent": "GeneralAssistant",
      "Parameters": {
        "prompt.$": "States.Format('Answer this question based on your general knowledge, clearly stating this is not from the knowledge base:\n\n{}', $.question)"
      },
      "ResultPath": "$.fallbackResult",
      "Next": "FormatFallbackOutput"
    },
    "FormatFallbackOutput": {
      "Type": "Pass",
      "Parameters": {
        "question.$": "$.question",
        "answer.$": "$.fallbackResult.response",
        "fromKnowledgeBase": false,
        "sources": [],
        "confidence": "low",
        "metrics": {
          "tokens.$": "$.fallbackResult._tokens",
          "cost.$": "$.fallbackResult._cost"
        }
      },
      "End": true
    },
    "ValidateWithSources": {
      "Type": "Task",
      "Agent": "AnswerValidator",
      "Parameters": {
        "prompt.$": "States.Format('Validate this answer against the sources provided:\n\nQuestion: {}\n\nAnswer: {}\n\nSources Used: {}\n\nRate the answer accuracy (1-10) and identify any claims not supported by sources. Respond in JSON: {\"accuracy\": N, \"unsupportedClaims\": [...], \"improvements\": [...]}', $.question, $.ragResult.response, $.ragResult.sources)"
      },
      "ResultPath": "$.validation",
      "Next": "CheckValidation"
    },
    "CheckValidation": {
      "Type": "Choice",
      "Choices": [
        {
          "Variable": "$.validation.parsed.accuracy",
          "NumericGreaterThanEquals": 7,
          "Next": "FormatOutput"
        }
      ],
      "Default": "RefineAnswer"
    },
    "RefineAnswer": {
      "Type": "Task",
      "Agent": "RAGAssistant",
      "Parameters": {
        "query.$": "States.Format('Improve this answer based on feedback:\n\nOriginal Question: {}\n\nOriginal Answer: {}\n\nFeedback: {}\n\nProvide an improved, more accurate answer.', $.question, $.ragResult.response, $.validation.parsed.improvements)"
      },
      "ResultPath": "$.refinedResult",
      "Next": "FormatRefinedOutput"
    },
    "FormatRefinedOutput": {
      "Type": "Pass",
      "Parameters": {
        "question.$": "$.question",
        "answer.$": "$.refinedResult.response",
        "fromKnowledgeBase": true,
        "sources.$": "$.refinedResult.sources",
        "refined": true,
        "validation.$": "$.validation.parsed",
        "confidence": "medium",
        "metrics": {
          "tokens.$": "States.MathAdd($.ragResult._tokens, $.validation._tokens, $.refinedResult._tokens)",
          "cost.$": "States.MathAdd($.ragResult._cost, $.validation._cost, $.refinedResult._cost)"
        }
      },
      "End": true
    },
    "FormatOutput": {
      "Type": "Pass",
      "Parameters": {
        "question.$": "$.question",
        "answer.$": "$.ragResult.response",
        "fromKnowledgeBase": true,
        "sources.$": "$.ragResult.sources",
        "refined": false,
        "validation.$": "$.validation.parsed",
        "confidence": "high",
        "metrics": {
          "tokens.$": "States.MathAdd($.ragResult._tokens, $.validation._tokens)",
          "cost.$": "States.MathAdd($.ragResult._cost, $.validation._cost)"
        }
      },
      "End": true
    }
  }
}
```

## Step 5: Run the Workflow

Create `run-knowledge-qa.php`:

```php
<?php

require_once 'vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use ClaudePhp\ClaudePhp;
use MyOrg\Adapters\RAGAgentAdapter;
use MyOrg\Adapters\ClaudeAgentAdapter;
use MyOrg\RAG\KnowledgeBase;

// Initialize
$client = ClaudePhp::make(getenv('ANTHROPIC_API_KEY'));

// Create shared knowledge base
$knowledgeBase = new KnowledgeBase();

// Seed knowledge base with sample documents
$sampleDocs = [
    [
        'content' => <<<DOC
# PHP 8.3 New Features

PHP 8.3, released in November 2023, introduces several important features:

## Typed Class Constants
Class constants can now have explicit type declarations:
```php
class Example {
    public const string VERSION = '1.0.0';
}
```

## json_validate() Function
A new function to validate JSON without decoding:
```php
if (json_validate(\$jsonString)) {
    // Valid JSON
}
```

## Randomizer Additions
New methods for the Randomizer class including getBytesFromString().

## Override Attribute
The #[Override] attribute ensures a method overrides a parent method.
DOC,
        'metadata' => ['source' => 'PHP Documentation', 'category' => 'language'],
    ],
    [
        'content' => <<<DOC
# Best Practices for PHP Performance

## Opcode Caching
Always enable OPcache in production. It caches compiled PHP bytecode.

Configuration recommendations:
- opcache.memory_consumption=256
- opcache.max_accelerated_files=20000
- opcache.validate_timestamps=0 (in production)

## Database Optimization
- Use prepared statements for repeated queries
- Implement connection pooling
- Add appropriate indexes
- Use EXPLAIN to analyze slow queries

## Caching Strategies
- Use Redis or Memcached for session storage
- Implement application-level caching for expensive operations
- Consider full-page caching for static content

## Memory Management
- Unset large variables when no longer needed
- Use generators for large datasets
- Profile memory usage with tools like Blackfire
DOC,
        'metadata' => ['source' => 'Performance Guide', 'category' => 'performance'],
    ],
    [
        'content' => <<<DOC
# PHP Security Best Practices

## Input Validation
Always validate and sanitize user input:
- Use filter_var() for validation
- Escape output appropriately for context (HTML, SQL, etc.)
- Never trust client-side validation alone

## SQL Injection Prevention
- Use prepared statements with PDO or MySQLi
- Never concatenate user input into queries
- Use parameterized queries exclusively

## XSS Prevention
- Escape output with htmlspecialchars()
- Use Content Security Policy headers
- Sanitize HTML input with libraries like HTMLPurifier

## Password Security
- Use password_hash() with PASSWORD_DEFAULT
- Verify with password_verify()
- Implement rate limiting for login attempts

## Session Security
- Regenerate session ID on login
- Use secure and httponly cookie flags
- Implement session timeout
DOC,
        'metadata' => ['source' => 'Security Guide', 'category' => 'security'],
    ],
];

$knowledgeBase->ingestBatch($sampleDocs);
echo "Knowledge base initialized with " . $knowledgeBase->getChunkCount() . " chunks\n\n";

// Create RAG-enabled agent
$ragAssistant = RAGAgentAdapter::create(
    'RAGAssistant',
    $client,
    $knowledgeBase,
    'You are a helpful PHP expert. Answer questions based on the provided documentation. Always cite your sources using document numbers.',
    5  // Top 5 documents
);

// Create supporting agents
$documentIngester = ClaudeAgentAdapter::create(
    'DocumentIngester',
    $client,
    'You summarize and prepare documents for ingestion into a knowledge base.'
);

$generalAssistant = ClaudeAgentAdapter::create(
    'GeneralAssistant',
    $client,
    'You are a helpful assistant. When answering without knowledge base sources, clearly state that the answer is from general knowledge.'
);

$answerValidator = ClaudeAgentAdapter::create(
    'AnswerValidator',
    $client,
    'You validate answers against source documents. Check for accuracy and unsupported claims. Always respond in valid JSON format.'
);

// Register agents
$registry = new AgentRegistry();
$registry->register('RAGAssistant', $ragAssistant);
$registry->register('DocumentIngester', $documentIngester);
$registry->register('GeneralAssistant', $generalAssistant);
$registry->register('AnswerValidator', $answerValidator);

// Load workflow
$engine = WorkflowEngine::fromFile('workflows/knowledge-qa.asl.json', $registry);

// Test questions
$questions = [
    'What new features were introduced in PHP 8.3?',
    'How can I prevent SQL injection in PHP?',
    'What are the best practices for PHP performance optimization?',
    'How do I implement microservices in PHP?',  // Not in knowledge base
];

foreach ($questions as $question) {
    echo str_repeat('=', 70) . "\n";
    echo "Question: {$question}\n";
    echo str_repeat('=', 70) . "\n\n";

    $result = $engine->run([
        'question' => $question,
        'ingestDocuments' => false,
    ]);

    if ($result->isSuccess()) {
        $output = $result->getOutput();

        echo "Answer:\n";
        echo wordwrap($output['answer'], 70) . "\n\n";

        echo "Source Information:\n";
        echo "- From Knowledge Base: " . ($output['fromKnowledgeBase'] ? 'Yes' : 'No') . "\n";
        echo "- Confidence: {$output['confidence']}\n";
        echo "- Refined: " . ($output['refined'] ?? false ? 'Yes' : 'No') . "\n";

        if (!empty($output['sources'])) {
            echo "- Sources Used: " . count($output['sources']) . "\n";
            foreach (array_slice($output['sources'], 0, 2) as $i => $source) {
                $src = $source['metadata']['source'] ?? 'Unknown';
                $score = number_format($source['score'] * 100, 1);
                echo "  [{$i}] {$src} (relevance: {$score}%)\n";
            }
        }

        if (isset($output['validation'])) {
            echo "- Validation Score: {$output['validation']['accuracy']}/10\n";
        }

        echo "\nMetrics:\n";
        echo "- Tokens: {$output['metrics']['tokens']}\n";
        echo "- Cost: $" . number_format($output['metrics']['cost'], 4) . "\n";
        echo "- Duration: " . number_format($result->getDuration(), 2) . "s\n";
    } else {
        echo "Error: " . $result->getError() . "\n";
    }

    echo "\n";
}
```

## Expected Output

```
Knowledge base initialized with 9 chunks

======================================================================
Question: What new features were introduced in PHP 8.3?
======================================================================

Answer:
Based on the documentation [Document 1], PHP 8.3 introduced several 
important features:

1. **Typed Class Constants** - Class constants can now have explicit 
   type declarations, improving type safety.

2. **json_validate() Function** - A new function to validate JSON 
   strings without the overhead of decoding them.

3. **Randomizer Additions** - New methods for the Randomizer class, 
   including getBytesFromString().

4. **Override Attribute** - The #[Override] attribute ensures a 
   method properly overrides a parent method, catching errors at 
   compile time.

Source Information:
- From Knowledge Base: Yes
- Confidence: high
- Refined: No
- Sources Used: 2
  [0] PHP Documentation (relevance: 94.2%)
  [1] Performance Guide (relevance: 45.3%)
- Validation Score: 9/10

Metrics:
- Tokens: 1847
- Cost: $0.0321
- Duration: 4.52s

======================================================================
Question: How do I implement microservices in PHP?
======================================================================

Answer:
Based on my general knowledge (this topic is not covered in the 
knowledge base):

Implementing microservices in PHP involves several key considerations:

1. **Framework Choice**: Use lightweight frameworks like Slim, Lumen, 
   or Symfony Micro for individual services.

2. **Communication**: Implement REST APIs or use message queues 
   (RabbitMQ, Redis) for inter-service communication.

3. **Service Discovery**: Use tools like Consul or Kubernetes for 
   service discovery and load balancing.

Note: This answer is from general knowledge, not the knowledge base.

Source Information:
- From Knowledge Base: No
- Confidence: low
- Refined: No

Metrics:
- Tokens: 1234
- Cost: $0.0215
- Duration: 3.21s
```

## RAG Patterns

### Pattern 1: Simple RAG

```
Query → Retrieve → Generate
```

### Pattern 2: RAG with Reranking

```
Query → Retrieve Many → Rerank → Select Top → Generate
```

### Pattern 3: RAG with Validation

```
Query → Retrieve → Generate → Validate → Refine (if needed)
```

### Pattern 4: Agentic RAG

```
Query → Agent decides retrieval strategy → Multiple retrievals → Synthesize
```

## Experiment

Try these modifications:

### Add Document Sources

```php
// Ingest new documents at runtime
$result = $engine->run([
    'question' => 'What is the latest version?',
    'ingestDocuments' => true,
    'documents' => [
        ['content' => 'PHP 8.4 is scheduled for...', 'metadata' => [...]]
    ]
]);
```

### Adjust Retrieval Parameters

```php
$ragAssistant = RAGAgentAdapter::create(
    'RAGAssistant',
    $client,
    $knowledgeBase,
    $systemPrompt,
    10,    // Retrieve more documents
    0.7    // Higher relevance threshold
);
```

### Combine with Multi-Agent

```json
{
  "Type": "Parallel",
  "Branches": [
    { "Agent": "RAGExpert1", "KnowledgeBase": "technical" },
    { "Agent": "RAGExpert2", "KnowledgeBase": "business" }
  ]
}
```

## Common Mistakes

### Empty Knowledge Base

```
Warning: No relevant documents found
```

**Fix**: Ensure documents are ingested before querying.

### Poor Chunking

```
Problem: Relevant info split across chunks, not retrieved together
```

**Fix**: Use semantic chunking or larger chunk sizes.

### Low Relevance Threshold

```
Problem: Irrelevant documents included, confusing the model
```

**Fix**: Increase relevance threshold (0.6-0.8 recommended).

### Missing Citations

```
Problem: Answer doesn't cite sources
```

**Fix**: Include citation instructions in system prompt.

## Summary

You've learned:

- ✅ Understanding RAG architecture and components
- ✅ Building vector stores and embedders
- ✅ Creating RAG-enabled agent adapters
- ✅ Document ingestion and retrieval workflows
- ✅ Answer validation and refinement
- ✅ Combining RAG with ASL orchestration

## Congratulations!

You've completed all the claude-php-agent integration tutorials! You now have comprehensive knowledge of:

| Tutorial | Key Skills |
|----------|------------|
| 13 | Basic integration and adapter pattern |
| 14 | Tool integration and permissions |
| 15 | Multi-agent orchestration |
| 16 | Loop strategies (ReAct, Reflection, Plan-Execute) |
| 17 | RAG and knowledge-augmented agents |

## Next Steps

- Explore the [complete examples](../../examples/)
- Read the [full specification](../../SPECIFICATION.md)
- Check the [best practices guide](../guides/best-practices.md)
- Review [production deployment](../guides/production-deployment.md)

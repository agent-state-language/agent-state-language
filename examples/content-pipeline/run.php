<?php

/**
 * Content Pipeline Example Runner
 * 
 * This script demonstrates how to run the content generation and publishing workflow.
 * It includes mock agents for testing without external dependencies.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Agents\AgentInterface;

// =============================================================================
// Mock Agents for Testing
// =============================================================================

/**
 * Generates content based on topic and parameters
 */
class ContentGeneratorAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $topic = $parameters['topic'] ?? 'General Topic';
        $contentType = $parameters['contentType'] ?? 'article';
        $targetAudience = $parameters['targetAudience'] ?? 'general';
        $tone = $parameters['tone'] ?? 'neutral';
        $length = $parameters['length'] ?? 'medium';
        $previousIssues = $parameters['previousIssues'] ?? [];
        
        // Generate content based on parameters
        $content = $this->generateContent($topic, $contentType, $tone, $length, $previousIssues);
        
        return [
            'content' => $content,
            'wordCount' => str_word_count($content),
            'contentType' => $contentType,
            'targetAudience' => $targetAudience,
            'generatedAt' => date('c'),
            'regenerated' => !empty($previousIssues)
        ];
    }
    
    private function generateContent(string $topic, string $type, string $tone, string $length, array $issues): string
    {
        $lengthMultiplier = match($length) {
            'short' => 1,
            'medium' => 2,
            'long' => 3,
            default => 2
        };
        
        $intro = match($tone) {
            'informative' => "In this comprehensive guide, we explore {$topic}.",
            'casual' => "Let's talk about {$topic} and why it matters.",
            'professional' => "This article provides an in-depth analysis of {$topic}.",
            default => "Today we discuss {$topic}."
        };
        
        $body = str_repeat(" This is important information about {$topic}.", $lengthMultiplier * 3);
        
        $conclusion = "In conclusion, understanding {$topic} is essential for success.";
        
        return $intro . $body . " " . $conclusion;
    }

    public function getName(): string
    {
        return 'ContentGenerator';
    }
}

/**
 * Moderates content for policy violations
 */
class ContentModeratorAgent implements AgentInterface
{
    private array $blockedPhrases = ['harmful content', 'explicit material'];
    private array $flaggedPatterns = ['competitor', 'confidential'];
    
    public function execute(array $parameters): array
    {
        $content = $parameters['content'] ?? '';
        $contentType = $parameters['contentType'] ?? 'article';
        
        $blocked = false;
        $flagged = false;
        $issues = [];
        $flags = [];
        
        // Check for blocked content
        foreach ($this->blockedPhrases as $phrase) {
            if (stripos($content, $phrase) !== false) {
                $blocked = true;
                $issues[] = [
                    'type' => 'blocked_content',
                    'phrase' => $phrase,
                    'action' => 'block'
                ];
            }
        }
        
        // Check for flagged content
        foreach ($this->flaggedPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $flagged = true;
                $flags[] = [
                    'type' => 'flagged_pattern',
                    'pattern' => $pattern,
                    'action' => 'review'
                ];
            }
        }
        
        // Check for PII (simplified)
        if (preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $content)) {
            $flags[] = [
                'type' => 'pii_detected',
                'detail' => 'email_address',
                'action' => 'redact'
            ];
        }
        
        return [
            'blocked' => $blocked,
            'flagged' => $flagged && !$blocked,
            'issues' => $issues,
            'flags' => $flags,
            'moderatedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'ContentModerator';
    }
}

/**
 * Optimizes content for SEO
 */
class SEOOptimizerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $content = $parameters['content'] ?? '';
        $keywords = $parameters['keywords'] ?? '';
        
        $keywordDensity = $this->calculateKeywordDensity($content, $keywords);
        
        return [
            'optimized' => true,
            'keywordDensity' => round($keywordDensity, 2),
            'suggestions' => [
                'Add more headers for better structure',
                'Include internal links',
                'Optimize meta description'
            ],
            'score' => min(100, 60 + ($keywordDensity * 10))
        ];
    }
    
    private function calculateKeywordDensity(string $content, string $keyword): float
    {
        $wordCount = str_word_count($content);
        $keywordCount = substr_count(strtolower($content), strtolower($keyword));
        return $wordCount > 0 ? ($keywordCount / $wordCount) * 100 : 0;
    }

    public function getName(): string
    {
        return 'SEOOptimizer';
    }
}

/**
 * Generates metadata for content
 */
class MetadataGeneratorAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $content = $parameters['content'] ?? '';
        $contentType = $parameters['contentType'] ?? 'article';
        
        // Extract first sentence for description
        $firstSentence = strtok($content, '.');
        
        return [
            'title' => $this->generateTitle($content),
            'description' => trim($firstSentence) . '...',
            'tags' => $this->extractTags($content),
            'contentType' => $contentType,
            'generatedAt' => date('c')
        ];
    }
    
    private function generateTitle(string $content): string
    {
        // Simple title extraction from first meaningful words
        $words = array_slice(str_word_count($content, 1), 0, 8);
        return ucfirst(implode(' ', $words));
    }
    
    private function extractTags(string $content): array
    {
        // Extract common meaningful words as tags
        $words = str_word_count(strtolower($content), 1);
        $frequency = array_count_values($words);
        arsort($frequency);
        
        $tags = [];
        foreach (array_slice($frequency, 0, 5) as $word => $count) {
            if (strlen($word) > 4) {
                $tags[] = $word;
            }
        }
        
        return $tags;
    }

    public function getName(): string
    {
        return 'MetadataGenerator';
    }
}

/**
 * Generates images for content
 */
class ImageGeneratorAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $topic = $parameters['topic'] ?? '';
        $style = $parameters['style'] ?? 'modern';
        
        return [
            'images' => [
                [
                    'id' => 'img_' . uniqid(),
                    'type' => 'hero',
                    'url' => "https://images.example.com/hero/{$topic}",
                    'alt' => "Hero image for {$topic}"
                ],
                [
                    'id' => 'img_' . uniqid(),
                    'type' => 'thumbnail',
                    'url' => "https://images.example.com/thumb/{$topic}",
                    'alt' => "Thumbnail for {$topic}"
                ]
            ],
            'style' => $style,
            'generatedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'ImageGenerator';
    }
}

/**
 * Assembles all content elements
 */
class ContentAssemblerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $draft = $parameters['draft'] ?? [];
        $seo = $parameters['seo'] ?? [];
        $metadata = $parameters['metadata'] ?? [];
        $images = $parameters['images'] ?? [];
        
        return [
            'content' => $draft['content'] ?? '',
            'title' => $metadata['title'] ?? 'Untitled',
            'description' => $metadata['description'] ?? '',
            'tags' => $metadata['tags'] ?? [],
            'seoScore' => $seo['score'] ?? 0,
            'images' => $images['images'] ?? [],
            'preview' => substr($draft['content'] ?? '', 0, 200) . '...',
            'metadata' => $metadata,
            'assembledAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'ContentAssembler';
    }
}

/**
 * Publishes content immediately
 */
class PublisherAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $content = $parameters['content'] ?? [];
        $immediate = $parameters['immediate'] ?? true;
        
        $publishId = 'pub_' . uniqid();
        
        return [
            'published' => true,
            'publishId' => $publishId,
            'url' => "https://example.com/content/{$publishId}",
            'title' => $content['title'] ?? 'Untitled',
            'publishedAt' => date('c'),
            'immediate' => $immediate
        ];
    }

    public function getName(): string
    {
        return 'Publisher';
    }
}

/**
 * Schedules content for future publishing
 */
class SchedulerAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $content = $parameters['content'] ?? [];
        $scheduledTime = $parameters['scheduledTime'] ?? date('c', strtotime('+1 day'));
        
        return [
            'scheduled' => true,
            'scheduleId' => 'sch_' . uniqid(),
            'title' => $content['title'] ?? 'Untitled',
            'scheduledFor' => $scheduledTime,
            'createdAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'Scheduler';
    }
}

/**
 * Saves content as draft
 */
class DraftSaverAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $content = $parameters['content'] ?? [];
        
        return [
            'saved' => true,
            'draftId' => 'draft_' . uniqid(),
            'title' => $content['title'] ?? 'Untitled',
            'savedAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'DraftSaver';
    }
}

/**
 * Sends notifications
 */
class NotifierAgent implements AgentInterface
{
    public function execute(array $parameters): array
    {
        $type = $parameters['type'] ?? 'general';
        $details = $parameters['details'] ?? [];
        
        return [
            'notified' => true,
            'type' => $type,
            'message' => "Content '{$details['title']}' has been published",
            'sentAt' => date('c')
        ];
    }

    public function getName(): string
    {
        return 'Notifier';
    }
}

// =============================================================================
// Main Execution
// =============================================================================

echo "=== Content Pipeline Workflow Example ===\n\n";

// Create and configure the agent registry
$registry = new AgentRegistry();
$registry->register('ContentGenerator', new ContentGeneratorAgent());
$registry->register('ContentModerator', new ContentModeratorAgent());
$registry->register('SEOOptimizer', new SEOOptimizerAgent());
$registry->register('MetadataGenerator', new MetadataGeneratorAgent());
$registry->register('ImageGenerator', new ImageGeneratorAgent());
$registry->register('ContentAssembler', new ContentAssemblerAgent());
$registry->register('Publisher', new PublisherAgent());
$registry->register('Scheduler', new SchedulerAgent());
$registry->register('DraftSaver', new DraftSaverAgent());
$registry->register('Notifier', new NotifierAgent());

// Load the workflow
$engine = WorkflowEngine::fromFile(__DIR__ . '/workflow.asl.json', $registry);

// Test Case 1: Clean content generation
echo "Test 1: Standard Blog Post Generation\n";
echo str_repeat('-', 45) . "\n";

$result1 = $engine->run([
    'topic' => 'Best practices for remote work',
    'contentType' => 'blog_post',
    'targetAudience' => 'professionals',
    'tone' => 'informative',
    'length' => 'medium'
]);

if ($result1->isSuccess()) {
    $output = $result1->getOutput();
    echo "Status: Published\n";
    echo "Title: " . ($output['title'] ?? 'N/A') . "\n";
    echo "URL: " . ($output['url'] ?? 'N/A') . "\n";
    echo "SEO Score: " . ($output['seoScore'] ?? 'N/A') . "\n";
}

// Test Case 2: Casual tone, short content
echo "\n\nTest 2: Casual Short Article\n";
echo str_repeat('-', 45) . "\n";

$result2 = $engine->run([
    'topic' => 'Coffee brewing techniques',
    'contentType' => 'article',
    'targetAudience' => 'enthusiasts',
    'tone' => 'casual',
    'length' => 'short'
]);

if ($result2->isSuccess()) {
    $output = $result2->getOutput();
    echo "Status: " . ($output['published'] ? 'Published' : 'Saved') . "\n";
    echo "Title: " . ($output['title'] ?? 'N/A') . "\n";
}

// Test Case 3: Professional long-form content
echo "\n\nTest 3: Professional Long-Form Content\n";
echo str_repeat('-', 45) . "\n";

$result3 = $engine->run([
    'topic' => 'Enterprise cloud migration strategies',
    'contentType' => 'whitepaper',
    'targetAudience' => 'CTOs',
    'tone' => 'professional',
    'length' => 'long'
]);

if ($result3->isSuccess()) {
    $output = $result3->getOutput();
    echo "Status: Published\n";
    echo "Title: " . ($output['title'] ?? 'N/A') . "\n";
    echo "Word Count: Approximately " . (strlen($output['content'] ?? '') / 5) . " words\n";
}

echo "\n\n=== Pipeline Execution Complete ===\n";

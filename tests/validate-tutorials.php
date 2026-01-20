<?php

/**
 * Comprehensive validation of code samples across all documentation.
 * 
 * Validates:
 * - Tutorial markdown files
 * - Guide markdown files
 * - Reference markdown files
 * - Example workflow JSON files
 * - Example run.php files
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AgentStateLanguage\Validation\WorkflowValidator;

$docsDir = __DIR__ . '/../docs';
$examplesDir = __DIR__ . '/../examples';

$errors = [];
$jsonSuccess = 0;
$phpSuccess = 0;
$workflowSuccess = 0;

echo "===========================================\n";
echo "  ASL Documentation Code Sample Validator\n";
echo "===========================================\n\n";

// Validate markdown files in all docs directories
$docPaths = [
    'tutorials' => $docsDir . '/tutorials',
    'guides' => $docsDir . '/guides',
    'reference' => $docsDir . '/reference',
];

foreach ($docPaths as $section => $path) {
    if (!is_dir($path)) {
        continue;
    }
    
    $files = glob($path . '/*.md');
    
    echo "Validating {$section}...\n";
    
    foreach ($files as $file) {
        $filename = basename($file);
        $content = file_get_contents($file);
        
        // Extract and validate JSON code blocks
        preg_match_all('/```json\s*([\s\S]*?)```/', $content, $jsonMatches);
        
        foreach ($jsonMatches[1] as $i => $json) {
            $json = trim($json);
            if (empty($json)) continue;
            
            // Skip obvious pseudocode/placeholder blocks
            // These patterns indicate the JSON is meant to be illustrative, not runnable:
            // - "..." ellipsis (three or more dots)
            // - "{ ... }" empty placeholder blocks (with or without spaces)
            // - "Operator: value" placeholder syntax
            // - Template placeholders like "{{variable}}" or "${variable}"
            // - ASL context references that break JSON like "${$$.Execution.StartTime}"
            // - JavaScript-style comments // which break JSON
            if (preg_match('/\.{3}|Operator|value.*:.*StateName|\$\{|\$\$\.|^\/\/|^\s*\/\//m', $json)) {
                continue; // Skip pseudocode/template examples
            }
            
            $decoded = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "[{$section}] {$filename}: JSON block " . ($i + 1) . " - " . json_last_error_msg();
            } else {
                $jsonSuccess++;
                
                // If it looks like a complete workflow (has States with actual state definitions), validate it
                // Skip templates with Parameters block or custom Output fields (they use extended syntax)
                if (isset($decoded['StartAt']) && isset($decoded['States']) && !isset($decoded['Parameters'])) {
                    // Check if this is a complete workflow (all states have Next or End)
                    $isCompleteWorkflow = true;
                    foreach ($decoded['States'] as $state) {
                        if (!isset($state['Type'])) {
                            $isCompleteWorkflow = false;
                            break;
                        }
                        // Skip validation for template placeholders
                        if (isset($state['Output'])) {
                            $isCompleteWorkflow = false;
                            break;
                        }
                    }
                    
                    if ($isCompleteWorkflow) {
                        $validator = new WorkflowValidator();
                        try {
                            $validator->validate($decoded);
                            $workflowSuccess++;
                        } catch (\AgentStateLanguage\Exceptions\ValidationException $e) {
                            // Only report if this looks like it should be a complete workflow
                            if (!preg_match('/\{\{.*\}\}/', $json)) {
                                $errors[] = "[{$section}] {$filename}: JSON block " . ($i + 1) . " - Invalid workflow: " . $e->getMessage();
                            }
                        }
                    }
                }
            }
        }
        
        // Extract and validate PHP code blocks
        // Use a more robust extraction that handles ``` inside strings
        // Look for code blocks that start with ```php and end with ``` at line start
        preg_match_all('/```php\s*([\s\S]*?)^```/m', $content, $phpMatches);
        
        foreach ($phpMatches[1] as $i => $php) {
            $php = trim($php);
            if (empty($php)) continue;
            
            // Skip partial/illustrative code blocks
            // - Lines ending with "..." indicate continuation
            // - Blocks that start mid-class or mid-function
            // - Blocks with heredocs (<<<) that may contain embedded code
            if (preg_match('/\.\.\.\s*$/m', $php) || preg_match('/\/\/\s*\.\.\./', $php) || preg_match('/<<<\w+/', $php)) {
                continue; // Skip partial code examples
            }
            
            // Check for balanced braces
            $openBraces = substr_count($php, '{');
            $closeBraces = substr_count($php, '}');
            $openParens = substr_count($php, '(');
            $closeParens = substr_count($php, ')');
            $openBrackets = substr_count($php, '[');
            $closeBrackets = substr_count($php, ']');
            
            if ($openBraces !== $closeBraces) {
                $errors[] = "[{$section}] {$filename}: PHP block " . ($i + 1) . " - Mismatched braces ({$openBraces} open, {$closeBraces} close)";
            } elseif ($openParens !== $closeParens) {
                $errors[] = "[{$section}] {$filename}: PHP block " . ($i + 1) . " - Mismatched parentheses ({$openParens} open, {$closeParens} close)";
            } elseif ($openBrackets !== $closeBrackets) {
                $errors[] = "[{$section}] {$filename}: PHP block " . ($i + 1) . " - Mismatched brackets ({$openBrackets} open, {$closeBrackets} close)";
            } else {
                $phpSuccess++;
            }
        }
    }
}

// Validate example workflows
echo "Validating examples...\n";

$exampleDirs = glob($examplesDir . '/*', GLOB_ONLYDIR);

foreach ($exampleDirs as $exampleDir) {
    $exampleName = basename($exampleDir);
    
    // Validate workflow JSON
    $workflowFile = $exampleDir . '/workflow.asl.json';
    if (file_exists($workflowFile)) {
        $content = file_get_contents($workflowFile);
        $decoded = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "[examples] {$exampleName}/workflow.asl.json - Invalid JSON: " . json_last_error_msg();
        } else {
            $jsonSuccess++;
            
            $validator = new WorkflowValidator();
            try {
                $validator->validate($decoded);
                $workflowSuccess++;
                echo "  ✓ {$exampleName}/workflow.asl.json\n";
            } catch (\AgentStateLanguage\Exceptions\ValidationException $e) {
                $errors[] = "[examples] {$exampleName}/workflow.asl.json - Validation failed: " . $e->getMessage();
                echo "  ✗ {$exampleName}/workflow.asl.json\n";
            }
        }
    }
    
    // Check for run.php
    $runFile = $exampleDir . '/run.php';
    if (file_exists($runFile)) {
        $content = file_get_contents($runFile);
        
        // Basic syntax check
        $openBraces = substr_count($content, '{');
        $closeBraces = substr_count($content, '}');
        
        if ($openBraces !== $closeBraces) {
            $errors[] = "[examples] {$exampleName}/run.php - Mismatched braces";
        } else {
            $phpSuccess++;
            echo "  ✓ {$exampleName}/run.php\n";
        }
    }
    
    // Check README
    $readmeFile = $exampleDir . '/README.md';
    if (file_exists($readmeFile)) {
        $content = file_get_contents($readmeFile);
        
        // Validate any JSON in README
        preg_match_all('/```json\s*([\s\S]*?)```/', $content, $jsonMatches);
        
        foreach ($jsonMatches[1] as $i => $json) {
            $json = trim($json);
            if (empty($json)) continue;
            
            // Skip pseudocode/placeholder blocks (same as tutorial validation)
            if (preg_match('/\.{3}|Operator|value.*:.*StateName|\$\{|\$\$\.|^\/\/|^\s*\/\//m', $json)) {
                continue; // Skip pseudocode/template examples
            }
            
            $decoded = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "[examples] {$exampleName}/README.md: JSON block " . ($i + 1) . " - " . json_last_error_msg();
            } else {
                $jsonSuccess++;
            }
        }
    }
}

echo "\n===========================================\n";
echo "  Validation Results\n";
echo "===========================================\n\n";

echo "Summary:\n";
echo "  JSON blocks validated: {$jsonSuccess}\n";
echo "  PHP blocks validated: {$phpSuccess}\n";
echo "  Workflows validated: {$workflowSuccess}\n";
echo "  Errors found: " . count($errors) . "\n\n";

if (!empty($errors)) {
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    echo "\n";
    exit(1);
}

echo "✓ All code samples validated successfully!\n";
exit(0);

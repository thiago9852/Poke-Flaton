<?php

// Utility script to scan Twig templates and find missing translations in messages.en.yaml
$templatesDir = __DIR__ . '/../templates';
$yamlFile = __DIR__ . '/../translations/messages.en.yaml';

if (!is_dir($templatesDir)) {
    die("Directory templates/ does not exist.\n");
}
if (!is_file($yamlFile)) {
    die("File messages.en.yaml does not exist.\n");
}

// 1. Read existing translation keys from messages.en.yaml
$yamlContent = file_get_contents($yamlFile);
$existingKeys = [];
// YAML keys look like: "Key Here": "Value Here" or "Key Here":
// Let's capture the key within quotes at the start of a line
if (preg_match_all('/^"([^"]+)"\s*:/m', $yamlContent, $matches)) {
    foreach ($matches[1] as $k) {
        $existingKeys[$k] = true;
    }
}

echo "Found " . count($existingKeys) . " translation keys in messages.en.yaml.\n\n";

// 2. Recursively find all Twig files
$twigFiles = [];
$directory = new RecursiveDirectoryIterator($templatesDir);
$iterator = new RecursiveIteratorIterator($directory);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'twig') {
        $twigFiles[] = $file->getPathname();
    }
}

echo "Scanning " . count($twigFiles) . " Twig template files...\n";

// 3. Extract keys using regexes
$usedKeys = [];
foreach ($twigFiles as $filePath) {
    $content = file_get_contents($filePath);
    
    // Pattern 1: 'some text'|trans or "some text"|trans
    // Handle optional spaces and filters: 'text'|trans or 'text'|trans(...)
    // Allow single and double quotes
    if (preg_match_all('/([\'"])([^\'"]+)\1\s*\|\s*trans/s', $content, $matches)) {
        foreach ($matches[2] as $key) {
            // Filter out keys containing twig variables or filters inside the quote (if any, though rare)
            $keyClean = trim($key);
            if ($keyClean !== '') {
                $usedKeys[$keyClean][$filePath] = true;
            }
        }
    }

    // Pattern 2: {% trans %}some text{% endtrans %}
    if (preg_match_all('/{%\s*trans\s*%}(.*?){%\s*endtrans\s*%}/s', $content, $matches)) {
        foreach ($matches[1] as $key) {
            $keyClean = trim($key);
            if ($keyClean !== '') {
                $usedKeys[$keyClean][$filePath] = true;
            }
        }
    }
}

echo "Found " . count($usedKeys) . " unique translation keys used in templates.\n\n";

// 4. Compare and print missing keys
$missing = [];
foreach ($usedKeys as $key => $files) {
    if (!isset($existingKeys[$key])) {
        // Strip the full path to make it readable
        $shortFiles = array_map(function($f) {
            return basename($f);
        }, array_keys($files));
        $missing[$key] = $shortFiles;
    }
}

if (empty($missing)) {
    echo "Congratulations! All translation keys are defined in messages.en.yaml!\n";
} else {
    echo "Missing keys found (" . count($missing) . "):\n";
    echo "=====================================\n";
    foreach ($missing as $key => $files) {
        echo "Key: \"$key\"\n";
        echo "Used in: " . implode(', ', $files) . "\n";
        echo "-------------------------------------\n";
    }
    
    // Let's print out the YAML lines for easy copy-pasting or automated appending
    echo "\nYAML lines to add:\n";
    echo "=====================================\n";
    foreach (array_keys($missing) as $key) {
        // Escape quotes just in case
        $escapedKey = str_replace('"', '\\"', $key);
        echo "\"$escapedKey\": \"$escapedKey\"\n";
    }
}
